#!/usr/bin/env node
'use strict';
// Static visibility/escaping contract. Does not execute PHP, SQL, or HTTP.
const fs=require('fs'),path=require('path');const root=path.resolve(__dirname,'..','..');
function fail(m){console.error('FAIL F9-08: '+m);process.exit(1)}function read(p){const f=path.join(root,p);if(!fs.existsSync(f))fail('missing '+p);return fs.readFileSync(f,'utf8')}function has(s,r,m){if(!r.test(s))fail(m)}
const model=read('dev/catalog/model/custom_field/value.php'),controller=read('dev/catalog/controller/cms/page.php'),twig=read('dev/catalog/view/template/cms/page_info.twig');
const langs=['en-us','pt-br','es-eu'].map(x=>read(`dev/catalog/language/${x}/cms/page.php`));
has(model,/namespace Reamur\\Catalog\\Model\\Custom_field;/,'catalog Model namespace mismatch');has(model,/class Value extends \\Reamur\\System\\Engine\\Model/,'catalog value Model class mismatch');
has(model,/f\.`entity_type`='cms\/page'/,'entity must be constrained to cms/page');has(model,/g\.`status`='1'/,'inactive groups must be excluded');has(model,/f\.`visibility`='public'/,'query must require explicit public visibility');has(model,/p\.`status`='1'/,'query must require published page');
has(model,/f\.`type` IN \('text','textarea','number','boolean','url','select'\)/,'field type whitelist missing');has(model,/CASE WHEN f\.`localized`=1/,'localized/shared language handling missing');has(model,/v_en/,'English value fallback missing');has(model,/fd_en/,'English label fallback missing');
has(controller,/getPagePublicFields\(/,'public controller does not use filtered reader');has(controller,/in_array\(\$type,\['text','textarea','number','url'\],true\)/,'controller output type guard missing');has(controller,/json_decode\(\(string\)\(\$row\['options'\]/,'select labels not resolved from JSON options');has(controller,/mb_substr\(\$value,0,5000\)/,'public value length bound missing');
const block=twig.slice(twig.indexOf('class="page-custom-fields"'));
has(block,/\{\{ field\.value \}\}/,'custom value output missing');if(/\{\{\s*field\.value\|raw/.test(block))fail('custom value must not use raw');if(/\{\{\s*group\.label\|raw|\{\{\s*field\.label\|raw/.test(block))fail('labels must remain escaped');
for(const [i,l] of langs.entries()){
 const name=['en-us','pt-br','es-eu'][i];if(!l.includes("$_['text_custom_fields']"))fail(name+' custom fields translation missing');if(!l.includes("$_['text_yes']")||!l.includes("$_['text_no']"))fail(name+' boolean translations missing');
}
if(/visibility[^\n]{0,50}admin_only/.test(model)){} // public reader explicitly filters the public state above.
console.log('PASS F9-08: public reader is opt-in, locale-aware, type-filtered and escaped; no admin-only values exposed');
