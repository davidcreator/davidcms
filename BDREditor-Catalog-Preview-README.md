# BDREditor — route-aware Catalog preview

This increment extends the existing CMS Pages preview rather than adding another editor or preview endpoint. It submits the unsaved BDRE v2 document and the current ordered section list through the existing same-origin POST preview, then renders the fixed Catalog Twig template for the saved system page key.

System page templates include Home, Portfolio list/detail, Blog list/post, Tools list/detail, Contact, Quote, and 404. Regular CMS Pages keep `cms/page_info.twig`. Pages, Blog, Portfolio, and Tools retain separate ownership and content flows. Module-owned dynamic records are synthetic examples, explicitly labeled in the private preview; no live module records are queried or copied.

Security properties retained:

- `modify cms/page` authorization and CSRF remain mandatory.
- The system key is read from the saved Page record; the client cannot choose a route.
- The server validates the section list against that route's allowlist and validates the BDRE document before rendering.
- The private response remains no-store, no-frame, CSP-restricted, and POST-only. Links are inert and forms are blocked by CSP.
- Three.js custom JavaScript is removed from preview documents. No draft is published, no page is saved, and there is no schema change or migration.

## Apply

From the repository root, place the patch file there and run:

```sh
patch --dry-run -p1 < BDREditor-Catalog-Preview.patch
patch -p1 < BDREditor-Catalog-Preview.patch
```

The Bash helper from the previous step can also stage/preflight and apply this patch:

```sh
./apply-patches.sh --dry-run BDREditor-Catalog-Preview.patch
./apply-patches.sh --apply BDREditor-Catalog-Preview.patch
```

## Verification limits

`node --check` was run for the updated preview launcher. The patch dry-run is checked against the selectively downloaded HEAD baseline. PHP CLI and the application runtime are unavailable here, so PHP lint and rendered Catalog/Twig preview behavior remain to be verified in the user's development environment. No GitHub write, push, deployment, database write, or migration was performed.
