# BDREditor — complete project reference and closure

**Snapshot date:** 1 November 2026 (America/Sao_Paulo)  
**Feature scope completed:** F9-103  
**Project state:** paused/closed for now at the user's request. This document is an operator reference and a handoff; it does not authorize further changes, GitHub writes, staging, or deployment.

## 1. What BDREditor is

BDREditor is ReamurCMS's native, structured block editor for Blog and CMS Pages. It edits a validated BDRE document in the existing admin workflow. Pages and Blog share editor implementation where useful, but remain separate content contexts: each has its own controller, saved document, media scope, draft identity and local browser storage namespace.

The editor's tools are built around the BDRE block model and explicit actions. Content changes are validated and committed through the existing editor flow; review tools are advisory and read-only unless their interface explicitly offers a separately previewed, confirmed action.

### Safety boundaries

- Treat Pages and Blog as independent. Do not transfer content, media, notes or saved local data across contexts.
- Use native, allowlisted BDRE attributes and block types. Do not add arbitrary HTML, execute `custom_js`, or treat user-provided text as executable code.
- Preserve existing blocks, resources, IDs, structure, content and behavior unless the user explicitly requests a change.
- Local editorial notes and style presets are browser-local. They are not published content, server state, a database feature or a substitute for the BDRE document.
- Do not infer that an advisory audit has verified the live behavior of an external site or media provider.

## 2. Architecture and source map

| Path | Responsibility |
| --- | --- |
| `dev/admin/view/js/blog-block-editor.js` | Shared editor UI, block model helpers, client validation, keyboard/accessibility behavior, local review analyzers, style tools, and editor workspaces. |
| `dev/admin/view/js/blog-block-editor-init.js` | Mounts the editor in the existing admin form and connects editor state to the established submission flow. |
| `dev/admin/view/js/blog-block-import.js` | Existing import-related editor integration. Inspect before changing import/export behavior. |
| `dev/admin/view/css/blog-block-editor.css` | Editor-scoped styles, responsive treatment and accessible/forced-colors states. |
| `dev/admin/controller/cms/page.php` | Pages editor context, localized strings, permissions/context and asset cache key. |
| `dev/admin/controller/blog/blog.php` | Independent Blog context, localized strings, permissions/context and asset cache key. |
| `dev/system/library/blog/block_document.php` | Server-side BDRE document validation and allowlisted schema. |
| `dev/system/library/blog/block_renderer.php` | Public rendering of validated native blocks. |
| `tools/f9-57-static-battery.py` | Cumulative static verification battery for the editor's block inventory and feature invariants. |
| `tools/f9-*-smoke.js` | Pure-helper and source-level regression checks. These are not a real-browser test suite. |
| `docs/F9-*.md` | Feature-specific implementation notes, safety boundaries and check instructions. |
| `agents-ia/Agent-BDREditor/` | Existing editor agent guidance. This folder's older visual-design brief may not describe every feature added after that brief; read this closure/handoff document as the current project overview. |

**Current asset cache suffix:** `threejs-49-media-accessibility` in both Page and Blog controllers. Change the suffix consistently when future front-end asset changes require cache busting.

## 3. Capability inventory

The detailed specifications are in the linked feature documents under `docs/`.

### Editing, blocks and workspace

- Native block editing, inspectors, contextual toolbars, full-screen and embedded editor modes, a single ribbon, editor branding, responsive layout, and command palette.
- Current static inventory covers **21 top-level block types plus 4 nested types**. The catalog includes paragraph, heading, list/list item, image, quote, embedded/local video, Three.js thumbnail, button, callout, details, spacer, preformatted code, separator, group, columns/column, gallery, cover, table/row/cell, file and audio. The cumulative battery checks schema, preview and editor integration.
- Content models and block patterns support safe, additive insertion. Existing content is not replaced by a model insertion.
- Pages creation and Pages-list Quick Edit have dedicated workflows; Blog remains independent. Page-local media and Blog media remain separate.
- Editor-wide controls include undo/redo, selection and outline navigation, inspector/contextual actions, keyboard shortcuts, in-editor command palette, preview and full-screen mode.

### Review and content quality

- Accessibility review for existing checks such as headings, empty text, image alternative text, button labels and table headers (F9-77).
- Media accessibility audit (F9-103): flags video blocks for human review of synchronized captions and audio blocks for review of an equivalent transcript. It does **not** claim captions/transcripts are absent. Current BDRE schema does not record synchronized caption tracks or associated transcript fields; the audit is read-only and does not fetch media.
- Link/destination review (F9-78), readability review (F9-79), and a pre-publication checklist (F9-80) that aggregates the existing reviews, including media accessibility.
- Internal anchor review and a separately confirmed assisted repair (F9-85/F9-86); table of contents and heading anchors (F9-84).
- Visual consistency audit (F9-100) compares allowlisted visual styles among same-type blocks and never changes the document.

### Find, revisions and editing safeguards

- Find & Replace already existed before F9-101. F9-101 adds a before/after preview and confirmation for **Replace all**, revalidates the document snapshot, skips locked targets, preserves the source on invalid input and commits as one undoable edit. Single Replace remains a one-occurrence action.
- Local draft/revision history, revision comparison and undo/redo are separate from the server's save operation.
- Multi-block selection, grouping, duplicate-as-Group, moving selected siblings as a unit and ungrouping use validated transformations and preview/confirmation where specified (F9-92–95).
- Block locks protect blocks and descendants against edits and structural changes, with explicit unlock and visible/accessibility feedback (F9-96).

### Style and private editorial tools

- Copy/apply visual style and a bounded named style-preset library with preview and confirmation (F9-97/F9-98).
- Style preset export/import transfers only allowlisted named style presets, with validation, size bounds and preview; no document content or IDs transfer (F9-99).
- Internal editorial notes (F9-102) are browser-local and scoped by user, document and Pages/Blog. Notes can be located, edited or explicitly deleted; notes whose block was removed are retained and marked as orphaned. They do not enter BDRE content, history or public output.

### Document import/export

- Safe Markdown export/import workflows (F9-82/F9-83), local preview and validated additive insertion, plus the editor's document export/import mechanisms.
- Format support and limitations are specified in the relevant feature documents. Do not promise fidelity for formats or content not documented and tested by those workflows.

## 4. Milestone map

- **F9-27 and F9-28–F9-43:** earlier Patterns, editor reliability, recovery/history, concurrency, search/replace, keyboard accessibility, statistics and block customization milestones. The user reported these as completed; do not redo them without a specific request.
- **F9-52–F9-74:** full-screen/editor workspace and progressive native block inspectors, toolbars, Pages media support and layout/content blocks.
- **F9-75–F9-91:** interface consistency, content models, accessibility/link/readability/preflight/revision tools, Markdown and TOC workflows, anchor review/repair, Pages creation/Quick Edit, command palette and related UI refinements.
- **F9-92–F9-99:** multi-block operations, locks and visual-style/preset workflows.
- **F9-100–F9-103:** visual consistency audit, Find & Replace safety preview, internal editorial notes, and media accessibility audit.

This map is a navigation aid, not a replacement for the detailed feature documents. F9-12 remains deferred; do not investigate environments or databases for it.

## 5. Verification and known limits

At F9-103 closure, the available checks completed successfully:

- JavaScript syntax check for `blog-block-editor.js`.
- All **30** `tools/*smoke.js` scripts passed.
- Cumulative static battery passed **335 checks** across 21 top-level and 4 nested block types.
- The F9-103 media audit smoke checks local/embedded video and audio findings, localization, source immutability and checklist aggregation.
- F9-103 complete archive: `entregas/BDREditor-F9-103-Media-Accessibility-Audit.zip`; **108 files**, CRC verified; SHA-256 `10497a87b1221cd247ec20112adf1fe9a9ef915e681051d3cbac31e3302e14b4`.

These checks are static or pure-helper smoke tests. A real browser, actual keyboard/focus behavior, browser localStorage integration, media player captions and the PHP CLI were not available for validation. Do not represent those as tested. The user has handled prior GitHub updates; the assistant has not written to GitHub or deployed these changes. Confirm the user's current GitHub state before claiming a feature has been published.

## 6. Operating rules for any future task

1. First inspect the current local source and the user's current GitHub branch for an existing or partial implementation. Avoid duplicating a tool; if a related tool exists, identify a scoped improvement instead.
2. Ask for clarification only where a missing decision materially changes scope; avoid unnecessary approval cycles for small reversible steps.
3. Keep Pages and Blog independent. No database/schema changes, arbitrary HTML or execution of `custom_js` without explicit authorization.
4. Preserve old features and resources. Run the dedicated smoke, every available `tools/*smoke.js` regression, JavaScript syntax checks and the cumulative static battery when relevant.
5. Be explicit when PHP/browser/runtime validation is unavailable. Never claim tests that were not run.
6. Deliver complete files rather than patches. For an implementation, build and CRC-verify a complete ZIP in `entregas/` and present it.
7. Do not stage, commit, write to GitHub, modify staging/production or deploy without explicit authorization. A user report that GitHub is updated is status, not authorization for the next write.
8. Do not investigate F9-12's deferred environments/databases.
9. The project is paused at F9-103. Do not add further BDREditor features unless the user reopens that work.

## 7. Suggested handoff

For a new conversation on a different ReamurCMS project stage, start with `agents-ia/Agent-BDREditor/PROJECT-HANDOFF.md`. It contains a reusable instruction block and asks the next agent to wait for the user's new task rather than continuing BDREditor automatically.
