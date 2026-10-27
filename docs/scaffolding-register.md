# Scaffolding Register

Miri Inventory has a separate **Scaffolding Register** at `/miri-scaffolding`.
Records whose `section_1` equals `SCAFFOLDING SET` (ignoring case and surrounding
spaces) appear there and are excluded from the Major Equipment register's lists,
filter options, tab totals, and dashboard.

No database migration or record copy is required. Existing IDs, certificates,
attachments, company assignments, audit history, and issue-note references remain
intact in the shared equipment tables. Future imports with this section are
classified automatically. Changing a record's section changes its register.

The new register supports search, live filters, company assignment, record
creation/editing, Excel editing, certificate access, and existing deletion
safeguards. It uses the same Miri branch and asset permissions as Major Equipment.
New records created from this register default to `SCAFFOLDING SET`.

Shared movement and Internal Issue Note workflows retain their existing equipment
source IDs and `Major equipment` source type for compatibility with saved history.
Scaffolding remains available to those workflows. CSV imports continue through
the existing machinery import flow.

Deploy the application changes and rebuilt frontend assets together. Rebuild any
cached routes using the application's normal deployment procedure. No manual
transfer of inventory data is needed.

Regression coverage: `tests/Feature/ScaffoldingRegisterTest.php` verifies register
separation, unchanged record/certificate IDs, filters, permissions, branch access,
creation, Excel edits, company assignment, and issue-note source availability.
