<?php

namespace App\Archive;

final readonly class HttpUploadLimits
{
    public function __construct(private int $maxFiles)
    {
    }

    /**
     * Returns the lower of the configured archive count and PHP's upload count.
     */
    public function maxFiles(): int
    {
        return min($this->maxFiles, max(0, (int) ini_get('max_file_uploads')));
    }
}
