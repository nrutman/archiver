<?php

namespace App\TemporaryStorage;

final readonly class TemporaryWorkspacePurger
{
    public function __construct(
        private string $rootDirectory,
        private TemporaryWorkspaceRemover $remover,
    ) {
    }

    /**
     * Removes expired unlocked workspaces.
     */
    public function purge(int $ttlSeconds): PurgeResult
    {
        if (!is_dir($this->rootDirectory)) {
            return new PurgeResult(0, 0, 0);
        }

        $removed = 0;
        $skippedActive = 0;
        $skippedFresh = 0;
        $expiresBefore = time() - $ttlSeconds;

        foreach (new \DirectoryIterator($this->rootDirectory) as $workspace) {
            if ($workspace->isDot() || $workspace->isLink() || !$workspace->isDir()) {
                continue;
            }

            $workspacePath = $workspace->getPathname();
            if ($workspace->getMTime() > $expiresBefore) {
                ++$skippedFresh;
                continue;
            }

            $lockHandle = fopen($workspacePath.\DIRECTORY_SEPARATOR.'.lock', 'c+');
            if (false === $lockHandle) {
                ++$skippedActive;
                continue;
            }

            try {
                if (!flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
                    ++$skippedActive;
                    continue;
                }

                $this->remover->remove($workspacePath);
                ++$removed;
            } finally {
                flock($lockHandle, \LOCK_UN);
                fclose($lockHandle);
            }
        }

        return new PurgeResult($removed, $skippedActive, $skippedFresh);
    }
}
