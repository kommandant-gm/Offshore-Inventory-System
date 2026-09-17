# Miri Internal Issue Note (DE-F-07E / REV.0)

The Miri COG create/view/download flow now follows the supplied DESB Internal Issue Note sample. It remains a document register, not a stock-posting system.

## Scope

- Major Equipment covers Machinery and Cargo.
- Rentals, Construction TEC/Garnet/PPE, and Paint are selectable in the same document.
- Search is branch-scoped and limited to 25 results; no entire register or private source snapshot is loaded.
- Source values prefill editable document fields: description, size/model, equipment identifier, serial, MR reference and unit. Fields unsupported by a source remain blank.
- Rental's combined serial/tag/equipment identifier remains in the identifier column; it is not guessed into separate serial/model fields.
- Paint batch is captured from the selected record and printed with the description, not as a tagging number. CAN or LTR must be selected explicitly.
- Missing units require staff input. Quantities permit three decimal places.
- All lines are snapshots. Source edits do not change issued notes. Creation/signing does not change balances, source dates, statuses or locations.

## Form and PDF

Header fields include consignee/department, from department/location, to, copy, destination, document date, and signatory names/designations/dates. New document numbers use DESB/YY/NNN and continue the highest suffix from both the legacy MIRI-COG-YYYY-NNNN and new format for the current year. Historical reference numbers remain stored for traceability; their displayed reference is converted to DESB/YY/NNN in the register, preview, PDF and allocation messages. Creation locks the branch while selecting the next suffix to prevent duplicate concurrent numbers.

The PDF uses A4 landscape, the existing Dayang logo, green company heading, red number, sample table columns, form/revision codes, certification and acknowledgement-return text. Long item/reference/remarks text wraps into continuation rows. Pages repeat the header and show Page X of Y. Totals are separated by uppercase/trimmed unit; synonymous units are not silently converted. Unknown units on historical notes are marked UNSPECIFIED. More than four unit totals get a dedicated totals section.

Long header fields are abbreviated with an asterisk and their complete values included in the document's continuation rows. General remarks likewise appear as document rows so page numbering remains accurate. The web preview is document-style; the downloadable PDF is the authoritative print layout.

Issued/checked, verified, and received blocks have name/designation/date lines and signing space. Names/dates alone do not constitute signatures or approval. Only the receiver block uses the existing digital-signature action. It requires a valid bounded PNG, prevents repeated signing and records an audit event. HOD approval routing, emails and additional digital signers are not introduced.

The PDF uses uncompressed output for this renderer because compressed embedded-font streams rendered incorrectly on the tested host. Font handling for other modules is unchanged.

## Deployment and compatibility

Deploy the code and run the frontend build. Back up first, review pending migrations, then apply 2026_09_11_001000_extend_miri_cog_issue_notes through the normal production migration workflow. Existing COGs retain their stored numbers and records; new nullable fields appear blank on old documents. The new layout applies to their downloads too.

The migration adds only Miri COG metadata/snapshot fields and widens COG quantity precision. No KL/Kemaman or source inventory tables are changed. Rolling back drops new metadata, so back it up; the wider quantity precision is intentionally retained on rollback to avoid rounding stored values.

No queue worker is needed to create or download a COG. Existing import workers are unaffected. This implementation does not create production COGs automatically.

## Verification

php artisan test --compact tests/Feature/MiriCogIssueNoteTest.php tests/Feature/InventoryPerformanceTest.php

Tests cover four-source item selection, branch/read-only permissions, no source changes, preserved snapshots, Paint units, distinct-unit totals, invalid references, atomic creation, signature validation/replay and multi-page PDF counts. Test PDFs are written only to private testing storage for visual review.

## Equipment availability

Major Equipment and Rental records are reserved by draft or signed outbound COGs (Issue out, Transfer, Return to supplier). The picker displays the holding COG numbers and disables outbound selection. Saving checks the same rule under the existing branch row lock, so concurrent submissions cannot both reserve equipment. Repeated equipment rows in one note are rejected.

Received backload releases the recorded quantity; partial returns keep the record blocked until the outstanding quantity reaches zero. Returns exceeding outstanding COG quantities are rejected. Existing non-cancelled COG history is replayed in creation order, including historical duplicate issues; returns consume the oldest outstanding allocations first. Source register stock and location fields are unchanged. Paint and Construction quantity-based stock handling is unchanged.

Staff may cancel an unfulfilled, unsigned outbound draft with a mandatory reason and confirmation that equipment was never dispatched. Cancellation is audited, retains the document, and releases its reservation. Signed notes, backloads, and notes with subsequent backload history cannot be cancelled. Cancelled notes cannot be signed. No migration is required. Equipment issued outside the COG register is not inferred from source register fields.

## September PDF amendments

General PDF text is 10 pt, with 11 pt quantity totals. Company heading and issue-note title retain their sizes. Table widths (percent) are Item 4, QTY 5, Unit 5, Description 30, Size/Model 11, Tag 14, Serial 11, MR 12, Remarks 8. Quantity and unit values are centred. All three signatory boxes have a signature line. Larger text uses ten continuation rows per page with narrower character wrapping, including long quantities; the web preview uses the same column proportions and document rows. Legacy MIRI-COG references display as DESB/YY/NNN as well; PDF filenames safely replace slashes.
