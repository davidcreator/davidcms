#!/usr/bin/env node
'use strict';
// F9-06 static route/controller/template contract only; never runs PHP/SQL/DB.
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..','..');
let modelPath=path.join(root,'dev/admin/model/custom_field/definition.php');
const modelArg=process.argv.indexOf('--model');
if(modelArg>=0){const v=process.argv[modelArg+1];if(!v) {console.error('Uso: e2e_f906.cjs --check [--model <caminho> ]');process.exit(2);}modelPath=path.resolve(v);}
function fail(m){console.error('FAIL F9-06: '+m);process.exit(1)}
function read(p){const f=path.join(root,p);if(!fs.existsSync(f))fail('missing '+p);return fs.readFileSync(f,'utf8')}
function has(s,re,m){if(!re.test(s))fail(m)}
const c=read('dev/admin/controller/custom_field/definition.php');
const list=read('dev/admin/view/template/custom_field/definition_list.twig');
const form=read('dev/admin/view/template/custom_field/definition_form.twig');
const lang=read('dev/admin/language/en-us/custom_field/definition.php');
if(!fs.existsSync(modelPath))fail('F9-05 Model dependency missing at '+modelPath+' (apply F9-05 first or pass --model)');
const model=fs.readFileSync(modelPath,'utf8');
for(const method of ['index','add_group','edit_group','delete_group','add_field','edit_field','delete_field'])has(c,new RegExp('function '+method+'\\('),'controller method missing '+method);
has(c,/namespace Reamur\\Admin\\Controller\\Custom_field;/,'namespace route mismatch');
has(c,/private const ROUTE = 'custom_field\/definition'/,'route mismatch');
has(c,/hasPermission\('modify',\s*self::ROUTE\)/,'modify ACL check missing');
has(c,/Csrf::check\(/,'CSRF validation missing');
has(c,/REQUEST_METHOD/,'POST verb guard missing');
has(c,/validateGroup\(/,'group validation missing');has(c,/validateField\(/,'field validation missing');
has(c,/fieldExists\(/,'field key uniqueness check missing');has(c,/groupExists\(/,'group key uniqueness check missing');
has(c,/text_field_has_values/,'delete-with-values message missing');
has(c,/deleteField\(/,'field delete guard not delegated to Model');has(c,/deleteGroup\(/,'group delete guard not delegated to Model');
if(/load->controller\(['"]catalog\//.test(c)||/public function (?:render|display)/.test(c))fail('public rendering/runtime integration is out of scope');
if(/custom_field_value.{0,80}(?:INSERT|UPDATE|DELETE)/is.test(c))fail('controller may not write custom field values');
for(const f of ['csrf_token','method="post"','group_description','field_description'])has(list+form,new RegExp(f.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'i'),'form/security token missing: '+f);
for(const k of ['error_csrf','error_key','error_duplicate_key','error_en_label','error_group','error_visibility','text_field_has_values','text_group_has_fields'])has(lang,new RegExp("\\['"+k+"'\\]"),'language key missing '+k);
has(form,/name="key"/,'stable key inputs missing');has(form,/name="visibility"/,'visibility control missing');has(form,/name="options\[value\]\[\]"/,'select option values missing');
has(form,/readonly/,'immutable edit keys should be read-only');
if(!form.includes('{% if not key %}') || !form.includes('name="localized"'))fail('localized should be selectable only at creation time');
if(!c.includes("'localized'=>(int)($field['localized']??($p['localized']??0))"))fail('localized value is not preserved/accepted for creation');
if(/SELECT\s+[^;]*custom_field_value/i.test(c))fail('controller should not access values table directly');
if(!model.includes("private const ALLOWED_ENTITIES = ['cms/page'];"))fail('F9-05 Model dependency not found or scope changed');
console.log('PASS F9-06: controller, admin forms, ACL/CSRF contracts; no public runtime or value writes');
