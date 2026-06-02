<?php

namespace App\Tests\Command;

use App\Tests\Support\NormalizesConsoleOutputTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateArchiveCommandTest extends KernelTestCase
{
    use NormalizesConsoleOutputTrait;

    /** @var list<string> */
    private array $workspaces = [];

    protected function tearDown(): void
    {
        // Per-test cleanup keeps temp dirs from accumulating across the run
        // and ensures a failing test's workspace is removed immediately
        // rather than waiting for process shutdown.
        foreach ($this->workspaces as $workspace) {
            $this->removeWorkspace($workspace);
        }
        $this->workspaces = [];

        parent::tearDown();
    }

    public function testItWritesAnEncryptedArchiveFromLocalFiles(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/alpha.txt';
        file_put_contents($sourcePath, 'classified-payload');

        $outputPath = $workspace.'/out.zip';
        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
            '--password' => 'correct-horse-staple-1234',
            '--mode' => 'aes256',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        self::assertFileExists($outputPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($outputPath));

        try {
            self::assertTrue($zip->setPassword('correct-horse-staple-1234'));
            self::assertSame('classified-payload', $zip->getFromName('alpha.txt'));
            self::assertSame(
                \ZipArchive::EM_AES_256,
                $zip->statName('alpha.txt')['encryption_method'] ?? null,
            );
        } finally {
            $zip->close();
        }
    }

    public function testItWritesAnUnencryptedArchiveWhenNoPasswordIsProvided(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/plain.txt';
        file_put_contents($sourcePath, 'no-secrets');

        $outputPath = $workspace.'/out.zip';
        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($outputPath));

        try {
            self::assertSame('no-secrets', $zip->getFromName('plain.txt'));
            // An unencrypted entry reports EM_NONE for its encryption method.
            self::assertSame(
                \ZipArchive::EM_NONE,
                $zip->statName('plain.txt')['encryption_method'] ?? null,
            );
        } finally {
            $zip->close();
        }
    }

    public function testItOverwritesAnExistingOutputFileWhenForceIsPassed(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/alpha.txt';
        file_put_contents($sourcePath, 'replacement-payload');

        $outputPath = $workspace.'/out.zip';
        file_put_contents($outputPath, 'placeholder-bytes');

        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        // The placeholder bytes must have been replaced by a real ZIP archive.
        self::assertNotSame('placeholder-bytes', file_get_contents($outputPath));

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($outputPath));

        try {
            self::assertSame('replacement-payload', $zip->getFromName('alpha.txt'));
        } finally {
            $zip->close();
        }
    }

    public function testItRefusesToOverwriteAnExistingOutputFileWithoutForce(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/alpha.txt';
        file_put_contents($sourcePath, 'payload');

        $outputPath = $workspace.'/existing.zip';
        file_put_contents($outputPath, 'placeholder');

        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString(
            'Refusing to overwrite',
            self::normalizeWhitespace($tester->getDisplay()),
        );
        self::assertSame('placeholder', file_get_contents($outputPath));
    }

    public function testItWritesAZipCryptoEncryptedArchive(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/legacy.txt';
        file_put_contents($sourcePath, 'compat-payload');

        $outputPath = $workspace.'/out.zip';
        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
            '--password' => 'compat-password-1234',
            '--mode' => 'zipcrypto',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($outputPath));

        try {
            self::assertTrue($zip->setPassword('compat-password-1234'));
            self::assertSame('compat-payload', $zip->getFromName('legacy.txt'));
            self::assertSame(
                \ZipArchive::EM_TRAD_PKWARE,
                $zip->statName('legacy.txt')['encryption_method'] ?? null,
            );
        } finally {
            $zip->close();
        }
    }

    public function testItDefaultsToZipCryptoWhenModeIsOmitted(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/legacy.txt';
        file_put_contents($sourcePath, 'compat-payload');

        $outputPath = $workspace.'/out.zip';
        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $outputPath,
            '--password' => 'compat-password-1234',
            // --mode intentionally omitted to exercise the default.
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($outputPath));

        try {
            self::assertTrue($zip->setPassword('compat-password-1234'));
            self::assertSame('compat-payload', $zip->getFromName('legacy.txt'));
            self::assertSame(
                \ZipArchive::EM_TRAD_PKWARE,
                $zip->statName('legacy.txt')['encryption_method'] ?? null,
            );
        } finally {
            $zip->close();
        }
    }

    public function testItErrorsOnUnsupportedEncryptionMode(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/alpha.txt';
        file_put_contents($sourcePath, 'payload');

        $exitCode = $tester->execute([
            'files' => [$sourcePath],
            '--output' => $workspace.'/out.zip',
            '--password' => 'pw',
            '--mode' => 'bogus',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString(
            'Unsupported encryption mode',
            self::normalizeWhitespace($tester->getDisplay()),
        );
    }

    public function testItErrorsWhenOutputOptionIsMissing(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $sourcePath = $workspace.'/alpha.txt';
        file_put_contents($sourcePath, 'payload');

        $exitCode = $tester->execute([
            'files' => [$sourcePath],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString(
            '--output option is required',
            self::normalizeWhitespace($tester->getDisplay()),
        );
    }

    public function testItErrorsWhenAnInputFileDoesNotExist(): void
    {
        $tester = $this->commandTester();
        $workspace = $this->makeWorkspace();

        $exitCode = $tester->execute([
            'files' => [$workspace.'/missing.txt'],
            '--output' => $workspace.'/out.zip',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('does not exist', self::normalizeWhitespace($tester->getDisplay()));
    }

    private function commandTester(): CommandTester
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:archive:create'));
    }

    private function makeWorkspace(): string
    {
        $workspace = sys_get_temp_dir().'/archiver-create-cmd-'.bin2hex(random_bytes(8));
        if (!@mkdir($workspace, 0700, true) && !is_dir($workspace)) {
            self::fail(sprintf('Could not create temporary workspace "%s".', $workspace));
        }

        $this->workspaces[] = $workspace;

        return $workspace;
    }

    private function removeWorkspace(string $workspace): void
    {
        if (!is_dir($workspace)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($workspace, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($workspace);
    }
}
