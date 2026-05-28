<?php

namespace App\Tests\TemporaryStorage;

use App\TemporaryStorage\TemporaryWorkspacePurger;
use App\TemporaryStorage\TemporaryWorkspaceRemover;
use PHPUnit\Framework\TestCase;

final class TemporaryWorkspacePurgerTest extends TestCase
{
    private string $rootDirectory;

    protected function setUp(): void
    {
        $this->rootDirectory = sys_get_temp_dir().'/archiver-purger-'.bin2hex(random_bytes(6));
        mkdir($this->rootDirectory);
    }

    protected function tearDown(): void
    {
        (new TemporaryWorkspaceRemover())->remove($this->rootDirectory);
    }

    public function testItRemovesExpiredUnlockedWorkspaces(): void
    {
        $workspace = $this->workspace('expired');
        touch($workspace, time() - 7200);

        $result = $this->purger()->purge(3600);

        self::assertSame(1, $result->removed);
        self::assertDirectoryDoesNotExist($workspace);
    }

    public function testItSkipsFreshWorkspaces(): void
    {
        $workspace = $this->workspace('fresh');

        $result = $this->purger()->purge(3600);

        self::assertSame(0, $result->removed);
        self::assertSame(1, $result->skippedFresh);
        self::assertDirectoryExists($workspace);
    }

    public function testItSkipsSymlinkedWorkspaces(): void
    {
        $outsideDirectory = sys_get_temp_dir().'/archiver-purger-outside-'.bin2hex(random_bytes(6));
        mkdir($outsideDirectory);
        file_put_contents($outsideDirectory.'/keep.txt', 'keep');
        symlink($outsideDirectory, $this->rootDirectory.'/workspace-link');

        try {
            $result = $this->purger()->purge(0);

            self::assertSame(0, $result->removed);
            self::assertFileExists($outsideDirectory.'/keep.txt');
            self::assertFileExists($this->rootDirectory.'/workspace-link');
        } finally {
            @unlink($this->rootDirectory.'/workspace-link');
            (new TemporaryWorkspaceRemover())->remove($outsideDirectory);
        }
    }

    public function testItSkipsLockedWorkspaces(): void
    {
        $workspace = $this->workspace('locked');
        $readyFile = $this->rootDirectory.'/lock-ready';
        $process = proc_open([
            \PHP_BINARY,
            '-r',
            '$h = fopen($argv[1]."/.lock", "c+"); flock($h, LOCK_EX); file_put_contents($argv[2], "ready"); sleep(5);',
            $workspace,
            $readyFile,
        ], [], $pipes);
        self::assertIsResource($process);

        try {
            $deadline = time() + 3;
            while (!is_file($readyFile) && time() < $deadline) {
                usleep(10_000);
            }

            self::assertFileExists($readyFile);
            touch($workspace, time() - 7200);

            $result = $this->purger()->purge(3600);

            self::assertSame(0, $result->removed);
            self::assertSame(1, $result->skippedActive);
            self::assertDirectoryExists($workspace);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    private function purger(): TemporaryWorkspacePurger
    {
        return new TemporaryWorkspacePurger($this->rootDirectory, new TemporaryWorkspaceRemover());
    }

    private function workspace(string $name): string
    {
        $workspace = $this->rootDirectory.'/'.$name;
        mkdir($workspace);
        file_put_contents($workspace.'/archive.zip', 'placeholder');

        return $workspace;
    }
}
