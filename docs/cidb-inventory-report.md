# Bintulu Yard CIDB Training Item Inventory Report

Open Reports, choose the CIDB report card, select a month and preview or export.
Only Miri construction records at BTU/Bintulu locations with the whole word CIDB
in Category, Section 1 or Section 2 are included. WQT TRAINING is excluded unless
the record also explicitly has a CIDB classification. This is the user's confirmed
scope. Labuan, other branches and non-CIDB inventory are excluded.

The original supplied workbook is bundled at
`resources/report-templates/cidb-training-inventory.xlsx`. Its logo, headers,
widths, colours, borders, print settings and price columns are retained. The body
expands to fit all rows, remarks wrap, and the grand total is at the bottom only,
as requested for the reports. Old signer names/dates are not carried forward.

`InventoryReportWorkbook` and `MonthlyInventoryReport.vue` are shared with Paint;
each report has its own data service, template configuration, page and endpoints.
Both preview and export require Miri asset read access and never mutate stock.

Monthly boundaries use Asia/Kuala_Lumpur converted to UTC for ledger queries.
Opening is the latest ledger balance before the month. Closing is the latest
balance before the following month. Receipts and issues use posted ledger entries
within that month. Other movement types appear separately in Remarks. No imported
receipt snapshot is interpreted as a monthly movement. Missing/partial history is
explained on Report notes. An unverified current register balance can appear as
current closing stock, but cannot supply historical or opening stock.

Metadata uses current register values. Recorded unit price and closing value are
only used for the current month; historical prices and opening/received/issued
values are unavailable and left blank. Mixed or absent units are not totalled.

The existing construction register and stock-history migrations must be installed.
Missing tables show an unavailable message and block export. No migrations are
run by the report. Tests cover branch/location/classification exclusions, Malaysia
month boundaries, recorded prices, missing history, permissions, template content,
totals, formula-like text and absence of stock changes.
