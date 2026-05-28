<?php

namespace App\Archive;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ZipArchiveBuilder
{
    public function __construct(private ArchiveEntryNamer $entryNamer)
    {
    }

    /**
     * Creates the ZIP archive at the provided path.
     */
    public function build(ArchiveCreationRequest $request, string $zipPath): void
    {
        $zip = new \ZipArchive();
        $status = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $status) {
            throw new \RuntimeException(sprintf('Could not create ZIP archive. ZipArchive status: %s.', $status));
        }

        $buildException = null;

        try {
            if ($request->isPasswordProtected()) {
                $this->assertEncryptionSupport($request->encryptionMode);
                $this->setPassword($zip, $request->password);
            }

            $entryNames = $this->entryNamer->namesForUploads($request->files);
            foreach ($request->files as $index => $file) {
                $this->addFile($zip, $file, $entryNames[$index]);

                if ($request->isPasswordProtected()) {
                    $this->encryptEntry($zip, $entryNames[$index], $request->encryptionMode, $request->password);
                }
            }
        } catch (\Throwable $exception) {
            $buildException = $exception;

            throw $exception;
        } finally {
            $closed = $zip->close();
            if (!$closed && null === $buildException) {
                throw new \RuntimeException('Could not finalize the ZIP archive.');
            }
        }
    }

    private function assertEncryptionSupport(ArchiveEncryptionMode $mode): void
    {
        if (ArchiveEncryptionMode::Aes256 === $mode && !\defined('ZipArchive::EM_AES_256')) {
            throw new \RuntimeException('This PHP ZIP extension does not support AES-256 ZIP encryption.');
        }
    }

    private function setPassword(\ZipArchive $zip, #[\SensitiveParameter] ?string $password): void
    {
        if (null === $password || '' === $password) {
            throw new ArchiveValidationException('Enter a password or disable password protection.');
        }

        if (!$zip->setPassword($password)) {
            throw new \RuntimeException('Could not configure the ZIP archive password.');
        }
    }

    private function addFile(\ZipArchive $zip, UploadedFile $file, string $entryName): void
    {
        $path = $file->getPathname();
        if (!$zip->addFile($path, $entryName)) {
            throw new \RuntimeException(sprintf('Could not add "%s" to the ZIP archive.', $file->getClientOriginalName()));
        }
    }

    private function encryptEntry(
        \ZipArchive $zip,
        string $entryName,
        ArchiveEncryptionMode $mode,
        #[\SensitiveParameter]
        ?string $password,
    ): void {
        if (!$zip->setEncryptionName($entryName, $mode->zipArchiveMethod(), $password)) {
            throw new \RuntimeException(sprintf('Could not encrypt "%s" in the ZIP archive.', $entryName));
        }
    }
}
