#!/usr/bin/env node
'use strict';
// Structural gate for the stitched F9 custom-field CMS/page vertical slice.
// The shell runner executes the previous task gates; this validates docs/schema.
const fs=require('fs'),path=require('path');
const rootArg=process.argv.indexOf('--root');
const root=path.resolve(rootArg>=0?(process.argv[rootArg+1]||''):path.resolve(__dirname,'..','..'));
function fail(m){console.error('FAIL F9-10: '+m);process.exit(1)}
function read(rel){const p=path.join(root,rel);if(!fs.existsSync(p))fail('missing '+rel);return fs.readFileSync(p,'utf8')}
const dict=read('docs/Dicionario-de-Dados.md');
const schema=read('dev/install/sql/upgrade_f9_02_custom_fields_schema.sql');
const expected=['rms_custom_field_group','rms_custom_field_group_description','rms_custom_field','rms_custom_field_description','rms_custom_field_value'];
const declared=[...schema.matchAll(/CREATE TABLE IF NOT EXISTS `([^`]+)`/g)].map(m=>m[1]);
if(JSON.stringify(declared)!==JSON.stringify(expected))fail('F9-02 schema table set/order mismatch');
for(const name of expected){const canonical=name.replace(/^rms_/,'');if(!dict.includes('`'+canonical+'`'))fail('data dictionary missing '+canonical);}
for(const rel of ['tools/e2e/e2e_f902.sh','tools/e2e/e2e_f905.sh','tools/e2e/e2e_f906.sh','tools/e2e/e2e_f907.sh','tools/e2e/e2e_f908.sh','tools/e2e/e2e_f909.sh'])read(rel);
if(!dict.includes('language_id=0')||!dict.includes('DECIMAL(38,6)')||!dict.includes("visibility='public'"))fail('dictionary omits value locale/type/visibility contracts');
console.log('PASS F9-10: dictionary matches the five F9-02 tables and vertical-slice gates exist; no DB connection');
