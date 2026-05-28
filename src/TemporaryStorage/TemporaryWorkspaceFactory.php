<?php

namespace App\TemporaryStorage;

final readonly class TemporaryWorkspaceFactory
{
    public function __construct(
        private string $rootDirectory,
        private TemporaryWorkspaceRemover $remover,
    ) {
    }

    /**
     * Creates a private locked workspace for one archive request.
     */
    public function create(): TemporaryWorkspace
    {
        $this->ensureDirectory($this->rootDirectory);

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $directory = $this->rootDirectory.\DIRECTORY_SEPARATOR.bin2hex(random_bytes(16));
            if (@mkdir($directory, 0700)) {
                $lockHandle = fopen($directory.\DIRECTORY_SEPARATOR.'.lock', 'c+');
                if (false === $lockHandle) {
                    $this->remover->remove($directory);
                    throw new \RuntimeException('Could not create a temporary workspace lock.');
                }

                if (!flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
                    fclose($lockHandle);
                    $this->remover->remove($directory);
                    throw new \RuntimeException('Could not lock the temporary workspace.');
                }

                return new TemporaryWorkspace($directory, $lockHandle, $this->remover);
            }
        }

        throw new \RuntimeException('Could not create a unique temporary workspace.');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create temporary archive directory "%s".', $directory));
        }
    }
}
