<?php

namespace App\Archive;

use App\TemporaryStorage\TemporaryWorkspace;

final readonly class ArchiveCreationService
{
    public function __construct(
        private ArchiveLimits $limits,
        private ZipArchiveBuilder $zipArchiveBuilder,
    ) {
    }

    /**
     * Creates an archive in the provided temporary workspace.
     *
     * The caller owns the workspace lifecycle and must close it after the response
     * has been streamed or after an exception.
     */
    public function createArchive(ArchiveCreationRequest $request, TemporaryWorkspace $workspace): string
    {
        $this->limits->validateUploads($request->files);
        $this->validatePassword($request);

        $zipPath = $workspace->path('archive.zip');
        $this->zipArchiveBuilder->build($request, $zipPath);

        return $zipPath;
    }

    private function validatePassword(ArchiveCreationRequest $request): void
    {
        if (!$request->isPasswordProtected()) {
            return;
        }

        if (null === $request->password || '' === trim($request->password)) {
            throw new ArchiveValidationException('Enter a password or disable password protection.');
        }

        if (str_contains($request->password, "\0")) {
            throw new ArchiveValidationException('Passwords cannot contain null bytes.');
        }

        if (mb_strlen($request->password) > 255) {
            throw new ArchiveValidationException('Passwords must be 255 characters or fewer.');
        }
    }
}
