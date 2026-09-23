# Miri Construction TEC, Garnet & PPE Register

## Scope and data

This separate Miri register preserves the Construction CSV's 35 meaningful columns and both header levels. It does not modify Major Equipment, Rentals, KL or Kemaman inventory tables. Categories and both section fields retain source spelling.

Each source row becomes one record. Historical stock-in, location issues, personnel issues and backloads are separate reference fields: importing never deducts or adds these quantities to the supplied stock balance. Closing value and unit price remain supplied snapshots; no costing or mixed-unit totals are calculated. Blanks remain distinct from zero. Repeated Garnet descriptions are flagged for review, not merged.

The supplied CSV contains 1,588 rows across six categories, including 30 records in 15 duplicate-tag groups. Duplicate matching is case-insensitive with outer spaces ignored, within this register and branch only. Editing a tag updates duplicate detection automatically. Invalid numeric/date values retain their original text in the protected source snapshot and receive review warnings. Acknowledging a grouping warning requires a review note and does not consolidate stock.

The register provides 25-row pagination, search, classification/location filters, review filters, grouped registration/edit/detail pages and Miri audit events. Certificate dates are displayed, not interpreted as new automatic expiry alerts. Movement posting and opening-balance cutoffs require a separate agreed workflow.

## Import

Keep the two CSV header records and all 35 meaningful columns in their original positions. Empty trailing columns are accepted; unexpected trailing data or shifted headers are rejected. Preview the exact file before confirming its background import. Maximum upload: 20 MB; maximum data rows: 50,000.

The import is transactional: a row failure rolls back the batch. An identical queued/completed file is blocked using its content hash. A changed file is a new import, not an update: overlapping tags are highlighted, not merged. After a failed task, review its cause and preview/upload again; do not retry the old terminal task using queue:retry. The progress page reports queued, processing, completed or failed states.

## Privacy and attachments

Three attachment slots support JPG, PNG, WebP or PDF, up to 5 MB each. Images are limited to 4096 by 4096 pixels. Files use random private paths and authenticated branch-checked routes; no public storage link is needed. Replacing/removing an attachment removes the old file after the database save succeeds. Retain backups if historical attachment recovery is required.

Personnel/IC details and original CSV snapshots are encrypted in the database, visible only to editors, excluded from ordinary list/search payloads and audit values. Personnel input is excluded from validation session flashing. Back up APP_KEY securely with the database; losing/changing it without a migration prevents decryption. Staged CSVs and attachments are private filesystem files, not application-encrypted file contents: use restricted filesystem permissions and encrypted disks/backups. Staged CSVs are removed when processing completes or fails.

## Deployment

1. Deploy the source changes and run npm run build through the normal release workflow.
2. Back up the database, private storage and encryption key. Review pending migrations, then apply the new 2026_09_10_000500_create_miri_construction_register migration using the normal production migration process. It creates two tables; rolling it back deletes this register and import history.
3. Provision persistent private storage. The construction disk defaults to storage/app/private/miri/construction; CONSTRUCTION_STORAGE_ROOT can override it. Both web and queue processes need access to the same location. Do not place it in public web storage.
4. Ensure PHP and IIS/request-body limits allow a 20 MB CSV plus multipart overhead (for example a 25 MB body limit), and three 5 MB attachments in one registration request.
5. Rebuild configuration caches and restart queue workers. This feature uses the existing imports connection and imports queue. A worker command is php artisan queue:work imports --queue=imports --sleep=3 --tries=1 --timeout=600. Keep its reservation timeout above the job timeout and run it under the existing supervised worker setup. Without a worker, imports stay queued.
6. Sign in as a Miri editor, open the new sidebar register, preview Construction.csv and review the counts/warnings before importing. Check completion, source balances, duplicates and attachment access. Read-only staff must not be able to edit or see personnel/IC values.

Automated CSV verification uses the isolated test database, not production. No production import is performed by deploying the code. Test with php artisan test --compact tests/Feature/MiriConstructionTest.php; the supplied-file test skips when the Desktop CSV is unavailable.

## Confirmed stock workflow

TEC, Garnet & PPE now has a separate stock ledger. Deploy `2026_09_24_000100_add_construction_stock_ledger.php` after the existing Miri Construction, COG, extended COG and company migrations. This migration does not replay old COGs, infer opening balances, or change imported quantities. The development database currently has those prerequisite tables pending; do not run this migration alone against a database missing them.

On each item details page, an editor verifies the remaining opening quantity, unit and storage location with a reason. A zero opening quantity is valid. An old receipt of 200 TON is not proof that 200 TON remains today. Each existing/imported item requires this one-time baseline before new stock transactions.

After initialization:

- Receive new stock increases the balance; write-off decreases it. Reviewed correction sets the verified current balance and records the difference, reason and previous balance.
- New Construction COGs remain drafts without posting stock. Confirm stock movement posts every Construction line atomically. Receiver signing is separate and neither required for stock confirmation nor sufficient to post it. An unsigned confirmed note can still be signed.
- Issue out and Return to supplier deduct. Received backload adds only up to the confirmed outstanding Issue out quantity on that source record; older returns require a documented correction.
- Transfer requires a destination Construction record in the same branch and company, with matching classification, description, model, identifiers and unit, at a different location. Register and verify that destination first (zero is valid). The COG To location must match it. Confirmation debits the source and credits the destination together.
- Confirmed notes cannot be cancelled as unfulfilled drafts. Use a real backload, reverse transfer or reviewed correction to correct stock, retaining the original history. Pure Construction backload drafts can be cancelled without changing stock.
- Unit, stock location, company and stock balance cannot be changed through the ordinary edit form once tracking is active. History fields stay as imported/reference information and do not post movements. Changing item description does not alter stock.

The ledger records the actor, posting timestamp, quantity, before/after balances, unit, location, COG/reference and reason. Confirmations are serialized per branch and checked against live balances. COG confirmation is idempotent; manual actions use unique request keys and stale-balance tokens. Overdraws, mixed units, unverified baselines and mismatched transfers fail without partial postings. History is paginated on the item details page. Dashboards and registers read the updated stock balance automatically.

This workflow applies to Construction only. Paint retains its existing posting behavior; equipment reservations retain theirs. A mixed COG explicitly confirms its Construction lines without reposting other registers.

Validation: `php -d extension=pdo_sqlite -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter="ConstructionStockLedgerTest|MiriConstructionTest|MiriCogIssueNoteTest|PaintStockLedgerTest"` uses the isolated test database. Run `npm run build` for the production frontend.
