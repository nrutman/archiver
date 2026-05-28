<?php

namespace App\TemporaryStorage;

final class TemporaryWorkspaceRemover
{
    /**
     * Removes a directory tree if it exists.
     */
    public function remove(string $directory): void
    {
        if (is_link($directory)) {
            unlink($directory);

            return;
        }

        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
