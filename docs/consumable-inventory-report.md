# Bintulu Yard Consumable Inventory Report

Reports → Bintulu Yard Consumable Inventory Report → select month → Preview / Export.

The database scope is Miri construction items at Bintulu/BTU locations whose
Category is CONSUMABLE (case-insensitive and trimmed), regardless of Section 1.
The separate CIDB report continues to select Section 1 = WQT TRAINING. A record
meeting both definitions can legitimately appear in both reports.

The original workbook is bundled at
`resources/report-templates/consumable-inventory.xlsx`. The export retains its logo,
headings, price columns and styling, expands the body, wraps remarks, and places
the grand total at the bottom only. Previous signer names/dates are blanked.

The shared ConstructionInventoryReport service supplies monthly posted movements
and balances using Malaysia month boundaries. Missing history stays blank; a
current unverified balance is identified in Report notes. Current recorded prices
are included without inventing historical valuations. Units are kept separate.
The report never changes stock and requires Miri asset read permission.

The existing construction register and stock-history tables are prerequisites;
the report shows a clear unavailable message when they are missing.
MiriConsumableReportTest covers data scope, monthly dates and balances, template
export, prices, missing history and permissions. CIDB regression tests ensure its
WQT filter remains independent.
