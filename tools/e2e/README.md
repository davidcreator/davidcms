# F9-27 E2E release gate — preparation only

## Static checker (safe to run now)

`node tools/e2e/e2e_f927_static.cjs [F9-27-root]` parses local contract/fixture JSON and confirms matrix status is blocked/not-run, required gates exist and fixtures use synthetic-only identifiers. It opens no network/socket/database and does not start the app, PHP or browser.

## Functional release gate (not executed)

Run only after all conditions in `docs/F9-27-Release-Gate.md` are met and a new explicit authorization supersedes the no-runtime constraint. Confirm disposable staging and F9-12 status with responsible owner. Use synthetic test posts and media only; never run in production.
