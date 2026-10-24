#!/usr/bin/env node
'use strict';
// Static source triage only. Reads files as text; never starts PHP, SQL, CMS or a browser.
const fs = require('node:fs');
const path = require('node:path');
const root = process.argv[2] && path.resolve(process.argv[2]);
if (!root || !fs.existsSync(root)) {
  console.error('Usage: node e2e_f918_preflight.cjs <path-to-DavidCMS-checkout>');
  process.exit(2);
}
const findings = [];
function read(rel) {
  const file = path.join(root, rel);
  if (!fs.existsSync(file)) { findings.push({ id: 'INPUT', level: 'ERROR', text: `missing source ${rel}` }); return ''; }
  return fs.readFileSync(file, 'utf8');
}
const publicModel = read('dev/catalog/model/blog/blog.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const adminModel = read('dev/admin/model/blog/blog.php');
const publicDetectionStart = publicModel.indexOf('private function blockContentTableExists');
const publicDetectionEnd = publicModel.indexOf('private function tablesExist', publicDetectionStart);
const detection = publicDetectionStart >= 0 && publicDetectionEnd > publicDetectionStart ? publicModel.slice(publicDetectionStart, publicDetectionEnd) : '';
if (/catch\s*\(\\Throwable\s+\$e\)\s*\{[^}]*\$cache\s*=\s*false/s.test(detection) || /\$cache\s*=\s*\(\$query\s+instanceof\\?\\stdClass\s*&&/.test(detection)) {
  findings.push({ id: 'F918-01', level: 'P1', text: 'Public sidecar metadata failure may be treated as table absence; legacy raw HTML fallback can bypass renderer.' });
}
const imageCase = renderer.slice(renderer.indexOf("case 'core/image':"), renderer.indexOf("case 'core/video_embed':"));
if (imageCase && !/resolveImage|catalogAssetBase|assetBase|absoluteImage/.test(imageCase + renderer.slice(0, 500))) {
  findings.push({ id: 'F918-02', level: 'P1', text: 'Renderer appears to emit local image paths without a trusted catalog base resolver.' });
}
if (/CASE\s+WHEN\s+NULLIF\(pd\.`description`,\s*''\)\s+IS\s+NOT\s+NULL\s+THEN\s+pd\.`language_id`\s+ELSE\s+pd_en\.`language_id`\s+END\s+AS\s+`content_language_id`/i.test(publicModel)) {
  findings.push({ id: 'F918-03', level: 'P2', text: 'Public language selection still keys block language off nonempty legacy HTML description.' });
}
const deleteStart = adminModel.indexOf('public function deletePost');
const deleteEnd = adminModel.indexOf('// ─── internos', deleteStart);
const deleteMethod = deleteStart >= 0 && deleteEnd > deleteStart ? adminModel.slice(deleteStart, deleteEnd) : '';
if (deleteMethod && !/START TRANSACTION/.test(deleteMethod)) {
  findings.push({ id: 'F918-04', level: 'P2', text: 'Post/sidecar/description/SEO deletion has no transaction guard.' });
}
if (findings.length) {
  console.error('F9-18 STATIC READINESS: BLOCKED');
  for (const f of findings) console.error(` - ${f.id} [${f.level}] ${f.text}`);
  process.exit(1);
}
console.log('F9-18 STATIC READINESS: no known source-pattern blockers detected. This is not a runtime or security test.');
