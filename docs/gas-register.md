# Gas Register

The Miri sidebar now has a Gas Register. Existing and new rental records whose supplier is exactly BIG or LGM (ignoring case and surrounding spaces) appear there. All other suppliers, including blank suppliers, remain in Rental Register. Each register's list, pagination, summary cards, live filter choices and Excel view use the same partition.

No data migration or copying is needed. Both sections use the original records, IDs, attachments, company assignments, movement references and audit history. Existing record URLs remain valid; detail and edit pages select the return link and sidebar section from the supplier. Editing a supplier automatically changes which register contains the item.

Both import pages accept the existing Rental CSV format. Mixed supplier files are automatically divided between the two registers. Existing shared rental reports, dashboard aggregates and movement/issue-note selection continue to include both groups.

The Gas table view includes **Delete all Gas records** only for the `codex` super-admin account with asset edit access to Miri. The server enforces the same permissions. Type `DELETE ALL GAS` to confirm permanent deletion of all Miri BIG/LGM records and their attachments across every page and company, regardless of filters. If any selected record is linked to a COG document, the entire operation is rejected without deleting records. Successful deletion preserves the existing per-record audit trail and attachment cleanup. Other rental suppliers and branches are unaffected.

Deploy the changed PHP/Vue files and rebuilt frontend assets (`npm run build`). Rebuild any cached routes using the existing deployment procedure so `miri-gas.*` routes are available. No new database migration is required.

Verification: `php artisan test --compact tests/Feature/MiriGasRegisterTest.php tests/Feature/MiriRentalAttachmentTest.php` and `npm run build`.
