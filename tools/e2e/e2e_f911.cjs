#!/usr/bin/env node
'use strict';
// Static contract check only: no PHP/JS runtime, browser, DB, or conversion.
const fs=require('fs'),path=require('path');const root=path.resolve(__dirname,'..','..');
function fail(m){console.error('FAIL F9-11: '+m);process.exit(1)}
function read(p){const f=path.join(root,p);if(!fs.existsSync(f))fail('missing '+p);return fs.readFileSync(f,'utf8')}
const c=JSON.parse(read('docs/F9-11-Contrato-Editor-Blocos-Blog.json'));
const plan=read('docs/F9-11-Editor-de-Blocos-Blog.md');const adr=read('agents-ia/decisoes/0067-f9-11-editor-de-blocos-blog-contrato.md');
if(c.task!=='F9-11'||c.status!=='requirements_and_architecture_contract_only')fail('task/status mismatch');
if(c.product.implementation!=='native ReamurCMS editor; no WordPress runtime or package dependency')fail('implementation must be native, no WP runtime');
if(c.product.module_scope!=='blog/blog'||c.product.cms_page_scope!=='unchanged')fail('scope drift');
if(c.product.runtime_dependency_policy!=='no external CDN or third-party runtime dependency')fail('runtime dependency policy missing');
for(const key of ['paragraph','heading','list','image','quote','video_embed','button','separator','group','columns'])if(!c.initial_blocks.includes(key))fail('MVP block missing: '+key);
if(JSON.stringify(c.layout_contract.column_presets)!==JSON.stringify([2,3])||c.layout_contract.mobile_behavior!=='stack_columns')fail('advanced responsive layouts missing');
for(const key of ['per_language_content','block_tree','nested_blocks','inspector_panel','block_inserter','undo_redo','responsive_layout'])if(c.editor[key]!==true)fail('editor feature missing: '+key);
if(c.legacy_conversion.on_edit!=='auto-convert in memory and show review/preview')fail('legacy edit conversion contract mismatch');
if(c.legacy_conversion.persist!=='only after explicit successful save')fail('conversion must not persist on open');
if(!c.legacy_conversion.original_source.includes('recoverable'))fail('legacy source recovery requirement missing');
if(c.security.arbitrary_inline_script!==false||c.security.arbitrary_style!==false||c.layout_contract.custom_html_block!==false)fail('unsafe arbitrary content is not disabled');
if(c.migration.schema_change_authorized!==false||c.migration.database_execution_authorized!==false||c.migration.backup_required!==true)fail('migration must remain unexecuted and backup-gated');
const planLower=plan.toLowerCase();
for(const phrase of ['`rms_blog_post_description.description`','|raw','f9-12','nenhum código de runtime','nenhum sql'])if(!planLower.includes(phrase.toLowerCase()))fail('plan lacks required context: '+phrase);
if(!adr.toLowerCase().includes('salvar explicitamente')||!adr.toLowerCase().includes('silenciosamente'))fail('ADR misses legacy safety decisions');
console.log('PASS F9-11: native block-editor scope, layout, conversion safety, and no-migration boundaries validated');
