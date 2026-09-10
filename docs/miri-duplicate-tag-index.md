# Miri duplicate-tag performance fix

## Scope and matching

Only `miri_inventory_items` (Miri Machinery and Cargo) changes. KL assets, Kemaman inventory, rentals, certificates, attachments, COGs and their IDs are not changed.

The database maintains a stored generated `normalized_tag` column using `NULLIF(LOWER(TRIM(tag_no)), '')`, indexed together with `branch_id`. This preserves the existing database matching expression and collation: case and surrounding spaces are ignored; punctuation and internal spaces remain. Null/empty/space-only tags do not count as duplicates. Original `tag_no` values are untouched. The generated field is not fillable and is hidden from serialized equipment responses.

Database generation handles existing rows during migration and automatically handles all future insert/update paths, including model edits, both import services, bulk updates and direct SQL. Do not explicitly insert/update the generated column, including in custom raw SQL scripts or future export/restore tools.

Dashboard/register duplicate filters now use one grouped subquery over indexed tags rather than a correlated full scan for every record. Register row counts use a grouped join. The details page uses indexed equality. Counts continue to count affected records, not groups; matching spans Machinery and Cargo within the same branch. Correcting tags clears warnings automatically; no records are merged/deleted. CSV preview reads grouped existing-tag counts instead of loading every raw tag.

## Deployment

1. Back up the database and validate the migration on a staging copy using the production database engine. The implementation is tested with the project's MySQL test database; stored generated columns and composite indexes must be supported by the deployed engine.
2. Use a maintenance window and pause imports while applying the schema change; adding a stored column/index can rebuild or lock the equipment table.
3. Deploy the code and run `php artisan migrate --force` after reviewing pending migrations. The required new migration is `2026_09_10_000400_index_miri_normalized_tags.php`. It automatically fills existing rows through the database-generated expression; no separate data cleanup/backfill command is needed.
4. Clear/rebuild application caches as appropriate and restart queue workers, then resume traffic/imports. The new queries require the new column/index: do not serve the new code before migrating. No frontend changes/build are required specifically for this fix.
5. Verify dashboard duplicate totals, the register duplicate filter, cross-tab matches and an edit that resolves a duplicate. Measure the dashboard response time on production; a faster duplicate query does not rule out other server bottlenecks.

Rollback requires restoring the previous application code and reversing only this migration in a reviewed maintenance window. Its `down()` removes only this index and generated column, leaving original tags and records intact. Do not blindly roll back a migration batch that contains earlier unrelated migrations.

## Verification

Automated tests cover preserved original tags, generated values for model/bulk/direct writes, blank tags, punctuation/internal spaces, cross-type matches, branch isolation, affected-record counts, edit resolution, register/detail responses, and equivalence to the old calculation on a 2,582-row isolated fixture. The MySQL EXPLAIN plan is checked for the normalized-tag index and absence of the old dependent subquery. The benchmark is local/test-only, not a production timing guarantee.

Final targeted run: 16 tests passed (290 assertions), including the supplied Cargo import, certificates/PDFs, dashboard and duplicate tests. The local 2,582-row benchmark returned the same duplicate count with 20,440.1 ms for the old query versus 22.7 ms for the grouped query. The separate inventory-performance regression suite also passed during implementation.
