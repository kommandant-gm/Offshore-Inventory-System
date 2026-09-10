# Certificate image attachments

Run `php artisan migrate --force` and `npm run build` when deploying this feature. Existing imported certificates remain valid and can receive images through Edit. One attachment is supported per certificate: PDF, JPG/JPEG, PNG or WebP, up to 5 MB. Images are limited to 4096 pixels per dimension; PDFs have no image-dimension limit. PDFs open through the same private authenticated endpoint and can also be downloaded. Existing image metadata columns and routes are reused for compatibility; no additional migration is required for PDF support. SVG and other file types are rejected. At most 10 new files / 20 MB total can be submitted in one save.

The dedicated `certificates` storage disk is private. Its default root is `storage/app/private/miri/certificates`. Files are stored below branch and certificate IDs using generated filenames. The database stores the path, original display filename, detected MIME type, bytes, uploader and upload time. Paths are hidden from frontend record serialization.

Preview and download use an authenticated controller which checks Miri branch access, inventory read permission, and certificate ownership. Responses disable caching and MIME sniffing. No public storage link is needed; do not expose this directory through the web server. File contents are not committed to Git.

Configure `CERTIFICATE_STORAGE_ROOT` in production if deployments replace the application directory. Use an absolute directory on a persistent disk outside the public web root, writable by the PHP application account. For example:

```env
CERTIFICATE_STORAGE_ROOT=/srv/dayang-shared/certificates
```

For Windows/IIS use an equivalent persistent absolute Windows path with suitable application-pool permissions. Back up this directory and the database together and test restoration. All app instances must share this storage. This implementation uses private local storage; deploying with multiple servers requires a shared persistent volume or a future object-storage adapter.

Configure the web-server/PHP request limits to accept the intended workload. Suggested PHP settings: `upload_max_filesize=5M`, `post_max_size=25M`, `max_file_uploads=20`. Match IIS/proxy request limits to at least 25 MB and restart/reload PHP after changes. The application validates 5 MB per image and 20 MB of new files per save. Save additional images in a later edit.

Updating metadata preserves certificate IDs and existing attachments. Removal is explicit. Uploaded files are cleaned up if the database transaction fails; old files are removed only after a successful save. Failed file cleanup is logged for administrator review. A process interruption can still leave an unreferenced file; review storage against database references before removing any orphan files. Image replacement/removal is permanent outside backups. Normal inventory audit records include before/after certificate metadata.

Tests: `php artisan test --filter=MiriCertificateImageTest`. Includes access isolation, imported certificate updates, replacement/removal, validation and failure cleanup. No images are appended automatically to registration PDFs; attachments are available from certificate Preview/Download.
