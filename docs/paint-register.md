# Miri Paint Register

## Behaviour

A separate register at /miri-paint, available from the Miri sidebar to users with assets read/edit access. Existing Major Equipment, Construction, Rentals, KL and Kemaman records are not modified. A Paint tab on the Miri dashboard provides record counts, opening/closing stock and price summaries, date coverage, and paginated paint-type/location charts. Automatic stock movement posting and a new attachment system are not included.

The original Paint CSV uses three header records and 32 meaningful columns. The importer validates the main grouped headers, Opening/Closing Stock subheaders and individual columns. Empty trailing columns and entirely empty rows are skipped; unexpected populated columns are rejected. Reference text, multiline document details, category spelling, blanks and zero values are preserved.

Opening/closing cans, litres, unit prices and total prices are independent fields. Issue quantities in cans/litres, receipts and backloads are separate historical fields. Importing never recalculates closing stock, converts cans to litres, merges Part A/B products, computes values or replays historical issues.

## Dates

- A valid chronological pair in day/month/year or day.month.year format is mapped according to the source heading: manufacture followed by best before.
- A single date is preserved in original_date and, when parseable, unconfirmed_date. Both assigned dates remain empty and date_status is unconfirmed.
- Invalid dates, reversed pairs and ambiguous text also remain unconfirmed. Missing source dates remain not_recorded.
- Only date_status=confirmed with an assigned best_before_date qualifies for the Past best before / Best before in 30 days filters and summary counts. Today is included in the next-30-days range, not the past range.
- Editors use Edit to assign the date meaning. Changing existing date meaning/values requires a review note. Original CSV text is immutable through registration/update endpoints. Dates cannot be confirmed without an assigned date or with best-before before manufacture.
- These are register-level indicators, not background emails/notifications. Do not interpret an unconfirmed source date as evidence that a batch is usable or expired.

## Review and duplicate candidates

The register's Opening Stock and Closing Stock cards aggregate all rows matching the applied filters, independently of pagination. CAN and LTR quantities remain separate. Total prices are sums of recorded source values, while unit prices display the recorded minimum/maximum range (never a sum or average). Each metric includes its populated-record count. Missing values are excluded and an entirely missing metric displays Not recorded; explicit zeros remain zero. Possible repeated rows are included, so these source summaries are not a verified inventory valuation.

Possible repeats use a normalized description + batch + current location key within this register and branch. Case and surrounding spaces are ignored; batch number alone is not unique. Missing location participates as unknown, so repeated unknown-location rows are flagged conservatively. Blank batch/description is not a duplicate key. Candidates remain separate until staff review them; editing identifying details updates matching.

Missing batch/location/balance and unconfirmed dates receive review flags. A numeric decimal comma with one/two trailing digits, such as 0,71, is converted to 0.71 with an explicit preview warning and original value retained. Invalid numeric values remain null with warnings. A nonempty correction plus a review note acknowledges numeric warnings on save.

Original 32-column snapshots are encrypted in the database and visible to editors only. Retain APP_KEY securely with database backups. Audit events record create/update/import without exposing raw source snapshots.

## Supplied CSV verification

The supplied Desktop Paint.csv has 154 populated rows, 257 empty data rows, and 322 empty trailing columns. Section 2 contains 71 INTERNATION PAINT and 83 HEMPEL PAINT rows. The isolated test import found:

- 48 valid manufacture / best-before pairs.
- 21 unconfirmed single dates.
- 85 rows with no date.
- 4 records across 2 possible repeated-description/batch/location groups.
- 127 records with detail/date/numeric review flags (duplicate counts may overlap).
- 11 recorded closing CAN balances and 48 recorded closing LTR balances.

Source rows are not automatically imported into the application or production database by deploying this code.

## Import operations and deployment

1. Back up the database and encryption key. Deploy code and built frontend assets through the normal release workflow.
2. Review pending migrations and apply 2026_09_11_000500_create_miri_paint_register using the normal production migration process. It creates miri_paint_items and miri_paint_imports only. Rolling it back deletes those tables and their data.
3. Provision the private paint disk: storage/app/private/miri/paint, or PAINT_STORAGE_ROOT. The web process and import worker need access to the same persistent directory. Do not publicly serve it.
4. Rebuild configuration caches and restart the existing imports worker. This feature uses the imports connection/queue, timeout 600 seconds and reservation timeout 900 seconds. Worker command: php artisan queue:work imports --queue=imports --sleep=3 --tries=1 --timeout=600. Manage it as a persistent service; without it, Paint uploads stay queued.
5. Allow 20 MB CSV files plus multipart overhead in PHP and web-server request limits (for example 25 MB body limit). Parser limit: 50,000 populated rows.
6. Open Paint Register > Import CSV, preview the exact file, inspect date and numeric warnings, then confirm. The progress page polls while queued/processing. Review imported rows afterward.

Files are staged privately until processing finishes/fails. Database import and audit/task completion are transactional; a processing failure rolls back records. Identical queued/completed files are blocked by a branch-scoped hash. Changed files are treated as new imports, not updates. After a failed task, review the cause and preview/upload again; do not queue:retry the old terminal task.

The staged CSV is private, not application-encrypted on disk: use restricted permissions and encrypted disks/backups where needed.

## Dashboard

Open the Paint tab beside TEC, Garnet & PPE on the Miri dashboard. Data is loaded only for the selected dashboard; Paint aggregates are branch-scoped and do not query other inventory registers. Stock summaries share the same calculation and card component as the Paint Register, but cover all Miri Paint rows.

Best-before coverage partitions records into past, today through 30 days, beyond 30 days, unconfirmed meaning, and no confirmed best-before date (including manufacture-only records). Unconfirmed dates are never treated as expiry dates. The priority list shows up to six confirmed past/approaching dates, earliest first. Type and location charts show six groups per page. Recent records are limited to six; source snapshots are not sent to the dashboard.

No additional database migration or import worker change is needed for this dashboard.

## Verification

php artisan test --compact tests/Feature/MiriPaintTest.php

The supplied-file test imports only into the isolated test database and skips when C:/Users/User/Desktop/Paint.csv is unavailable. Synthetic tests cover all field groups, date parsing and confirmation, expiry exclusions, permissions, repeated-batch scope, transactional failure and same-file protection.
