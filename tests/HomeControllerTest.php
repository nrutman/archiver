<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePageServesReactMountPoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        self::assertSelectorExists('#archiver-root');
        self::assertSelectorExists(sprintf('#archiver-root[data-max-files="%d"]', min(25, (int) ini_get('max_file_uploads'))));
    }
}
