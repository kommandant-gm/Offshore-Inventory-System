# Miri Machinery and Cargo

Deploy the code, run migrations, and build the frontend before opening the register:

```sh
php artisan migrate --force
npm run build
php artisan optimize:clear
```

Review pending migrations and back up production before running them. The Cargo migration adds fields and import history, normalises MACHINARY/MACHINERY section labels to Machinery, and replaces the unique branch/tag constraint with an ordinary index. Automatic rollback is disabled because restoring uniqueness after importing duplicates requires data review.

In Miri Inventory Register, select Cargo, then Import Cargo CSV. Upload the original UTF-8 CSV and select Preview CSV. Confirm import after reviewing the counts and first five mapped rows. Import is transactional; duplicate tags are retained as separate records. No CSV data is bundled with deployment or automatically loaded into production.

Data quality filters show duplicate tags (case-insensitive, ignoring surrounding spaces), missing tag/description/location, and historical import warnings. The View page links matching tags across both tabs, within Miri only. Record IDs identify individual items in View/Edit and COG selection. No automatic merging or deletion occurs.

Blank quantities stay null. Tonnage and length retain their source units. Cargo statuses and subcategory labels are preserved. Cargo certificate numbers and expiry dates are stored separately; invalid dates remain null with a warning and the original source value retained for review. Current record edits do not rewrite the historical source snapshot or its import warnings.

An identical file is blocked per branch and format after a successful import. An edited/re-exported file has a different fingerprint and may contain existing rows; review the duplicate count carefully before confirming. Imports performed before this feature are not present in the new file history.

The Machinery tab retains model/brand and serial fields; Cargo shows dimensions, tonnage, length and quantity. Totals are record counts, not a sum of units. Shared inventory movement, COG selection and certificate expiry reporting include Cargo because it uses the existing inventory/certificate tables.

Validation: `php artisan test --filter=MiriCargoTest`. The supplied Desktop Cargo.csv is optionally tested in the dedicated test database when available; it is never imported into the application database by that test.
