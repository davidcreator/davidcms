# CMS Public Page Structure — local patch

This patch adds a fixed, allowlisted visual section-order contract to the existing BDREditor for CMS system pages. It preserves Pages and Blog as separate tools and content stores; Blog posts remain managed by Blog. The shared behavior is limited to the editor framework and safe visual composition.

## Included

- An allowlisted `PageLayout` contract with route-specific section IDs and a versioned serialized envelope; no database migration.
- Admin integration: existing BDRE v2 content stays nested in the envelope, while editors can enable/disable and reorder sections. Existing ACL and CSRF controls remain authoritative.
- Catalog integration for Home, Portfolio list/detail, Tools list/detail, Contact, Quote, 404, Blog list, and Blog post page composition.
- Public templates render fixed Twig includes and module-owned dynamic data only. The editor does not accept Twig, PHP, JavaScript, arbitrary paths, or free-form template code.
- Legacy descriptions retain route defaults. Invalid layout envelopes are not emitted as HTML.

## Apply from the repository root

First verify the patch against the current checkout:

```sh
patch --dry-run -p1 < CMS-Public-Page-Structure.patch
```

If the dry run succeeds, apply it:

```sh
patch -p1 < CMS-Public-Page-Structure.patch
```

Do not run migrations: this change has no schema changes. This is a local patch only; it does not push to GitHub or deploy.

## QA notes

The JavaScript syntax check passed with `node --check`. PHP CLI was not available in the build environment, so PHP lint/runtime and Twig rendering were not executed. Review the diff and run the project's normal PHP/Twig checks after applying it in your development environment.
