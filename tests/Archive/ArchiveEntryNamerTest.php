<?php

namespace App\Tests\Archive;

use App\Archive\ArchiveEntryNamer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ArchiveEntryNamerTest extends TestCase
{
    public function testItSanitizesUnsafeNamesAndDeduplicatesEntries(): void
    {
        $directory = sys_get_temp_dir().'/archiver-entry-namer-'.bin2hex(random_bytes(6));
        mkdir($directory);

        try {
            $filePath = $directory.'/upload.txt';
            file_put_contents($filePath, 'content');

            $files = [
                new UploadedFile($filePath, '../report?.pdf', null, null, true),
                new UploadedFile($filePath, 'report-.pdf', null, null, true),
                new UploadedFile($filePath, "\0\0", null, null, true),
            ];

            self::assertSame([
                'report.pdf',
                'report (2).pdf',
                'file',
            ], (new ArchiveEntryNamer())->namesForUploads($files));
        } finally {
            @unlink($directory.'/upload.txt');
            @rmdir($directory);
        }
    }
}
