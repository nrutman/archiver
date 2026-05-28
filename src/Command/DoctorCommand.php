<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:doctor',
    description: 'Checks whether the runtime can create and clean up encrypted archives.',
)]
final class DoctorCommand extends Command
{
    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = ['ctype', 'fileinfo', 'iconv', 'intl', 'mbstring', 'openssl', 'zip'];

    public function __construct(
        private readonly string $tempRoot,
        private readonly int $maxFiles,
        private readonly int $maxSingleFileBytes,
        private readonly int $maxTotalBytes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errors = [];
        $warnings = [];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf('Missing required PHP extension: %s.', $extension);
            }
        }

        if (!class_exists(\ZipArchive::class)) {
            $errors[] = 'ZipArchive is unavailable.';
        } else {
            if (!\defined('ZipArchive::EM_AES_256')) {
                $errors[] = 'ZipArchive::EM_AES_256 is unavailable.';
            }

            if (!\defined('ZipArchive::EM_TRAD_PKWARE')) {
                $errors[] = 'ZipArchive::EM_TRAD_PKWARE is unavailable.';
            }
        }

        $this->checkTemporaryStorage($errors);
        if ([] === $errors) {
            $this->checkEncryptedZipCreation($errors);
        }
        $this->checkPhpLimits($warnings);

        if ([] !== $warnings) {
            $io->warning($warnings);
        }

        if ([] !== $errors) {
            $io->error($errors);

            return Command::FAILURE;
        }

        $io->success('Runtime checks passed. This host can create encrypted temporary archives.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $errors
     */
    private function checkTemporaryStorage(array &$errors): void
    {
        if (!is_dir($this->tempRoot) && !@mkdir($this->tempRoot, 0700, true) && !is_dir($this->tempRoot)) {
            $errors[] = sprintf('Could not create temporary archive directory: %s.', $this->tempRoot);

            return;
        }

        if (!is_writable($this->tempRoot)) {
            $errors[] = sprintf('Temporary archive directory is not writable: %s.', $this->tempRoot);
        }
    }

    /**
     * @param list<string> $errors
     */
    private function checkEncryptedZipCreation(array &$errors): void
    {
        $workspace = $this->tempRoot.\DIRECTORY_SEPARATOR.'.doctor-'.bin2hex(random_bytes(8));
        if (!@mkdir($workspace, 0700)) {
            $errors[] = 'Could not create a temporary doctor workspace.';

            return;
        }

        try {
            $sourcePath = $workspace.\DIRECTORY_SEPARATOR.'source.txt';
            file_put_contents($sourcePath, 'archiver doctor probe');

            $this->checkEncryptedZipMode($workspace, $sourcePath, 'aes256.zip', \ZipArchive::EM_AES_256);
            $this->checkEncryptedZipMode($workspace, $sourcePath, 'zipcrypto.zip', \ZipArchive::EM_TRAD_PKWARE);
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        } finally {
            $this->removeDirectory($workspace);
        }
    }

    private function checkEncryptedZipMode(string $workspace, string $sourcePath, string $zipName, int $method): void
    {
        $zipPath = $workspace.\DIRECTORY_SEPARATOR.$zipName;
        $zip = new \ZipArchive();
        $status = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $status) {
            throw new \RuntimeException(sprintf('Could not create doctor ZIP probe. ZipArchive status: %s.', $status));
        }

        if (!$zip->setPassword('archiver-doctor-password')) {
            $zip->close();
            throw new \RuntimeException('Could not set a ZIP password during the doctor probe.');
        }

        if (!$zip->addFile($sourcePath, 'source.txt')) {
            $zip->close();
            throw new \RuntimeException('Could not add a file during the doctor ZIP probe.');
        }

        if (!$zip->setEncryptionName('source.txt', $method)) {
            $zip->close();
            throw new \RuntimeException('Could not encrypt a file during the doctor ZIP probe.');
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Could not finalize the doctor ZIP probe.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath)) {
            throw new \RuntimeException('Could not reopen the doctor ZIP probe.');
        }

        try {
            if (!$zip->setPassword('archiver-doctor-password') || 'archiver doctor probe' !== $zip->getFromName('source.txt')) {
                throw new \RuntimeException('Could not read the encrypted doctor ZIP probe.');
            }
        } finally {
            $zip->close();
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }

    /**
     * @param list<string> $warnings
     */
    private function checkPhpLimits(array &$warnings): void
    {
        $maxFileUploads = (int) ini_get('max_file_uploads');
        if ($maxFileUploads > 0 && $maxFileUploads < $this->maxFiles) {
            $warnings[] = sprintf('PHP max_file_uploads is %d, below ARCHIVER_MAX_FILES=%d.', $maxFileUploads, $this->maxFiles);
        }

        $uploadMaxFilesize = $this->iniBytes('upload_max_filesize');
        if (null !== $uploadMaxFilesize && $uploadMaxFilesize < $this->maxSingleFileBytes) {
            $warnings[] = 'PHP upload_max_filesize is below ARCHIVER_MAX_SINGLE_FILE_BYTES.';
        }

        $postMaxSize = $this->iniBytes('post_max_size');
        if (null !== $postMaxSize && $postMaxSize < $this->maxTotalBytes) {
            $warnings[] = 'PHP post_max_size is below ARCHIVER_MAX_TOTAL_BYTES.';
        }
    }

    private function iniBytes(string $key): ?int
    {
        $value = ini_get($key);
        if (false === $value || '' === $value) {
            return null;
        }

        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
