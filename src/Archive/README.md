# Archive domain

The archive domain validates uploaded files, chooses the requested ZIP encryption mode, creates safe ZIP entry names, and writes the generated archive into a caller-owned temporary workspace.

## Entry points

- `ArchiveCreationService` coordinates validation and ZIP creation.
- `ZipArchiveBuilder` is the only class that talks directly to PHP's `ZipArchive` API.
- `ArchiveEntryNamer` converts browser-provided file names into safe, unique archive entry names.
- `ArchiveEncryptionMode` maps API form values to supported ZIP encryption modes.

## Encryption modes

The API accepts:

- `aes256` — strong default password protection using `ZipArchive::EM_AES_256`.
- `zipcrypto` — Windows Explorer-compatible password protection using `ZipArchive::EM_TRAD_PKWARE`; this is weaker and should be labeled that way in user-facing UI.
- `none` — internal unencrypted mode used when password protection is disabled.

Every encrypted file entry must call `ZipArchive::setEncryptionName()` after being added to the archive. Calling `setPassword()` alone is not enough to encrypt newly-created archives.

## Validation

`ArchiveLimits` enforces configured file count and size limits before ZIP creation. Controllers should translate `ArchiveValidationException` into a user-facing `400` response.
