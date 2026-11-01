# F9-101 — Find & Replace Safety Preview

## Existing feature and scope

The GitHub `main` branch already includes Find & Replace: it is available from Review, the command palette, and the `Ctrl/⌘+F` shortcut. It supports case sensitivity, whole-word matching, navigation, single replacement, and bulk replacement across supported rich-text runs. This change does **not** add a second Find & Replace tool; it improves the existing bulk-replace action.

## F9-101 enhancement

- **Replace all** now opens a read-only before/after preview. No document change happens until the user confirms.
- The preview summarizes occurrence counts and shows up to 30 literal text examples; the existing 1,000-match safety limit remains.
- Locked blocks and descendants are skipped, counted, and left unchanged. A bulk operation is one explicit confirmation and one undoable editor commit.
- The document snapshot and search/replacement inputs are checked again at confirmation. If document data changed after preview, confirmation is refused and the user must review a fresh preview.
- Invalid documents are rejected atomically. Replacement changes only matching rich-text runs, retaining block IDs, order, and unrelated content. Existing inline run transformation behavior is preserved.
- Preview can be cancelled, dismissed with Escape, or navigated with a keyboard focus trap. Preview text is inserted as text, not HTML.
- Single **Replace** remains a direct one-occurrence action; this enhancement specifically protects bulk replacement.

## Checks

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-101-find-replace-preview-smoke.js` checks word-boundary and cross-run matching, locked skips, content and identity preservation, invalid documents, and pure-helper immutability.
- `python3 tools/f9-57-static-battery.py` runs cumulative static coverage.
- All `tools/*smoke.js` scripts are executed for regression coverage.

These checks do not simulate browser keyboard interaction or a real browser. No browser runtime validation is claimed.
