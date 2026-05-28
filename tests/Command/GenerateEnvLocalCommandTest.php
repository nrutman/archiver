<?php

namespace App\Tests\Command;

use App\Command\GenerateEnvLocalCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateEnvLocalCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'archiver-env-local-test-'.bin2hex(random_bytes(8));
        mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $path = $this->projectDir.\DIRECTORY_SEPARATOR.'.env.local';
        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }
    }

    public function testItGeneratesProductionEnvLocalDefaults(): void
    {
        $tester = new CommandTester(new GenerateEnvLocalCommand($this->projectDir));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Generated', $tester->getDisplay());

        $contents = $this->envLocalContents();
        self::assertStringContainsString('APP_ENV=prod', $contents);
        self::assertStringContainsString('APP_DEBUG=0', $contents);
        self::assertMatchesRegularExpression('/^APP_SECRET=[a-f0-9]{64}$/m', $contents);
        self::assertStringNotContainsString('DEFAULT_URI=', $contents);
    }

    public function testItRefusesToOverwriteExistingEnvLocalWithoutForce(): void
    {
        file_put_contents($this->projectDir.\DIRECTORY_SEPARATOR.'.env.local', "APP_ENV=dev\n");
        $tester = new CommandTester(new GenerateEnvLocalCommand($this->projectDir));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("APP_ENV=dev\n", $this->envLocalContents());
    }

    public function testItCanForceOverwriteWithCustomValues(): void
    {
        file_put_contents($this->projectDir.\DIRECTORY_SEPARATOR.'.env.local', "APP_ENV=prod\n");
        $tester = new CommandTester(new GenerateEnvLocalCommand($this->projectDir));

        $exitCode = $tester->execute([
            '--app-env' => 'dev',
            '--app-debug' => '1',
            '--default-uri' => 'https://archiver.example.test',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $contents = $this->envLocalContents();
        self::assertStringContainsString('APP_ENV=dev', $contents);
        self::assertStringContainsString('APP_DEBUG=1', $contents);
        self::assertStringContainsString('DEFAULT_URI=https://archiver.example.test', $contents);
    }

    public function testItRejectsEmptyEnvironment(): void
    {
        $tester = new CommandTester(new GenerateEnvLocalCommand($this->projectDir));

        $exitCode = $tester->execute(['--app-env' => '']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertFileDoesNotExist($this->projectDir.\DIRECTORY_SEPARATOR.'.env.local');
    }

    public function testItRejectsInvalidDebugValue(): void
    {
        $tester = new CommandTester(new GenerateEnvLocalCommand($this->projectDir));

        $exitCode = $tester->execute(['--app-debug' => 'sometimes']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertFileDoesNotExist($this->projectDir.\DIRECTORY_SEPARATOR.'.env.local');
    }

    private function envLocalContents(): string
    {
        $contents = file_get_contents($this->projectDir.\DIRECTORY_SEPARATOR.'.env.local');
        self::assertIsString($contents);

        return $contents;
    }
}
