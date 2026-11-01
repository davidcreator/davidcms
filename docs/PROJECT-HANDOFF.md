# New-conversation handoff — ReamurCMS / BDREditor

Use this as a starter brief in a future Arena.ai Agent Mode conversation. This is an agent prompt/handoff document, not a claim that a separate model or process has been launched.

## Copy/paste starter

> Continue work on the ReamurCMS codebase. First read `docs/BDREditor-Complete-Reference-and-Closure.md` and inspect the current workspace and relevant GitHub branch. The BDREditor project is paused/closed at F9-103 by user request. **Do not continue BDREditor or add features unless I explicitly reopen it.** My new task is: [state the new task here].
>
> Follow the project’s standing constraints: keep Pages and Blog independent; preserve existing files, blocks and behavior; no database/schema/migration/staging/GitHub/deployment writes without explicit authorization; do not execute `custom_js` or arbitrary HTML; F9-12 is deferred; do not investigate its environments/databases. Check GitHub for existing work before proposing a duplicate. If browser or PHP CLI validation is unavailable, say so and do not claim runtime validation. For requested code changes, run relevant smoke scripts, all available `tools/*smoke.js` regressions and `tools/f9-57-static-battery.py`, then deliver complete files in a CRC-verified ZIP under `entregas/` rather than patches.

## Handoff facts

- Closure reference: `docs/BDREditor-Complete-Reference-and-Closure.md`.
- Last completed BDREditor feature: **F9-103 — Media Accessibility Audit**.
- Last feature archive: `entregas/BDREditor-F9-103-Media-Accessibility-Audit.zip`.
- Last checked source cache suffix: `threejs-49-media-accessibility` in Page and Blog controllers.
- Verified at closure: 30 smoke scripts and 335 cumulative static battery checks passed; JavaScript syntax check passed. No real-browser or PHP CLI validation was available.
- User previously reported updating GitHub through F9-102. The assistant did not write or deploy F9-103; ask/check before reporting its current remote status.
- F9-12 remains deferred.

## Agent operating stance

Treat this as a clean handoff for a **new user task**, not permission to continue the previous feature backlog. Confirm actual repository state before edits, explain verified facts separately from assumptions, ask only for materially necessary clarifications, and present finished deliverables directly. A user report that GitHub is updated is not authorization for subsequent GitHub writes.
