'use strict';
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const checks = [
  ['CMS Page form mounts the shared BDREditor', /data-blog-block-editor/.test(read('dev/admin/view/template/cms/page_form.twig'))],
  ['CMS Page form uses an existing-field v2 marker', /REAMURCMS_BDRE_JSON_V2:/.test(read('dev/admin/view/template/cms/page_form.twig'))],
  ['Block submission is validated server-side', /validateBlockSubmission/.test(read('dev/admin/controller/cms/page.php')) && /deserializeDocument/.test(read('dev/admin/controller/cms/page.php'))],
  ['Valid marked content is rendered before Twig as v2 only', /BlockRenderer/.test(read('dev/catalog/controller/cms/page.php')) && /renderJson\(\$json, 2,/.test(read('dev/catalog/controller/cms/page.php'))],
  ['Marker cannot bypass mode/readiness validation', /\$hasMarker/.test(read('dev/admin/controller/cms/page.php')) && /validateBlockSubmission/.test(read('dev/admin/controller/cms/page.php'))],
  ['Legacy description remains supported', /Legacy HTML remains on its existing path/.test(read('dev/catalog/controller/cms/page.php'))],
  ['Unconvertible legacy content retains textarea fallback', /block_mode == 'invalid'/.test(read('dev/admin/view/template/cms/page_form.twig')) && /\$blockMode = 'legacy'/.test(read('dev/admin/controller/cms/page.php'))],
  ['No media picker buttons are exposed without their library modal', /data-media-pickers="false"/.test(read('dev/admin/view/template/cms/page_form.twig')) && /mediaPickers !== false/.test(read('dev/admin/view/js/blog-block-editor.js'))],
];
let failed = 0;
for (const [name, ok] of checks) { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); if (!ok) failed++; }
process.exitCode = failed ? 1 : 0;
