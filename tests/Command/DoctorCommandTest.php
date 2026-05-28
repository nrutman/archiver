<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

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
}
