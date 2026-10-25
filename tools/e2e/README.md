# BDREditor table checker — static-only

Run from the package root:

```sh
node tools/e2e/e2e_bdre_table_static.cjs
```

The checker parses the synthetic fixture, loads the pure client validator in Node VM (without DOM/browser), checks dimensions and limits, and scans the server schema, renderer, localization and CSS for the expected table contract.

It does **not** execute PHP, SQL, browser, app, E2E runtime, staging, deploy, migrations or database access. A PASS is not a PHP/runtime/security/accessibility certification.
