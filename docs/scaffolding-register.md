# Scaffolding Register

Miri Inventory has a separate **Scaffolding Register** at `/miri-scaffolding`.
Records whose `section_1` equals `SCAFFOLDING SET` (ignoring case and surrounding
spaces) appear there and are excluded from the Major Equipment register's lists,
filter options, tab totals, and dashboard.

The original register separation required no record copy. Existing IDs, certificates,
attachments, company assignments, audit history, and issue-note references remain
intact in the shared equipment tables. Future imports with this section are
classified automatically. Changing a record's section changes its register until
stock has been verified; verified stock records retain their classification.

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

## Yard stock and issue history

### Materials report

Open **Materials report** from the Scaffolding Register/dashboard or Miri Reports.
Choose an as-of date and company. The matrix groups actual register subcategories,
descriptions, size/model and units into columns, and shows yard rows followed by
project/location and contract rows with MR references. Excel exports the full matrix
with merged group headers and frozen headings. A3 landscape PDF splits columns into
panels of ten, repeating locations, headers and basis notes. No extra migration is needed.

This first version is a **recorded-stock report**, not a certified physical inventory.
Yard values sum confirmed signed movements by document date only if all records in a
material column have an opening entry by that date. Missing openings or negative dated
balances show unknown. Project values reflect recorded issues; pre-existing project
holdings are not inferred from legacy COG metadata. Existing backloads cannot reliably
identify the returning project, so affected project/material cells show unknown and a
return-review list gives the quantities. The report never guesses that allocation.
Current classification/company metadata is used; later backdated postings can restate
past dates. The same limitations are printed in both exports. There are no mixed-unit
grand totals. Future complete physical-stock reporting needs verified project openings
and explicit source-project allocation for returns and project transfers.

`ScaffoldingReportTest` verifies dated quantities, unknowns, company/branch isolation,
material grouping, export XML and literal text handling, PDF generation and access.

The Miri Dashboard includes a **Scaffolding** tab using the existing company filter.
It shows record counts, verified/unverified item-yard balances, verified zero balances,
stock grouped separately by yard and unit, subcategory counts, items requiring opening
verification, and the ten latest confirmed postings. Unverified quantities and drafts
are excluded from stock totals. The dashboard uses the stock migration below and does
not require another migration.

Run `php artisan migrate` when deploying the stock feature, alongside rebuilt frontend
assets. The additive scaffolding migration creates yard accounts, an immutable movement
ledger, classification options and stock-workflow fields on Internal Issue Notes.

Each existing scaffolding item has independent BINTULU and LABUAN balances. Contracts
describe movements and do not reserve separate stock. Edit details to choose or add
subcategories, descriptions and contracts. Save a unit, then open the record and verify
the physical opening quantity for each yard, including zero where appropriate.
Imported quantity and historical COG fields are retained as historical information;
neither is automatically replayed into the new balances.

Enter multiple external issues with destination/project, contract, MR, quantity,
COG reference, date and notes. Preview balances are calculated in row order; confirmation
posts the entire batch or rejects it entirely. Receipts, backloads, writeoffs and reviewed
corrections are separate confirmed movements. Corrections specify the resulting balance.
Backloads cannot exceed the yard's aggregate confirmed outstanding issues. History keeps
the actual posting order even when document dates are backdated.

For new Internal Issue Notes, select the scaffolding yard and contract when adding an
item. Saving a draft does not deduct scaffolding stock. Use **Confirm stock movement**
on the note after checking the physical movement. Mixed scaffolding/construction notes
post atomically. For a yard transfer, set Destination to exactly BINTULU or LABUAN and
verify both yard accounts first. Repeated confirmation cannot post twice. Historical
notes are excluded from the new workflow. Internal COG references cannot also be entered
manually for the same item.

Opening stock is verified once; subsequent corrections retain their reason and history.
Verified records cannot be deleted or have their unit/register reinterpreted. Stock
mutations enforce existing Miri permissions, branch scope, concurrency checks and
nonnegative balances. `ScaffoldingStockTest` covers these accounting flows together with
the existing construction, issue-note editing and register deletion regression suites.
