# F9-102 — Internal Editorial Notes

## Purpose and boundaries

The **Review → Internal notes** workspace stores private editorial reminders outside the BDRE document. Notes live only in browser localStorage; they are not added to Page/Blog content, document state, revisions, public rendering, import/export, database, or schema. Pages and Blog have separate namespaces, and keys are scoped to the current user and document. New Page/Blog drafts without a persisted document ID do not get a notes key until they are saved.

## Use

1. Select a block in the editor, then open **Review → Internal notes** and choose **Add note to selected block**.
2. Write a note of up to 1,000 characters and save. The note is stored locally in this browser.
3. Use **Locate block** to return to the referenced block, or **Edit** to change the note.
4. Deletion is a two-step action. If its block is removed, the note remains visible as an orphan with a notice; it can still be edited or explicitly deleted.

## Safety

- Local data uses a strict versioned envelope with a 200-note cap, 1,000-character note cap and 256 KiB storage-size limit. Malformed or oversized local data is reported and not silently overwritten.
- UI displays notes with text-only DOM content; note text is never parsed as HTML or executed.
- Saving and deleting notes update only the separate localStorage key and do not call the editor document commit/history mechanism.
- Notes are not synchronized to a server or shared among users/devices. Clearing browser storage removes them.

## Validation

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-102-internal-editor-notes-smoke.js` validates envelope constraints, round-trip, isolation keys, orphan indicators and explicit actions.
- `python3 tools/f9-57-static-battery.py` runs cumulative static coverage.
- All `tools/*smoke.js` scripts are run for regressions.

No real-browser/localStorage runtime test is claimed. PHP CLI and browser validation are unavailable in the current environment.
