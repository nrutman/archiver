<?php

namespace App\Archive;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ArchiveEntryNamer
{
    /**
     * Creates safe, unique ZIP entry names for uploaded files.
     *
     * @param list<UploadedFile> $files
     *
     * @return array<int, non-empty-string>
     */
    public function namesForUploads(array $files): array
    {
        $usedNames = [];
        $entryNames = [];

        foreach ($files as $index => $file) {
            $baseName = $this->sanitize($file->getClientOriginalName());
            $entryName = $this->deduplicate($baseName, $usedNames);
            $usedNames[strtolower($entryName)] = true;
            $entryNames[$index] = $entryName;
        }

        return $entryNames;
    }

    /**
     * @return non-empty-string
     */
    private function sanitize(string $clientName): string
    {
        $name = basename(str_replace('\\', '/', $clientName));
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        $name = trim($name, " .-_\t\n\r\0\x0B");
        $name = preg_replace('/-+(\.[A-Za-z0-9]+)$/', '$1', $name) ?? $name;

        if ('' === $name) {
            return 'file';
        }

        return $name;
    }

    /**
     * @param array<string, true> $usedNames
     *
     * @return non-empty-string
     */
    private function deduplicate(string $name, array $usedNames): string
    {
        if (!isset($usedNames[strtolower($name)])) {
            return $name;
        }

        $extension = pathinfo($name, \PATHINFO_EXTENSION);
        $stem = pathinfo($name, \PATHINFO_FILENAME);
        if ('' === $stem) {
            $stem = 'file';
        }

        for ($counter = 2;; ++$counter) {
            $candidate = '' === $extension
                ? sprintf('%s (%d)', $stem, $counter)
                : sprintf('%s (%d).%s', $stem, $counter, $extension);

            if (!isset($usedNames[strtolower($candidate)])) {
                return $candidate;
            }
        }
    }
}
