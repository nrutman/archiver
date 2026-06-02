<?php

namespace App\Command;

use App\Archive\ArchiveCreationRequest;
use App\Archive\ArchiveCreationService;
use App\Archive\ArchiveEncryptionMode;
use App\Archive\ArchiveValidationException;
use App\TemporaryStorage\TemporaryWorkspaceFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsCommand(
    name: 'app:archive:create',
    description: 'Creates a ZIP archive from local files. Bypasses the HTTP API and its rate limit.',
)]
final class CreateArchiveCommand extends Command
{
    public function __construct(
        private readonly ArchiveCreationService $archiveCreationService,
        private readonly TemporaryWorkspaceFactory $workspaceFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'files',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'One or more files to add to the archive.',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Path where the resulting ZIP file should be written.',
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'Password to encrypt the archive with. Omit to create an unencrypted archive.',
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Encryption mode when a password is provided: zipcrypto (default) or aes256.',
                'zipcrypto',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite the output file if it already exists.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputPath = $this->resolveOutputPath($input, $io);
        if (null === $outputPath) {
            return Command::INVALID;
        }

        try {
            $uploadedFiles = $this->buildUploadedFiles($input);
            $archiveRequest = $this->buildRequest($input, $uploadedFiles);
        } catch (\InvalidArgumentException|ArchiveValidationException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $workspace = $this->workspaceFactory->create();

        try {
            $zipPath = $this->archiveCreationService->createArchive($archiveRequest, $workspace);

            try {
                (new Filesystem())->copy($zipPath, $outputPath, overwriteNewerFiles: true);
            } catch (IOException $exception) {
                // Surface the underlying OS reason (permission denied, no
                // space, missing parent directory) rather than a generic
                // "could not write" message; that detail is what the
                // operator needs to fix the host.
                $io->error(sprintf('Could not write the archive to "%s": %s', $outputPath, $exception->getMessage()));

                return Command::FAILURE;
            }
        } catch (ArchiveValidationException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } finally {
            $workspace->close();
        }

        $io->success(sprintf('Wrote archive to %s (%s).', $outputPath, Helper::formatMemory((int) filesize($outputPath))));

        return Command::SUCCESS;
    }

    private function resolveOutputPath(InputInterface $input, SymfonyStyle $io): ?string
    {
        $output = $input->getOption('output');
        if (!is_string($output) || '' === trim($output)) {
            $io->error('The --output option is required.');

            return null;
        }

        if (file_exists($output) && !$input->getOption('force')) {
            $io->error(sprintf('Refusing to overwrite "%s". Pass --force to replace it.', $output));

            return null;
        }

        return $output;
    }

    /**
     * @return list<UploadedFile>
     */
    private function buildUploadedFiles(InputInterface $input): array
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('files');

        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not a regular file.', $path));
            }

            if (!is_readable($path)) {
                throw new \InvalidArgumentException(sprintf('File "%s" is not readable.', $path));
            }

            // test = true skips the move-uploaded-file check so we can reuse the
            // same ArchiveCreationService path the controller uses without
            // staging files through a fake upload directory.
            $files[] = new UploadedFile($path, basename($path), null, null, true);
        }

        return $files;
    }

    /**
     * @param list<UploadedFile> $files
     */
    private function buildRequest(InputInterface $input, array $files): ArchiveCreationRequest
    {
        $password = $input->getOption('password');
        $hasPassword = is_string($password) && '' !== $password;

        $mode = $hasPassword
            ? ArchiveEncryptionMode::fromFormValue((string) $input->getOption('mode'))
            : ArchiveEncryptionMode::None;

        return new ArchiveCreationRequest($files, $mode, $hasPassword ? $password : null);
    }
}
