<?php

namespace App\TemporaryStorage;

final class TemporaryWorkspace
{
    /** @var resource|null */
    private $lockHandle;
    private bool $closed = false;

    /**
     * @param resource $lockHandle
     */
    public function __construct(
        private readonly string $directory,
        $lockHandle,
        private readonly TemporaryWorkspaceRemover $remover,
    ) {
        $this->lockHandle = $lockHandle;
    }

    /**
     * Returns an absolute path inside this workspace.
     */
    public function path(string $filename): string
    {
        $filename = ltrim($filename, '/\\');

        return $this->directory.\DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Removes the workspace and releases its lock.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        try {
            $this->remover->remove($this->directory);
        } finally {
            if (null !== $this->lockHandle) {
                flock($this->lockHandle, \LOCK_UN);
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
