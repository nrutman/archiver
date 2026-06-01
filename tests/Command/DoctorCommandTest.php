<?php

namespace App\Tests\Command;

use App\Command\DoctorCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class DoctorCommandTest extends KernelTestCase
{
    public function testItReportsSuccessfulRuntimeChecks(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:doctor');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Runtime checks passed', $tester->getDisplay());
    }

    public function testItFailsWhenTheRateLimiterCachePoolCannotStoreState(): void
    {
        // Pins the consume(1) probe: a limiter whose storage write throws
        // must surface as a doctor failure. A regression to consume(0)
        // would short-circuit before storage->save() and silently pass.
        self::bootKernel();

        // Stubs, not mocks — we only want canned return values, no call
        // assertions. createStub() silences PHPUnit 12's "no expectations
        // configured" notice that would otherwise trip failOnNotice=true.
        $failingLimiter = $this->createStub(LimiterInterface::class);
        $failingLimiter
            ->method('consume')
            ->willThrowException(new \RuntimeException('simulated cache pool write failure'));

        $failingFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $failingFactory->method('create')->willReturn($failingLimiter);

        $command = new DoctorCommand(
            tempRoot: self::getContainer()->getParameter('archiver.temp_root'),
            maxFiles: (int) self::getContainer()->getParameter('archiver.max_files'),
            maxSingleFileBytes: (int) self::getContainer()->getParameter('archiver.max_single_file_bytes'),
            maxTotalBytes: (int) self::getContainer()->getParameter('archiver.max_total_bytes'),
            archiveCreateLimiter: $failingFactory,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            'Could not exercise the archive_create rate limiter',
            self::normalizeWhitespace($tester->getDisplay()),
        );
        self::assertStringContainsString(
            'simulated cache pool write failure',
            self::normalizeWhitespace($tester->getDisplay()),
        );
    }

    /**
     * SymfonyStyle wraps error boxes to the terminal width, which can split
     * substring matches across a line break on narrower CI terminals.
     */
    private static function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
