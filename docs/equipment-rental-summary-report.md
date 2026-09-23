# Equipment Rental List Summary

Reports → Equipment Rental List Summary → month/project/location → Preview → Export.
The report reads Miri rental register data. It does not require the construction or
paint stock ledgers, and does not change rentals or movement documents.

The bundled DE-F-187A workbook is the export template. Its company heading, logo,
merged description cells, column widths, header/footer, note and signature block
are retained. The 17-row body expands as required and the print area and footer
merges move with it. Text wraps; identifiers and formula-like strings are literal
text, preserving leading zeroes. The report has no invented totals or signatures.

The template explicitly excludes gas cylinders. Detection matches rental details:
gas classification, gas-cylinder descriptions, or named gases with cylinder wording.
Other rental equipment is included across Miri locations, with optional exact
project/contract and current warehouse/location filters.

Month inclusion uses overlap of recorded rental dates: on-hire certificate date
(delivery date fallback) through the later return/off-hire date. Missing endpoints
are left open and flagged on Report notes. Conflicting dates remain included for
review. Record creation/import timestamps are not rental start dates. Inactive
records are not automatically excluded from historical reports.

SR/PO and DO references appear together in the supplied document column. Receipt
and on-hire dates, and return and off-hire dates, are individually labelled when
they differ. Dates use DD/MM/YYYY. Current references, locations and remarks are
not historical snapshots; the export notes state this limitation.

Both endpoints enforce Miri branch access and asset read permission. Missing rental
tables produce an explicit unavailable message. Tests cover scope/filter/date
mapping, gas exclusion, template/merge expansion, print area, identifier safety,
unchanged database records and access control.
