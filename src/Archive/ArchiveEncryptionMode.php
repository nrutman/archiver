<?php

namespace App\Archive;

enum ArchiveEncryptionMode: string
{
    case None = 'none';
    case Aes256 = 'aes256';
    case ZipCrypto = 'zipcrypto';

    /**
     * Creates an encryption mode from the API form value.
     */
    public static function fromFormValue(?string $value): self
    {
        if (null === $value || '' === trim($value)) {
            return self::Aes256;
        }

        return match (strtolower(trim($value))) {
            'aes256' => self::Aes256,
            'zipcrypto' => self::ZipCrypto,
            default => throw new ArchiveValidationException('Unsupported encryption mode.'),
        };
    }

    /**
     * Returns the ZipArchive encryption constant for encrypted modes.
     */
    public function zipArchiveMethod(): int
    {
        return match ($this) {
            self::Aes256 => \ZipArchive::EM_AES_256,
            self::ZipCrypto => \ZipArchive::EM_TRAD_PKWARE,
            self::None => throw new \LogicException('No ZIP encryption method exists for unencrypted archives.'),
        };
    }
}
