<?php

namespace App\Archive;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ArchiveLimits
{
    public function __construct(
        private int $maxFiles,
        private int $maxSingleFileBytes,
        private int $maxTotalBytes,
    ) {
    }

    /**
     * Validates uploaded files against configured archive limits.
     *
     * @param list<UploadedFile> $files
     */
    public function validateUploads(array $files): void
    {
        if ([] === $files) {
            throw new ArchiveValidationException('Add at least one file before creating an archive.');
        }

        if (count($files) > $this->maxFiles) {
            throw new ArchiveValidationException(sprintf('Upload at most %d files.', $this->maxFiles));
        }

        $totalBytes = 0;
        foreach ($files as $file) {
            if (!$file->isValid()) {
                throw new ArchiveValidationException('One of the selected files did not upload successfully.');
            }

            $size = $file->getSize();
            if (false === $size) {
                throw new ArchiveValidationException('Could not determine the size of one of the selected files.');
            }

            if ($size > $this->maxSingleFileBytes) {
                throw new ArchiveValidationException('One of the selected files is larger than the per-file limit.');
            }

            $totalBytes += $size;
            if ($totalBytes > $this->maxTotalBytes) {
                throw new ArchiveValidationException('The selected files are larger than the total archive limit.');
            }
        }
    }
}
