# Labuan Warehouse General Store Consumable Inventory Report

Open its card under Reports, select a month, preview, then export Excel.
The user confirmed both Consumable and PPE categories. Scope is the Miri
construction register, current location mapped to Labuan/LBN, with either category
(case-insensitive and trimmed). Bintulu and other categories are excluded.

The original workbook is bundled at
`resources/report-templates/labuan-consumable-inventory.xlsx`. Its distinct IN/OUT
layout, logo, merged headers, widths and sign-off section are retained. Data rows
expand, remarks wrap, the print area/footer move down, and the bottom grand total
is populated only for complete quantities in a single unit. Source signer names
are blanked. This template has no price columns.

Opening/closing use the shared construction ledger report. IN includes receipt,
backload and transfer_in; OUT includes issue, transfer_out and supplier_return.
Other Misc. is signed correction plus writeoff. Source references and COG
from/to locations are listed with all distinct monthly posting dates, converted
from UTC to Malaysia time. Opening verification is not treated as a receipt.
Unknown monthly history stays blank and is explained on Report notes.

The header covers Labuan general stores instead of falsely identifying all rows
as store B2. Each row retains its actual current store and storage rack. Locations
and classification are current metadata, not historical snapshots.

Preview uses shared pagination (25 records, 10 coverage notes); Excel includes all
rows and notes. Generation is read-only with Miri branch/asset-read authorization.
Existing construction tables are required; no migrations or inventory writes are
performed by the report. MiriLabuanConsumableReportTest verifies categories,
location/branch scope, Malaysia month boundaries, movement details, expanded
template, totals, permissions and missing-schema handling.
