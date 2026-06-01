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
    protected function setUp(): void
    {
        parent::setUp();

        // Each test owns a fresh rate-limit window. The cache.rate_limiter
        // pool persists across tests inside one PHPUnit run, so without this
        // the 6th request to 127.0.0.1 anywhere in this class would 429.
        self::bootKernel();
        /** @var \Psr\Cache\CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get('cache.rate_limiter');
        $pool->clear();
        self::ensureKernelShutdown();
    }

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

    public function testItRateLimitsRepeatedRequestsFromTheSameIp(): void
    {
        $client = static::createClient();
        $remoteIp = '203.0.113.42';

        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('file-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => $remoteIp],
            );
            self::assertResponseIsSuccessful(sprintf('Request %d should be under the limit.', $i + 1));
        }

        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('over-limit.txt', 'body')]],
            ['REMOTE_ADDR' => $remoteIp],
        );

        $response = $client->getResponse();
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('rate_limited', $body['error']['code'] ?? null);
        self::assertIsString($body['error']['message'] ?? null);
        self::assertGreaterThan(0, $body['error']['retryAfterSeconds'] ?? 0);
        self::assertNotNull($response->headers->get('Retry-After'));
        self::assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        self::assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        self::assertMatchesRegularExpression('/^\d+$/', (string) $response->headers->get('X-RateLimit-Reset'));
        self::assertGreaterThan(time(), (int) $response->headers->get('X-RateLimit-Reset'));
        // Header and JSON body must agree on the retry hint so a future
        // refactor cannot let them diverge (e.g. seconds vs milliseconds).
        self::assertSame(
            (string) $body['error']['retryAfterSeconds'],
            $response->headers->get('Retry-After'),
        );
    }

    public function testItCollapsesIpv6AddressesInTheSameSixtyFourToOneBucket(): void
    {
        // Two distinct /128 addresses inside the same /64 must share a
        // bucket so a client cannot rotate addresses inside its allocation
        // to bypass the limit.
        $client = static::createClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('a-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '2001:db8:1:1::1'],
            );
            self::assertResponseIsSuccessful();
        }

        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('over.txt', 'body')]],
            ['REMOTE_ADDR' => '2001:db8:1:1::beef'],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testItRateLimitsIpv6AddressesInDifferentSixtyFoursIndependently(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('a-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '2001:db8:1:1::1'],
            );
            self::assertResponseIsSuccessful();
        }

        // A client in a different /64 must not inherit the first /64's window.
        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('b.txt', 'body')]],
            ['REMOTE_ADDR' => '2001:db8:2:2::1'],
        );
        self::assertResponseIsSuccessful('Different /64 prefixes must have independent rate-limit windows.');
    }

    public function testItUnmapsIpv4MappedIpv6AddressesToTheirIpv4Bucket(): void
    {
        // A client seen sometimes as plain IPv4 and sometimes as the
        // IPv4-mapped IPv6 form (`::ffff:a.b.c.d`, common on dual-stack
        // listeners) must hit the same bucket; without unmapping, the
        // mapped form would also collapse every IPv4 client into one
        // shared global bucket because the upper 8 bytes are all zero.
        $client = static::createClient();

        for ($i = 0; $i < 3; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('a-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '203.0.113.42'],
            );
            self::assertResponseIsSuccessful();
        }

        for ($i = 0; $i < 2; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('m-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '::ffff:203.0.113.42'],
            );
            self::assertResponseIsSuccessful();
        }

        // 3 + 2 = 5 — the next request from either form should trip the limit.
        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('over.txt', 'body')]],
            ['REMOTE_ADDR' => '203.0.113.42'],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testItKeepsDistinctIpv4MappedIpv6AddressesInSeparateBuckets(): void
    {
        // Regression guard for the cycle-2 fix: before unmapping was added,
        // every `::ffff:*` address collapsed to a single shared key
        // because the upper 8 bytes are all zero. This test fails under
        // that bug because the second client's first request would be a
        // 429 instead of the expected success.
        $client = static::createClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('a-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '::ffff:198.51.100.10'],
            );
            self::assertResponseIsSuccessful();
        }

        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('b.txt', 'body')]],
            ['REMOTE_ADDR' => '::ffff:198.51.100.20'],
        );
        self::assertResponseIsSuccessful('Distinct IPv4-mapped IPv6 addresses must not share a bucket.');
    }

    public function testValidationErrorsStillConsumeTheRateLimitWindow(): void
    {
        // The controller consumes one token before validating, so an attacker
        // cannot blast invalid requests to probe the endpoint without being
        // throttled. Keep this property pinned: five rejected requests must
        // be enough to exhaust the window for a subsequent valid request.
        $client = static::createClient();
        $remoteIp = '203.0.113.99';

        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                [],
                ['REMOTE_ADDR' => $remoteIp],
            );
            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        }

        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('valid.txt', 'body')]],
            ['REMOTE_ADDR' => $remoteIp],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testItRateLimitsIndependentlyPerClientIp(): void
    {
        $client = static::createClient();

        // Burn through the limit for one IP.
        for ($i = 0; $i < 5; ++$i) {
            $client->request(
                'POST',
                '/api/archives',
                ['passwordEnabled' => 'false'],
                ['files' => [$this->uploadedFile(sprintf('a-%d.txt', $i), 'body')]],
                ['REMOTE_ADDR' => '198.51.100.10'],
            );
            self::assertResponseIsSuccessful();
        }

        // A different IP should still be allowed through.
        $client->request(
            'POST',
            '/api/archives',
            ['passwordEnabled' => 'false'],
            ['files' => [$this->uploadedFile('b.txt', 'body')]],
            ['REMOTE_ADDR' => '198.51.100.20'],
        );
        self::assertResponseIsSuccessful('A separate IP must not inherit another client\'s window.');
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
