# Monthly paint inventory report

Open **Reports**, select a month, then **Preview report**. BTU/LBN and Hempel/IP are combined in one report; brand and storage remain visible on each row.
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
- Report prices remain visible (the dashboard price removal does not apply here).
  Recorded opening/closing item values appear once on the LTR row, with an explicit
  remark; they are not recalculated valuations. Unit price uses the recorded closing
  price (or opening price if absent) on the matching recorded CAN/LTR unit row.
  Historical prices and undated received/issued values remain blank. Missing
  quantities are blank, genuine zero is 0.

The XLSX export copies `resources/report-templates/paint-inventory.xlsx`, the
user-provided workbook. It preserves the logo, original styles, A:Q column order,
merged headings, widths, frozen panes, landscape settings and sign-off positions.
Rows 15?32 expand when needed; totals, sign-off and print area move accordingly.
Old source formulas are removed. Quantity totals are populated only with complete
coverage in one unit; financial totals are populated only with complete recorded
values. Old approver names and signature dates are left blank. Report notes are
on a separate sheet. Price columns remain in their original positions.

Inventory text is written as inline strings, so formula-like input is never evaluated.
Temporary export files are removed after sending the response. PHP ZipArchive and
DOM are required. Exports do not depend on the user's Downloads directory.

Validation: `MiriPaintReportTest` covers filtering/branch scope, current/historical
balances, missing history, read-only generation, validation/access, unavailable
schema, XLSX XML structure, precision, and formula-like text.
