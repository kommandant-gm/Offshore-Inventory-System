# Inventory performance changes (2–8)

## What changed

- Branch IDs, memberships and edit checks are cached for one HTTP request, keyed by user. Session branch selection is checked on every call. Access updates clear the request cache; nothing is cached across HTTP requests.
- Major Equipment and Rentals dashboards load separately. Summary, expiry, rental distribution and timeline counts use database aggregates. Only the eight recent/upcoming rental rows are hydrated, not the entire register.
- Movement uses a branch-scoped SQL union, database filtering and 10/25/50-row pages. Summary cards remain totals for the whole branch. COG item search returns at most 25 matches; selected records remain visible when searching again. Record IDs distinguish duplicate tags.
- Settings eager-loads employee branch memberships.
- CSV confirmation stages a private file and queues an import; users receive a progress URL. Category lookups run once per category and certificate inserts run in batches. Equipment, certificates, import completion and audit are committed together. Existing duplicate-file protection remains; no automatic repeat imports. Duplicate tags are still imported and highlighted.
- Certificate image previews are generated once at a maximum of 480 pixels, privately stored beside originals, then reused. Existing imported attachments need no migration/backfill. Preview requests recheck authorization before returning content or HTTP 304. Image previews are lazy loaded. Original images/PDFs remain unchanged, protected and downloadable. Preview files are deleted with replaced/removed originals. PDFs use a lightweight link tile rather than rendering pages.
- Checkout/check-in email is queued and logged as pending, then sent only after transport succeeds, or failed after retries. Superseded signing links are skipped. Supervisor mail notifications are also queued after database commit.

## Required production rollout

Do not deploy the queue changes without workers. Code changes alone do not start them.

1. Back up the database and persistent private storage; deploy code and build frontend assets (`npm run build`).
2. Run `php artisan migrate --force` for `miri_import_tasks` and any earlier pending migrations after reviewing them.
3. Set `QUEUE_CONNECTION=database` (or your existing asynchronous driver), not `sync`. Ensure the standard `jobs` and `failed_jobs` tables exist. The new `imports` connection is a separate database queue with a 900-second reservation; import jobs allow 600 seconds.
4. Run two separately supervised workers as services, using the deployed application's working directory:

   ```text
   php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=60
   php artisan queue:work imports --queue=imports --sleep=3 --tries=1 --timeout=600
   ```

   Keep them running with the host's service manager (e.g. a Windows service on IIS, Supervisor/systemd on Linux). Linux requires PCNTL for Laravel timeouts; Windows needs service-manager monitoring/restarts because PHP PCNTL is unavailable. Do not allow the import worker's lifetime to exceed the 900-second queue reservation. A normal 1,762-row Cargo import is far smaller than this limit; investigate tasks stuck in processing before retrying. Separate workers keep imports from delaying email.
5. Web and worker processes must use the same database, app URL, mail configuration, and persistent `storage/app/private` files. They both need private storage read/write access. Certificate previews require PHP GD; without GD the route safely falls back to the original image (no bandwidth saving). Never place private files under `public/` or a public storage link.
6. Clear/rebuild application configuration caches as appropriate, then `php artisan queue:restart` so workers pick up new code/configuration. A service manager must restart them after exit.

## Verification and operations

- Open both dashboard tabs; check counts, expiry buckets and cargo/machinery filters.
- Search movement on later pages; switch filters and page sizes. Create a COG using a record beyond the first 25 and select both rental/equipment lines.
- Import a small previewed CSV: queued → processing → completed. Its progress link is available under Your recent imports. Verify duplicate tags/warnings and try the exact same file again (must not duplicate records).
- Check that imported equipment/certificates appear together and the Miri log records completion. Failed validation must leave no imported rows. Status pages are limited to their submitting user and branch.
- Trigger an authorized checkout/check-in; verify pending → sent in Email Activity and actual receipt. Never use test/synthetic emails against real recipients without permission.
- Inspect `php artisan queue:failed` and application logs for failures; retry email jobs only after resolving the cause. For failed import tasks, review the failure and re-upload/preview the file rather than retrying the old job (staged files are removed). After abrupt worker termination, reconcile import task status and the completed-import hash before resubmitting. Retain status/audit history. Staged files normally disappear on success/failure; administrators should inspect orphaned staged files after a crash before removing them.
- Original certificate links/downloads must still work. Check unauthorized requests (including a request carrying an ETag) do not return image content. Replacing/removing an attachment must also remove its cached preview.

These changes reduce known work/payloads; they are not a measured production latency guarantee. The normalized duplicate-tag/index improvement from review item 1 is intentionally not included in this request.

## Local verification

The targeted performance, Cargo, certificate attachment and Cargo dashboard suites passed: 22 tests, 416 assertions. The supplied Cargo CSV was tested only in the isolated test database (1,762 records and 3,698 certificates). The frontend production build and PHP syntax checks passed.

An additional run of the existing IT assignment tests exposed three unrelated failures, reproduced with the original uncached BranchContext implementation: the viewer test expects 403 but system-access middleware redirects with 302; two register tests construct categories without the matching KL branch and hit a null category at AssetController.php:93. These were not changed as part of the performance work. The checkout/reassignment/check-in workflow test itself passed.
