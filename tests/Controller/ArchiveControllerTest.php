<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArchiveControllerTest extends WebTestCase
{
    public function testItCreatesAnUnencryptedArchiveFromUploadedFiles(): void
    {
        $client = static::createClient();
        $files = [
            $this->uploadedFile('alpha.txt', 'alpha'),
            $this->uploadedFile('nested/report?.txt', 'report'),
        ];

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'false',
        ], [
            'files' => $files,
        ]);

        $response = $client->getResponse();
        self::assertResponseIsSuccessful();
        self::assertSame('application/zip', $response->headers->get('Content-Type'));

        $zipPath = $this->writeResponseZip($client);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($zipPath));
            self::assertSame('alpha', $zip->getFromName('alpha.txt'));
            self::assertSame('report', $zip->getFromName('report.txt'));
            $zip->close();
        } finally {
            @unlink($zipPath);
        }
    }

    public function testItCreatesAnArchiveFromASingleScalarFileField(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'false',
        ], [
            'files' => $this->uploadedFile('single.txt', 'single'),
        ]);

        self::assertResponseIsSuccessful();
        $zipPath = $this->writeResponseZip($client);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($zipPath));
            self::assertSame('single', $zip->getFromName('single.txt'));
            $zip->close();
        } finally {
            @unlink($zipPath);
        }
    }

    #[DataProvider('encryptedArchiveProvider')]
    public function testItCreatesPasswordProtectedArchives(string $encryptionMode, int $expectedMethod): void
    {
        $client = static::createClient();
        $files = [$this->uploadedFile('secret.txt', 'classified')];

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'true',
            'encryptionMode' => $encryptionMode,
            'password' => 'correct-horse-123-battery-456-staple',
        ], [
            'files' => $files,
        ]);

        self::assertResponseIsSuccessful();
        $zipPath = $this->writeResponseZip($client);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($zipPath));
            self::assertTrue($zip->setPassword('correct-horse-123-battery-456-staple'));
            self::assertSame('classified', $zip->getFromName('secret.txt'));
            self::assertSame($expectedMethod, $zip->statName('secret.txt')['encryption_method'] ?? null);
            $zip->close();
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function encryptedArchiveProvider(): iterable
    {
        yield 'AES-256' => ['aes256', \ZipArchive::EM_AES_256];
        yield 'Windows-compatible ZipCrypto' => ['zipcrypto', \ZipArchive::EM_TRAD_PKWARE];
    }

    public function testItReturnsValidationErrorsForMissingFiles(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'false',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('Add at least one file', (string) $client->getResponse()->getContent());
    }

    public function testItReturnsValidationErrorsWhenPasswordProtectionHasNoPassword(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'true',
            'encryptionMode' => 'aes256',
        ], [
            'files' => [$this->uploadedFile('secret.txt', 'classified')],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('Enter a password', (string) $client->getResponse()->getContent());
    }

    public function testItRejectsUnencryptedModeWhenPasswordProtectionIsEnabled(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'true',
            'encryptionMode' => 'none',
            'password' => 'giant-cheetah-284-upstairs-199',
        ], [
            'files' => [$this->uploadedFile('secret.txt', 'classified')],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('Unsupported encryption mode', (string) $client->getResponse()->getContent());
    }

    public function testItReturnsValidationErrorsForInvalidUtf8FormValues(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/archives', [
            'passwordEnabled' => 'true',
            'encryptionMode' => "invalid-\xB1\x31",
            'password' => 'giant-cheetah-284-upstairs-199',
        ], [
            'files' => [$this->uploadedFile('secret.txt', 'classified')],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertJson((string) $client->getResponse()->getContent());
        self::assertStringContainsString('Unsupported encryption mode', (string) $client->getResponse()->getContent());
    }

    private function uploadedFile(string $clientName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'archiver-upload-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $clientName, 'text/plain', null, true);
    }

    private function writeResponseZip(KernelBrowser $client): string
    {
        self::assertInstanceOf(StreamedResponse::class, $client->getResponse());

        $contents = $client->getInternalResponse()->getContent();

        $zipPath = tempnam(sys_get_temp_dir(), 'archiver-response-');
        self::assertIsString($zipPath);
        file_put_contents($zipPath, $contents);

        return $zipPath;
    }
}
