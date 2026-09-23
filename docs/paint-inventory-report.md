# Monthly paint inventory report

Open **Reports**, select month, BTU/LBN and paint type, then **Preview report**.
The preview displays 25 rows per page. **Export Excel** includes all matching rows.
Changing a filter requires a new preview before export. Export regenerates the report
from the latest stored data for the applied filters.

Both endpoints require Miri branch access and asset read permission. They do not
post stock, roll months forward, or update inventory. Existing paint register,
monthly snapshot and movement tables must already be migrated. Missing tables
produce an unavailable message instead of a misleading empty report.

## Data definitions

- Monthly balances use the saved month snapshot, or item balances if its stock
  period exactly matches the requested month. Missing history is left blank.
- Item details, brand, storage and PO/DO references are current metadata, not
  historical snapshots. Filters use the existing paint brand/location mappings.
- Purchase receipts have no distinct dated ledger type yet. Total received is
  blank; the imported `stock_in_qty` is never assumed to belong to this month.
- Tracked issues are outbound ledger entries linked to an Issue out note. Other
  outbound includes transfers and supplier returns. Posted draft notes are included,
  because the current paint workflow posts their stock at creation.
- Backloads and cancellation reversals are positive movements in their posting
  month. They do not erase prior-month issue history.
- Adjustments stay separate, including unknown adjustment quantities as blanks.
- CAN and LTR have separate rows. No conversion or mixed-unit total is inferred.
- No price columns are exported. Missing quantities are blank, genuine zero is 0.

The workbook uses a values-only XLSX table with frozen headings, column filters,
three-decimal quantities and a Report notes sheet. Inventory text is written as
inline strings, so formula-like input is never evaluated. Temporary export files
are removed after sending the response. The XLSX writer requires PHP ZipArchive,
which is also used by existing Excel imports.

Validation: `MiriPaintReportTest` covers filtering/branch scope, current/historical
balances, missing history, read-only generation, validation/access, unavailable
schema, XLSX XML structure, precision, and formula-like text.
