# F9-103 — Media Accessibility Audit

## GitHub duplicate check

Checked the current GitHub `main` editor source and the Pages/Blog controllers before implementation. No dedicated media-accessibility audit or transcript/caption review was present. Existing accessibility review checks image alternative text, but not the video-caption/audio-transcript cases addressed here.

## Behavior

Open **Review → Media accessibility** for a read-only report. The audit validates the BDRE document first, then reports each video block for human review of synchronized captions and each audio block for review of an equivalent transcript. Findings include a localized explanation, advisory severity, block type and a button to locate the source block. The same findings are included as a separate category in the pre-publication checklist.

## Important schema limitation

The current BDRE schema has an optional visible figure caption for local video, but no synchronized subtitle/caption-track metadata. Audio blocks have an accessible label but no associated transcript field. Accordingly, the audit does **not** claim that captions or transcripts are missing; it says their presence cannot be verified from BDRE data and asks the editor to check the media source or provide equivalent accessible text. It does not fetch media, contact providers, alter content, add tracks or create transcripts.

## Validation

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-103-media-accessibility-smoke.js` tests local and embedded video, audio, localization, invalid input and source immutability, plus preflight aggregation.
- `node tools/f9-80-preflight-smoke.js` verifies the pre-publication checklist retains its prior results while exposing the additional category.
- `python3 tools/f9-57-static-battery.py` and all `tools/*smoke.js` scripts cover cumulative regressions.

Tests are static/pure-helper checks, not browser runtime validation. PHP CLI and a real browser are not available in this environment.
