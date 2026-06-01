<?php

namespace App\Tests\Command;

use App\Command\DoctorCommand;
use App\Tests\Support\NormalizesConsoleOutputTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class DoctorCommandTest extends KernelTestCase
{
    use NormalizesConsoleOutputTrait;

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
        // Drives the probe through a real SlidingWindowLimiter whose backing
        // storage throws on save(). This genuinely pins the consume(1)
        // contract: a regression to consume(0) would short-circuit before
        // storage->save() runs and would let the test pass silently with the
        // bug back in production. A stubbed LimiterInterface that throws on
        // any consume() argument would not catch that.
        self::bootKernel();

        $factory = new RateLimiterFactory(
            [
                'id' => 'doctor-probe-test',
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '1 minute',
            ],
            new class implements StorageInterface {
                public function save(LimiterStateInterface $limiterState): void
                {
                    throw new \RuntimeException('simulated cache pool write failure');
                }

                public function fetch(string $limiterStateId): ?LimiterStateInterface
                {
                    return null;
                }

                public function delete(string $limiterStateId): void
                {
                }
            },
        );

        $command = new DoctorCommand(
            tempRoot: self::getContainer()->getParameter('archiver.temp_root'),
            maxFiles: (int) self::getContainer()->getParameter('archiver.max_files'),
            maxSingleFileBytes: (int) self::getContainer()->getParameter('archiver.max_single_file_bytes'),
            maxTotalBytes: (int) self::getContainer()->getParameter('archiver.max_total_bytes'),
            archiveCreateLimiter: $factory,
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
}
