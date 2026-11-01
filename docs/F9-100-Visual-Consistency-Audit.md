# F9-100 — Visual Consistency Audit

## Scope

BDREditor adds an advisory visual-style audit for the current document. It compares only the editor's existing allowlisted visual style attributes, and only among blocks of the same block type. It does not compare or report content, media, IDs, structure, lock metadata, or arbitrary HTML. Pages and Blog use the shared editor code while retaining their independent document context and existing local preset namespaces.

## Report behavior

- Open **Review → Visual consistency** to analyze the current document.
- Only block types with more than one distinct captured allowlisted style signature produce findings. Each signature cluster includes its block IDs and count; a cluster can locate its first block.
- The audit is read-only: analysis returns report data and never changes the editor document, history, or persisted content.
- A cluster can open the existing saved-style-preset library targeted to those block IDs. This is optional and explicit. Clusters containing locked blocks, and all actions in a read-only document, cannot start preset application.
- Applying a saved preset remains subject to the existing style compatibility checks, lock protections, preview and confirmation, content/identity preservation, and undo history. The audit itself never applies styles.
- The report explains that style differences are findings for human review, not errors.

## Boundaries and validation

The analyzer first validates the document using the established BDREditor schema. It groups by block type and deterministic serialization of `captureBlockStyle` results, so non-allowlisted attributes do not affect findings. Invalid documents yield a report error rather than a partial comparison. Findings include locked blocks for visibility, while editing actions are guarded separately.

## Checks

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-100-visual-consistency-smoke.js` checks grouping, same-type isolation, lock-metadata exclusion, invalid input, consistent documents, and input immutability.
- `python3 tools/f9-57-static-battery.py` runs cumulative static coverage, including prior F9 checks.

These checks do not simulate a real browser, native file chooser, or browser localStorage runtime. No runtime/browser validation is claimed.
