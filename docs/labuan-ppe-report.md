# Labuan Warehouse PPE Report

Open **Miri Reports → Labuan Warehouse PPE Report**, select a month, and preview or download Excel.

The workbook uses the supplied `PPE STORE` sheet from `LABUAN WAREHOUSE PPE STORE INVENTORY 2026.xlsx`. Its logo, column widths, merged headings, fonts, borders, print settings and sign-off sections are retained. Sample inventory and named signatories are removed. The month and update date are generated dynamically. Extra items extend the form and move the sign-off section down; long text increases row height. Decimal quantities remain visible. No grand total is added to the original form.

Only Miri construction-register items classified as PPE and located in Labuan/LBN are included. Monthly stock figures use the existing construction stock history and Malaysia time boundaries. Read the coverage notes alongside the report for historical balance limitations. Unknown quantities remain blank; actual zero quantities display zero. Generating a report does not post stock movements.

The screen preview reads the generated workbook and reproduces its layout in a horizontally scrollable table. Excel remains the source for print layout. An additional Report notes worksheet explains the figures without changing the main form.

Deployment requires the PHP/Vue changes, both `resources/report-templates/labuan-ppe-*` files and rebuilt frontend assets. This feature adds no database migration, but requires the existing construction register and stock-history schema. If that schema is absent, the report displays an unavailable message.

Validation: PPE scope, month boundaries, stock calculations, permissions, missing schema, workbook XML, retained styles/logo, row expansion, decimal quantities and long descriptions are covered by `MiriLabuanPpeReportTest`. The existing consumable report tests also cover the shared workbook helper.
