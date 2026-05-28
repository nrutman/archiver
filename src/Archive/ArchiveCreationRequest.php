<?php

namespace App\Archive;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ArchiveCreationRequest
{
    /**
     * @param list<UploadedFile> $files
     */
    public function __construct(
        public array $files,
        public ArchiveEncryptionMode $encryptionMode,
        #[\SensitiveParameter]
        public ?string $password,
    ) {
    }

    /**
     * Returns true when archive entries should be encrypted.
     */
    public function isPasswordProtected(): bool
    {
        return ArchiveEncryptionMode::None !== $this->encryptionMode;
    }
}
