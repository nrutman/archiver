<?php

namespace App\Tests\Archive;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class HttpUploadLimitsTest extends TestCase
{
    #[DataProvider('limits')]
    public function testItUsesTheLowerFileCount(int $phpLimit, int $appLimit, int $expected): void
    {
        $process = new Process([
            \PHP_BINARY, '-d', 'max_file_uploads='.$phpLimit, '-r',
            'require "vendor/autoload.php"; echo (new App\\Archive\\HttpUploadLimits('.$appLimit.'))->maxFiles();',
        ], \dirname(__DIR__, 2));
        $process->mustRun();

        self::assertSame((string) $expected, $process->getOutput());
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function limits(): iterable
    {
        yield 'PHP lower' => [20, 25, 20];
        yield 'app lower' => [30, 25, 25];
        yield 'equal' => [25, 25, 25];
        yield 'PHP disables uploads' => [0, 25, 0];
    }
}
