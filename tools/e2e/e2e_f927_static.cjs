#!/usr/bin/env node
'use strict';
const fs=require('fs');const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const errors=[];const need=(ok,msg)=>{if(!ok)errors.push(msg)};
function json(rel){try{return JSON.parse(fs.readFileSync(path.join(root,rel),'utf8'));}catch(e){errors.push(`${rel}: ${e.message}`);return null;}}
const status=json('docs/F9-27-Status.json');
const fixture=json('tests/fixtures/blog_editor_f927_synthetic.json');
const hashes=json('docs/F9-27-Base-Hashes.json');
need(status&&status.status==='BLOCKED_NOT_RUN','release gate must remain BLOCKED_NOT_RUN');
need(status&&status.f9_12_status==='UNKNOWN_NOT_CONFIRMED','F9-12 must not be presumed');
need(status&&status.runtime_tests_executed===false&&status.database_accessed===false&&status.migration_applied===false,'runtime/DB status must remain false');
need(status&&status.release_approved===false&&status.github_write_or_deploy===false,'release cannot be approved or published by a static checker');
need(status&&Array.isArray(status.required_before_execution)&&status.required_before_execution.length>=4,'required preconditions incomplete');
need(Array.isArray(fixture)&&fixture.length>=5,'synthetic E2E fixture matrix incomplete');
if(Array.isArray(fixture)){
 const ids=new Set();for(const f of fixture){need(typeof f.id==='string'&&!ids.has(f.id),`fixture id missing/duplicate: ${f.id}`);ids.add(f.id);need(!/real|production|customer|private|credential|token/i.test(f.id),`non-synthetic identifier in fixture: ${f.id}`);}
 const images=JSON.stringify(fixture);
 need(!/bd-davidalmeida\.sql|localhost|127\.0\.0\.1|password\s*[:=]|token\s*[:=]/i.test(images),'fixture must not include secrets, private dump names or local targets');
}
need(hashes&&hashes.head===status.base_head&&Array.isArray(hashes.relevant_point_sources)&&hashes.relevant_point_sources.length>=5,'base SHA manifest incomplete');
const plan=fs.readFileSync(path.join(root,'docs/F9-27-Release-Gate.md'),'utf8');
for(const marker of ['BLOQUEADO','F9-12','ABORTAR','NOT RUN','credenciais','descartável'])need(plan.toLowerCase().includes(marker.toLowerCase()),`release plan missing ${marker}`);
if(errors.length){console.error('F9-27 STATIC RELEASE-GATE CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-27 STATIC RELEASE-GATE CHECK: PASS — ${fixture.length} synthetic scenarios; F9-12 unknown; runtime/DB/deploy not run.`);
