#!/usr/bin/env node
'use strict';
// F9-07 static integration contract; this script never connects to DB or PHP.
const fs=require('fs'),path=require('path');const root=path.resolve(__dirname,'..','..');
function fail(m){console.error('FAIL F9-07: '+m);process.exit(1)}function read(p){const f=path.join(root,p);if(!fs.existsSync(f))fail('missing '+p);return fs.readFileSync(f,'utf8')}function has(s,re,m){if(!re.test(s))fail(m)}
const m=read('dev/admin/model/custom_field/value.php'),c=read('dev/admin/controller/cms/page.php'),t=read('dev/admin/view/template/cms/page_form.twig'),l=read('dev/admin/language/en-us/cms/page.php');
has(m,/namespace Reamur\\Admin\\Model\\Custom_field;/,'value model namespace incorrect');has(m,/class Value extends \\Reamur\\System\\Engine\\Model/,'value model class incorrect');
for(const method of ['getPageFields','getActiveLanguages','getPageValues','validateValue','savePageValues','deletePageValues'])has(m,new RegExp('function '+method+'\\('),'missing value adapter method '+method);
has(m,/ENTITY = 'cms\/page'/,'entity whitelist missing');has(m,/g\.`status`='1'/,'inactive field groups must not be rendered');has(m,/WHERE f\.`entity_type`='"\.self::ENTITY\."'/,'field lookup must be entity scoped');
has(m,/ON DUPLICATE KEY UPDATE/,'value save must upsert by unique entity/field/language');has(m,/START TRANSACTION/,'multi-value writes should be transactional');has(m,/ROLLBACK/,'write failure must rollback value batch');
has(m,/DELETE FROM[^\n]*custom_field_value[^\n]*entity_id/,'page deletion must scope value cleanup');
for(const type of ['number','boolean','url','select'])has(m,new RegExp("type==='"+type+"'"),'validation missing for '+type);
if(!m.includes('{1,32}')||!m.includes('{1,6}'))fail('number validation should fit DECIMAL(38,6)');
has(c,/load->model\('custom_field\/value'\)/,'page controller does not load value adapter');has(c,/savePageValues\(/,'page add/edit does not persist custom fields');has(c,/deletePageValues\(/,'page deletion does not clean up values');has(c,/validateValue\(/,'page form validation does not validate typed values');
has(c,/\$data\['custom_field_groups'\]/,'page form data missing custom field group data');
has(t,/name="custom_field\[\{\{ field\.id \}\}\]\[\{\{ item\.language_id \}\}\]"/,'page form fields not posted with field/language IDs');
for(const type of ['textarea','select','boolean','number','url'])has(t,new RegExp("field.type == '"+type+"'"),'template input control missing: '+type);
has(t,/text_custom_fields_note/,'privacy/behavior notice missing');
for(const k of ['error_custom_field_required','error_custom_field_number','error_custom_field_boolean','error_custom_field_url','error_custom_field_select','error_custom_field_save','error_custom_field_delete'])has(l,new RegExp("\\['"+k+"'\\]"),'language string missing '+k);
if(/catalog\/(?:controller|model|view)/.test(JSON.stringify(fs.readdirSync(path.join(root,'dev')))))fail('catalog files must not be part of F9-07');
console.log('PASS F9-07: page admin fields, type validation, scoped value adapter, lifecycle cleanup; no public runtime');
