#!/usr/bin/env node
'use strict';
// Static check for ACL-driven custom-fields navigation. No PHP/browser/network.
const fs=require('fs'),path=require('path');const root=path.resolve(__dirname,'..','..');
function fail(m){console.error('FAIL F9-09: '+m);process.exit(1)}
function read(p){const f=path.join(root,p);if(!fs.existsSync(f))fail('missing '+p);return fs.readFileSync(f,'utf8')}
function has(s,r,m){if(!r.test(s))fail(m)}
const h=read('dev/admin/controller/common/header.php');
const route="['route' => 'custom_field/definition'";
if(h.split(route).length!==2)fail('navigation route must appear exactly once');
has(h,/text_group_content[\s\S]{0,1200}custom_field\/definition/,'custom fields item should be in Content group');
has(h,/custom_field\/definition',[^\n]*bi-ui-checks-grid[^\n]*text_menu_custom_fields/,'menu route/icon/translation key mismatch');
has(h,/\$this->user->hasPermission\('access',\s*\$item\['route'\]\)/,'menu must retain ACL access filtering');
has(h,/str_starts_with\(\$current,\s*\$item\s*\.\s*'\.'/,'subroute active-state behavior missing');
for(const [lang,label] of [['en-us','Custom Fields'],['pt-br','Campos personalizados'],['es-eu','Campos personalizados']]){
 const s=read(`dev/admin/language/${lang}/common/header.php`);
 if(!s.includes("$_['text_menu_custom_fields'] = '"+label+"';"))fail(`missing ${lang} menu label`);
}
if(h.includes("hasPermission('modify', 'custom_field/definition')") && /hasPermission\('modify',\s*\$item\['route'\]\)/.test(h))fail('menu display must not be coupled to modify capability');
console.log('PASS F9-09: menu link points to existing route and remains ACL-filtered, with three translations');
