<?php

namespace App\Tests\Archive;

use App\Archive\ArchiveLimits;
use App\Archive\ArchiveValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ArchiveLimitsTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }
    }

    public function testItAllowsFilesWithinConfiguredLimits(): void
    {
        $limits = new ArchiveLimits(maxFiles: 2, maxSingleFileBytes: 10, maxTotalBytes: 20);

        $limits->validateUploads([
            $this->uploadedFile('one.txt', 5),
            $this->uploadedFile('two.txt', 5),
        ]);

        self::addToAssertionCount(1);
    }

    public function testItRejectsTooManyFiles(): void
    {
        $limits = new ArchiveLimits(maxFiles: 1, maxSingleFileBytes: 10, maxTotalBytes: 20);

        $this->expectException(ArchiveValidationException::class);
        $this->expectExceptionMessage('Upload at most 1 files.');

        $limits->validateUploads([
            $this->uploadedFile('one.txt', 1),
            $this->uploadedFile('two.txt', 1),
        ]);
    }

    public function testItRejectsFilesThatExceedTheSingleFileLimit(): void
    {
        $limits = new ArchiveLimits(maxFiles: 2, maxSingleFileBytes: 5, maxTotalBytes: 20);

        $this->expectException(ArchiveValidationException::class);
        $this->expectExceptionMessage('larger than the per-file limit');

        $limits->validateUploads([$this->uploadedFile('large.txt', 6)]);
    }

    public function testItRejectsFilesThatExceedTheTotalFileLimit(): void
    {
        $limits = new ArchiveLimits(maxFiles: 3, maxSingleFileBytes: 10, maxTotalBytes: 10);

        $this->expectException(ArchiveValidationException::class);
        $this->expectExceptionMessage('larger than the total archive limit');

        $limits->validateUploads([
            $this->uploadedFile('one.txt', 6),
            $this->uploadedFile('two.txt', 6),
        ]);
    }

    private function uploadedFile(string $clientName, int $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'archiver-limit-');
        self::assertIsString($path);
        file_put_contents($path, str_repeat('a', $bytes));
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, 'text/plain', null, true);
    }
}
