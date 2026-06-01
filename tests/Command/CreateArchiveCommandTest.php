<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateArchiveCommandTest extends KernelTestCase
{
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

    /**
     * SymfonyStyle wraps error boxes to the terminal width, so a literal
     * substring assertion can fail on narrower CI terminals (e.g. "does not"
     * lands at end of line, "exist" on the next). Collapse all whitespace to
     * single spaces so the assertion only depends on the words themselves.
     */
    private static function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
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

        register_shutdown_function(static function () use ($workspace): void {
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
        });

        return $workspace;
    }
}
