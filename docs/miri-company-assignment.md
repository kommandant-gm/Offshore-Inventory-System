# Miri item ownership

Miri Machinery, Cargo, Rental, Construction TEC/Garnet/PPE and Paint items have an optional `company` field: DESB or FTSB. Null means Not assigned. Existing rows and CSV imports remain unassigned; descriptions, tags and supplier names are not used to infer ownership. CSV column layouts are unchanged.

Company is editable on Register/Edit and visible on item detail pages and register tables. Register, dashboard and COG-picker filters support All companies, Not assigned, DESB and FTSB. Ownership is independent of rental supplier/project and does not change COG numbering or document branding.

Users with assets edit permission can check items on a register page, choose a company and click Assign selected items. The header checkbox selects only that page. Page/filter changes clear the selection; there is no implicit assignment of all search matches. Not assigned clears ownership when needed. The endpoint accepts at most 500 explicit IDs and validates every ID against the Miri branch and chosen register; any invalid ID rejects the whole operation. Every changed item produces an audit event with old/new company. Stock, locations, certificates, status and COG history are unchanged.

Apply `2026_09_23_000200_add_miri_item_companies.php` after the existing Miri register migrations. This local database currently lacks those prerequisite tables, so application activation still requires the Miri migration deployment. No existing ownership was assigned during development. Build assets with `npm run build`.

Verification: `MiriCompanyTest` covers creation/editing, all register bulk assignments, unassignment, audit events, stock preservation, branch/register isolation, read-only permissions and company filters across registers, dashboards and COG pickers.
