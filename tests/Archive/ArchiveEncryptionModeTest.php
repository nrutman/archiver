<?php

namespace App\Tests\Archive;

use App\Archive\ArchiveEncryptionMode;
use App\Archive\ArchiveValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchiveEncryptionModeTest extends TestCase
{
    #[DataProvider('emptyFormValueProvider')]
    public function testItDefaultsToZipCryptoForEmptyValues(?string $value): void
    {
        // ZipCrypto is the default so password-protected archives open
        // natively in Windows Explorer when no mode is supplied.
        self::assertSame(ArchiveEncryptionMode::ZipCrypto, ArchiveEncryptionMode::fromFormValue($value));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function emptyFormValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
    }

    #[DataProvider('explicitFormValueProvider')]
    public function testItMapsExplicitValuesCaseInsensitively(string $value, ArchiveEncryptionMode $expected): void
    {
        self::assertSame($expected, ArchiveEncryptionMode::fromFormValue($value));
    }

    /**
     * @return iterable<string, array{string, ArchiveEncryptionMode}>
     */
    public static function explicitFormValueProvider(): iterable
    {
        yield 'aes256' => ['aes256', ArchiveEncryptionMode::Aes256];
        yield 'zipcrypto' => ['zipcrypto', ArchiveEncryptionMode::ZipCrypto];
        yield 'mixed case is normalized' => ['AES256', ArchiveEncryptionMode::Aes256];
        yield 'surrounding whitespace is trimmed' => ['  zipcrypto  ', ArchiveEncryptionMode::ZipCrypto];
    }

    public function testItRejectsUnsupportedValues(): void
    {
        $this->expectException(ArchiveValidationException::class);

        ArchiveEncryptionMode::fromFormValue('bogus');
    }
}
