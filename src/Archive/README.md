# Archive domain

The archive domain validates uploaded files, chooses the requested ZIP encryption mode, creates safe ZIP entry names, and writes the generated archive into a caller-owned temporary workspace.

## Entry points

- `ArchiveCreationService` coordinates validation and ZIP creation.
- `ZipArchiveBuilder` is the only class that talks directly to PHP's `ZipArchive` API.
- `ArchiveEntryNamer` converts browser-provided file names into safe, unique archive entry names.
- `ArchiveEncryptionMode` maps API form values to supported ZIP encryption modes.

## Encryption modes

The API accepts:

- `zipcrypto` — default password protection using `ZipArchive::EM_TRAD_PKWARE`. Opens natively in Windows Explorer and virtually every ZIP tool, but is weaker than AES and should be labeled that way in user-facing UI.
- `aes256` — stronger, opt-in password protection using `ZipArchive::EM_AES_256`; requires a modern ZIP tool (7-Zip, WinZip, Keka, etc.).
- `none` — internal unencrypted mode used when password protection is disabled.

Every encrypted file entry must call `ZipArchive::setEncryptionName()` after being added to the archive. Calling `setPassword()` alone is not enough to encrypt newly-created archives.

## Validation

`ArchiveLimits` enforces configured file count and size limits before ZIP creation. Controllers should translate `ArchiveValidationException` into a user-facing `400` response.
