# Archiver API and CLI

The Archiver frontend is a thin client over a single HTTP endpoint. The same archive creation pipeline is also exposed as a Symfony console command so scripts on the host can build archives without going through HTTP.

## HTTP API

### `POST /api/archives`

Streams a ZIP archive built from uploaded files. The request must be `multipart/form-data`.

| Field             | Type                    | Required                    | Notes                                                                                                                                                            |
| ----------------- | ----------------------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `files[]`         | file part               | yes                         | One file part per archive entry. Can be repeated; a single `files` part also works.                                                                              |
| `passwordEnabled` | string `true` / `false` | yes                         | When `false` an unencrypted archive is produced.                                                                                                                 |
| `password`        | string                  | when `passwordEnabled=true` | No null bytes, 255 characters or fewer. Send as UTF-8.                                                                                                           |
| `encryptionMode`  | `aes256` or `zipcrypto` | no                          | Defaults to `aes256` when omitted. Ignored when `passwordEnabled=false`. `zipcrypto` is provided only for compatibility with legacy Windows tools and is weaker. |

#### Success (`200 OK`)

- `Content-Type: application/zip`
- `Content-Disposition: attachment; filename=archive-YYYYMMDD-HHMMSS.zip`
- Body is the ZIP file bytes, streamed.

#### Validation error (`400 Bad Request`)

```json
{
    "error": {
        "message": "Add at least one file before creating an archive."
    }
}
```

#### Rate limited (`429 Too Many Requests`)

Each client IP is limited to **5 requests per minute** against `/api/archives` using a sliding window. When the window is exhausted the server responds:

```json
{
    "error": {
        "code": "rate_limited",
        "message": "Too many archive requests from your IP. Please wait a moment and try again.",
        "retryAfterSeconds": 37
    }
}
```

Response headers:

- `Retry-After: <seconds>`
- `X-RateLimit-Limit: 5`
- `X-RateLimit-Remaining: 0`
- `X-RateLimit-Reset: <unix-timestamp>`

Rate limit state is persisted to a filesystem cache pool (`var/cache/<env>/pools/`). No database is involved. Behind a reverse proxy or CDN, set `TRUSTED_PROXIES` so the limiter sees real client addresses rather than the proxy's; see [`docs/deployment.md`](deployment.md).

### `curl` example

```bash
curl -fSL -o archive.zip \
  -F 'files[]=@./alpha.pdf' \
  -F 'files[]=@./beta.png' \
  -F 'passwordEnabled=true' \
  -F 'encryptionMode=aes256' \
  -F 'password=correct-horse-battery-staple' \
  https://archiver.example.com/api/archives
```

On an HTTP error, `curl -fSL` exits non-zero and prints only a short curl error to stderr — the `-f`/`--fail` flag suppresses the response body. Drop `-f` (e.g. `curl -SL`) when you want to see the server's JSON error body.

## CLI: `app:archive:create`

For host-side scripting or any case where shelling out to `curl` is awkward, the same archive pipeline is available as a Symfony console command. Because it runs in-process it bypasses both the HTTP upload limits and the rate limiter.

```bash
php bin/console app:archive:create \
  --output=out.zip \
  --password=correct-horse-battery-staple \
  --mode=aes256 \
  ./alpha.pdf ./beta.png
```

Options:

| Option             | Description                                                                    |
| ------------------ | ------------------------------------------------------------------------------ |
| `--output`, `-o`   | Required. Destination path for the ZIP file.                                   |
| `--password`, `-p` | Encrypts the archive with the given password. Omit for an unencrypted archive. |
| `--mode`, `-m`     | `aes256` (default) or `zipcrypto`. Only meaningful when `--password` is set.   |
| `--force`, `-f`    | Overwrite `--output` if it already exists.                                     |

The command exits with code `0` on success, `2` on validation errors (e.g. missing or unreadable input file, unsupported encryption mode, missing `--output`, refusing to overwrite without `--force`, or input that exceeds the file-count / size limits), and `1` on unexpected failures such as the destination filesystem being unwritable.
