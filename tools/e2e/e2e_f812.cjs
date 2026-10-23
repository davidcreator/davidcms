#!/usr/bin/env node
'use strict';
/** F8-12 — consolidação local e revisão de evidências. Sem runtime/app/DB/HTTP.
 * Whitelist só --check das entradas05..11. Não instala, loga, grava dados do CMS,
 * repete fluxos mutáveis ou encerra fase/deploy. JSON não é atestado criptográfico.
 */
const fs=require('node:fs');const path=require('node:path');const crypto=require('node:crypto');const cp=require('node:child_process');const os=require('node:os');const assert=require('node:assert/strict');
const BASE='7301dc143ab5c936b44ce9efc7172b23c1472b0b';
const ROOT=path.resolve(__dirname,'../..');
const INPUTS=Object.freeze([
  "dev/admin/controller/catalog/category.php",
  "dev/admin/controller/catalog/project.php",
  "dev/admin/controller/cms/page.php",
  "dev/admin/controller/common/header.php",
  "dev/admin/controller/communication/contact_message.php",
  "dev/admin/controller/localization/language.php",
  "dev/admin/controller/tool/tool.php",
  "dev/admin/language/en-us/catalog/category.php",
  "dev/admin/language/en-us/catalog/project.php",
  "dev/admin/language/en-us/cms/page.php",
  "dev/admin/language/en-us/communication/contact_message.php",
  "dev/admin/language/en-us/localization/language.php",
  "dev/admin/language/en-us/tool/tool.php",
  "dev/admin/language/es-eu/catalog/category.php",
  "dev/admin/language/es-eu/catalog/project.php",
  "dev/admin/language/es-eu/cms/page.php",
  "dev/admin/language/es-eu/communication/contact_message.php",
  "dev/admin/language/es-eu/localization/language.php",
  "dev/admin/language/es-eu/tool/tool.php",
  "dev/admin/language/pt-br/catalog/category.php",
  "dev/admin/language/pt-br/catalog/project.php",
  "dev/admin/language/pt-br/cms/page.php",
  "dev/admin/language/pt-br/communication/contact_message.php",
  "dev/admin/language/pt-br/localization/language.php",
  "dev/admin/language/pt-br/tool/tool.php",
  "dev/admin/model/catalog/category.php",
  "dev/admin/model/catalog/project.php",
  "dev/admin/model/cms/page.php",
  "dev/admin/model/communication/contact_message.php",
  "dev/admin/model/localization/language.php",
  "dev/admin/model/tool/tool.php",
  "dev/admin/view/css/admin.css",
  "dev/admin/view/js/admin.js",
  "dev/admin/view/js/blog-media.js",
  "dev/admin/view/js/catalog-category.js",
  "dev/admin/view/js/catalog-project.js",
  "dev/admin/view/js/cms-page.js",
  "dev/admin/view/js/connected-tools.js",
  "dev/admin/view/template/blog/blog_form.twig",
  "dev/admin/view/template/catalog/category_form.twig",
  "dev/admin/view/template/catalog/category_list.twig",
  "dev/admin/view/template/catalog/project_form.twig",
  "dev/admin/view/template/catalog/project_list.twig",
  "dev/admin/view/template/cms/page_form.twig",
  "dev/admin/view/template/cms/page_list.twig",
  "dev/admin/view/template/communication/contact_message_list.twig",
  "dev/admin/view/template/communication/contact_message_read.twig",
  "dev/admin/view/template/localization/language_form.twig",
  "dev/admin/view/template/localization/language_list.twig",
  "dev/admin/view/template/tool/tool_form.twig",
  "dev/admin/view/template/tool/tool_list.twig",
  "dev/system/engine/controller.php",
  "dev/system/engine/model.php",
  "dev/system/engine/registry.php",
  "dev/system/library/media_upload.php",
  "dev/system/library/pagination.php",
  "dev/system/library/security/csrf.php",
  "dev/system/library/session.php",
  "dev/system/storage/composer.lock",
  "dev/tests/e2e/e2e_f213.sh",
  "dev/tests/e2e/e2e_f215.sh",
  "dev/tests/e2e/e2e_regressao.sh",
  "tools/e2e/e2e_f402.sh",
  "tools/e2e/e2e_f701.sh",
  "tools/e2e/e2e_f805.cjs",
  "tools/e2e/e2e_f805.sh",
  "tools/e2e/e2e_f805_a01_blog_media.sh",
  "tools/e2e/e2e_f805_a01_blog_media_dom.cjs",
  "tools/e2e/e2e_f805_a02_ckeditor.sh",
  "tools/e2e/e2e_f805_a02_ckeditor_dom.cjs",
  "tools/e2e/e2e_f805_browser.js",
  "tools/e2e/e2e_f806.sh",
  "tools/e2e/e2e_f806_contracts.php",
  "tools/e2e/e2e_f806_dom.cjs",
  "tools/e2e/e2e_f806_http.cjs",
  "tools/e2e/e2e_f807.sh",
  "tools/e2e/e2e_f807_contracts.php",
  "tools/e2e/e2e_f807_dom.cjs",
  "tools/e2e/e2e_f807_http.cjs",
  "tools/e2e/e2e_f808.sh",
  "tools/e2e/e2e_f808_contracts.php",
  "tools/e2e/e2e_f808_dom.cjs",
  "tools/e2e/e2e_f808_http.cjs",
  "tools/e2e/e2e_f809.sh",
  "tools/e2e/e2e_f809_contracts.php",
  "tools/e2e/e2e_f809_dom.cjs",
  "tools/e2e/e2e_f809_http.cjs",
  "tools/e2e/e2e_f810.sh",
  "tools/e2e/e2e_f810_contracts.php",
  "tools/e2e/e2e_f810_http.cjs",
  "tools/e2e/e2e_f811.sh",
  "tools/e2e/e2e_f811_contracts.php",
  "tools/e2e/e2e_f811_http.cjs",
  "tools/e2e/e2e_f812.cjs",
  "tools/e2e/e2e_f812.sh"
]);
const CHECK_IDS=Object.freeze(["shell-login", "dashboard", "blog-rte-cover", "blog-import", "portfolio", "cms", "tools", "contact", "languages", "users-groups", "settings-site", "marketplace-ui", "webhooks-ui", "themes-responsive", "i18n", "acl-csrf", "invariants-cleanup", "public-read-smoke", "assets-console", "debts-privacy"]);
const SUITES=Object.freeze([
  {
    "task": "F8-05",
    "script": "tools/e2e/e2e_f805.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-06",
    "script": "tools/e2e/e2e_f806.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-07",
    "script": "tools/e2e/e2e_f807.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-08",
    "script": "tools/e2e/e2e_f808.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-09",
    "script": "tools/e2e/e2e_f809.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-10",
    "script": "tools/e2e/e2e_f810.sh",
    "args": [
      "--check"
    ]
  },
  {
    "task": "F8-11",
    "script": "tools/e2e/e2e_f811.sh",
    "args": [
      "--check"
    ]
  }
]);
const NATIVE=Object.freeze({
  "F8-05": {
    "kind": "native-browser-workflow",
    "steps": 14,
    "task": "Fase 8 / Tarefa 05"
  },
  "F8-06": {
    "kind": "http-workflow",
    "steps": 10,
    "task": "Fase 8 / Tarefa 06"
  },
  "F8-07": {
    "kind": "http-workflow",
    "steps": 14,
    "task": "Fase 8 / Tarefa 07"
  },
  "F8-08": {
    "kind": "http-workflow",
    "steps": 15,
    "task": "Fase 8 / Tarefa 08"
  },
  "F8-09": {
    "kind": "http-workflow",
    "steps": 13,
    "task": "Fase 8 / Tarefa 09"
  },
  "F8-10": {
    "kind": "http-workflow",
    "steps": 12,
    "task": "Fase 8 / Tarefa 10"
  },
  "F8-11": {
    "kind": "http-workflow",
    "steps": 10,
    "task": "Fase 8 / Tarefa 11"
  }
});
const REQUIRED=Object.freeze({
 'F8-05':['32 PASS / 0 FAIL','18 PASS / 0 FAIL','10 pass / 0 fail'],
 'F8-06':['30 PASS / 0 FAIL','6 PASS / 0 FAIL','16 PASS / 0 FAIL'],
 'F8-07':['40 PASS / 0 FAIL','9 PASS / 0 FAIL','16 PASS / 0 FAIL'],
 'F8-08':['45 PASS / 0 FAIL','12 PASS / 0 FAIL','10 PASS / 0 FAIL','9 PASS / 0 FAIL','16 PASS / 0 FAIL'],
 'F8-09':['36 PASS / 0 FAIL','9 PASS / 0 FAIL','8 PASS / 0 FAIL','16 PASS / 0 FAIL'],
 'F8-10':['40 PASS / 0 FAIL','8 PASS / 0 FAIL','16 PASS / 0 FAIL'],
 'F8-11':['40 PASS / 0 FAIL','8 PASS / 0 FAIL','16 PASS / 0 FAIL']
});
class GateError extends Error{constructor(code){super(code);this.code=code;}}
function need(condition,code){if(!condition)throw new GateError(code);}
const hash=bytes=>crypto.createHash('sha256').update(bytes).digest('hex');
function parseArgs(argv){if(argv.length===0)return{mode:'--check'};if(argv.length===1&&['--check','--self-test','--help','-h'].includes(argv[0]))return{mode:argv[0]==='-h'?'--help':argv[0]};if(argv.length===2&&argv[0]==='--review')return{mode:'--review',file:argv[1]};throw new GateError('CLI_MODE_NOT_ALLOWED');}
function safeFile(root,relative){need(typeof relative==='string'&&/^[A-Za-z0-9_./-]+$/.test(relative)&&!relative.split('/').includes('..')&&!path.isAbsolute(relative),'INPUT_PATH_INVALID');const file=path.join(root,relative);try{need(fs.lstatSync(file).isFile()&&fs.realpathSync(file)===file,'INPUT_NOT_REGULAR');}catch(e){if(e instanceof GateError)throw e;throw new GateError('INPUT_MISSING');}return file;}
function fingerprint(root,list=INPUTS){need(new Set(list).size===list.length,'DUPLICATE_INPUT');const files=[...list].sort().map(relative=>({path:relative,sha256:hash(fs.readFileSync(safeFile(root,relative)))}));return{digest:hash(files.map(f=>f.path+'\0'+f.sha256+'\n').join('')),files};}
function suitePlan(root){return SUITES.map(s=>{safeFile(root,s.script);need(s.args.length===1&&s.args[0]==='--check','UNSAFE_SUITE_ARGUMENT');return{task:s.task,command:'bash',args:[path.join(root,s.script),'--check'],script:s.script};});}
function sameMirror(root,value){if(!value)return;let real;try{real=fs.realpathSync(value);}catch(e){throw new GateError('MIRROR_NOT_SAME_PROJECT');}need(real===path.join(root,'dev'),'MIRROR_NOT_SAME_PROJECT');}
function childEnv(root,env,phpBin){const clean={};for(const key of['PATH','HOME','USER','LOGNAME','LANG','LC_ALL','TMPDIR','TEMP','TMP','SYSTEMROOT','WINDIR'])if(typeof env[key]==='string')clean[key]=env[key];clean.MIRROR=path.join(root,'dev');clean.NODE_BIN=process.execPath;clean.PHP_BIN=phpBin;if(env.TWIG_AUTOLOAD)clean.TWIG_AUTOLOAD=env.TWIG_AUTOLOAD;return clean;}
function jsonFile(file,max=524288){need(typeof file==='string'&&file.toLowerCase().endsWith('.json')&&!/^https?:/i.test(file),'JSON_PATH_INVALID');try{const stat=fs.lstatSync(file);need(stat.isFile()&&!stat.isSymbolicLink()&&stat.size<=max,'JSON_FILE_INVALID');return JSON.parse(fs.readFileSync(file,'utf8'));}catch(e){if(e instanceof GateError)throw e;throw new GateError('JSON_UNREADABLE');}}
function newDestination(file){if(!file)return;need(path.isAbsolute(file)&&file.toLowerCase().endsWith('.json'),'REPORT_PATH_INVALID');try{let exists=false;try{fs.lstatSync(file);exists=true;}catch(e){if(e.code!=='ENOENT')throw e;}need(!exists&&fs.statSync(path.dirname(file)).isDirectory(),'REPORT_NOT_NEW');}catch(e){if(e instanceof GateError)throw e;throw new GateError('REPORT_NOT_NEW');}}
function writeReport(file,data){if(!file)return;fs.writeFileSync(file,JSON.stringify(data,null,2)+'\n',{flag:'wx',mode:0o600});}
// composer.lock não tem extensão JSON: leitura restrita à dependência conhecida.
function twigLock(root){let lock;try{lock=JSON.parse(fs.readFileSync(safeFile(root,'dev/system/storage/composer.lock'),'utf8'));}catch(e){throw new GateError('TWIG_LOCK_UNREADABLE');}const p=(lock.packages||[]).find(p=>p.name==='twig/twig');need(p&&typeof p.version==='string','TWIG_LOCK_MISSING');return p.version.replace(/^v/,'');}
function dependencyMatches(probe,version){return Boolean(probe&&Number(probe.phpMajor)>=8&&Number(probe.phpId)>=80100&&probe.mbstring===true&&probe.twig===version);}
function runtimeProbe(root,env,phpBin){const autoload=env.TWIG_AUTOLOAD||path.join(root,'dev/system/storage/vendor/autoload.php');need(path.isAbsolute(autoload),'AUTOLOAD_MUST_BE_ABSOLUTE');try{need(fs.lstatSync(autoload).isFile()&&!fs.lstatSync(autoload).isSymbolicLink(),'AUTOLOAD_MISSING');}catch(e){if(e instanceof GateError)throw e;throw new GateError('AUTOLOAD_MISSING');}
 const code='if(PHP_VERSION_ID<80100||!extension_loaded("mbstring")){exit(2);} require $argv[1]; if(!class_exists("Twig\\\\Environment")){exit(2);} echo json_encode(["php"=>PHP_VERSION,"phpMajor"=>PHP_MAJOR_VERSION,"phpId"=>PHP_VERSION_ID,"mbstring"=>true,"twig"=>Twig\\Environment::VERSION]);';
 const run=cp.spawnSync(phpBin,['-r',code,autoload],{cwd:root,env:childEnv(root,env,phpBin),encoding:'utf8',timeout:15000,maxBuffer:262144,shell:false});let probe;try{probe=JSON.parse(run.stdout||'');}catch(e){throw new GateError('PHP_TWIG_PROBE_BLOCKED');}need(run.status===0&&!run.error,'PHP_TWIG_PROBE_BLOCKED');const expected=twigLock(root);need(dependencyMatches(probe,expected),'TWIG_LOCK_RUNTIME_MISMATCH');return{node:process.versions.node,php:probe.php,twig:probe.twig,twigLockVersion:expected,dependencyMatch:true,explicitQaAutoload:Boolean(env.TWIG_AUTOLOAD)};
}
function suiteOutcome(spec,run,durationMs){const stdout=typeof run.stdout==='string'?run.stdout:'';const required=REQUIRED[spec.task];const markers=required.filter(s=>stdout.includes(s));const errorCode=run.error?(run.error.code==='ETIMEDOUT'?'SUITE_TIMEOUT':'SUITE_PROCESS_ERROR'):run.status!==0?'SUITE_EXIT_NOT_ZERO':markers.length!==required.length?'EXPECTED_SIGNALS_MISSING':null;return{task:spec.task,script:spec.script,argument:'--check',status:errorCode?'failed':'passed',exitCode:Number.isInteger(run.status)?run.status:null,durationMs,summaryMarkers:markers,errorCode};}
function allPassed(suites){return suites.length===SUITES.length&&suites.every(s=>s.status==='passed'&&s.exitCode===0);}
function nativeEvidence(report,task){const spec=NATIVE[task];need(spec,'EVIDENCE_TASK_UNKNOWN');need(report&&report.kind===spec.kind&&report.task===spec.task,'EVIDENCE_KIND_OR_TASK_MISMATCH');need(report.status==='passed','EVIDENCE_NOT_PASSED');need(Array.isArray(report.steps)&&report.steps.length===spec.steps,'EVIDENCE_STEP_COUNT');need(report.steps.every(s=>s&&s.status==='pass'&&typeof s.label==='string'&&s.label.length>0),'EVIDENCE_STEP_NOT_PASS');need(new Set(report.steps.map(s=>s.label)).size===spec.steps,'EVIDENCE_DUPLICATE_STEP');need(report.cleanup==='complete'&&report.probeCleanup!=='pending','EVIDENCE_CLEANUP_PENDING');if(task==='F8-11'){need(report.operation==='nondefault-sort-and-restore'&&report.restoration==='complete'&&report.defaultsUnchanged===true&&report.languageCode!=='en-us','EVIDENCE_LANGUAGE_NOT_RESTORED');}return{task,method:'native-json',status:'accepted',kind:spec.kind,steps:spec.steps,independentlyVerified:false};}
function localEvidence(local,current){need(local&&local.kind==='local-contracts-batch'&&local.task==='Fase 8 / Tarefa 12','LOCAL_REPORT_KIND');need(local.status==='passed'&&local.sourceUnchanged===true,'LOCAL_REPORT_NOT_PASSED');need(local.sourceFingerprint===current&&/^[a-f0-9]{64}$/.test(current),'LOCAL_REPORT_SOURCE_CHANGED');need(local.inputCount===INPUTS.length,'LOCAL_REPORT_INPUT_COUNT');need(local.selfTests&&local.selfTests.failed===0&&local.selfTests.passed===32,'LOCAL_REPORT_GUARDS');need(local.runtimes&&local.runtimes.dependencyMatch===true,'LOCAL_REPORT_DEPENDENCIES');need(Array.isArray(local.suites)&&local.suites.length===SUITES.length&&new Set(local.suites.map(s=>s.task)).size===SUITES.length,'LOCAL_REPORT_SUITES');for(const spec of SUITES){const s=local.suites.find(s=>s.task===spec.task);need(s&&s.script===spec.script&&s.argument==='--check'&&s.status==='passed'&&s.exitCode===0,'LOCAL_REPORT_SUITE_FAILED');need(Array.isArray(s.summaryMarkers)&&REQUIRED[spec.task].every(m=>s.summaryMarkers.includes(m)),'LOCAL_REPORT_SIGNALS');}need(local.nativeWorkflowsExecuted===false,'LOCAL_REPORT_SCOPE');return true;}
function checklistShape(doc){need(doc&&doc.schema==='reamur-f8-acceptance-v1'&&['operator-checklist','operator-checklist-template'].includes(doc.kind),'CHECKLIST_SCHEMA');need(doc.referenceBase===BASE,'CHECKLIST_REFERENCE');need(Array.isArray(doc.checks)&&doc.checks.length===CHECK_IDS.length&&new Set(doc.checks.map(c=>c.id)).size===CHECK_IDS.length&&CHECK_IDS.every(id=>doc.checks.some(c=>c.id===id)),'CHECKLIST_CHECK_IDS');need(Array.isArray(doc.taskEvidence)&&doc.taskEvidence.length===SUITES.length&&new Set(doc.taskEvidence.map(e=>e.task)).size===SUITES.length&&SUITES.every(s=>doc.taskEvidence.some(e=>e.task===s.task)),'CHECKLIST_EVIDENCE_IDS');}
function assess(doc,local,current,evidence){checklistShape(doc);const issues=[];if(doc.kind!=='operator-checklist'||doc.status!=='passed'||doc.finalDecision!=='approved-for-phase-closure')issues.push('OPERATOR_DECISION_PENDING');if(!doc.environment||!['disposable-local','staging'].includes(doc.environment.kind)||doc.environment.syntheticData!==true)issues.push('ENVIRONMENT_NOT_CONFIRMED');for(const c of doc.checks)if(c.status!=='pass')issues.push('CHECK_PENDING:'+c.id);try{localEvidence(local,current);}catch(e){issues.push(e.code||'LOCAL_REPORT_INVALID');}const assurance=[];for(const item of doc.taskEvidence){try{need(item.status==='accepted','TASK_EVIDENCE_PENDING');if(item.method==='operator-confirmed'){need(!item.path&&typeof item.reference==='string'&&item.reference.trim().length>0,'ATTESTATION_REFERENCE_INVALID');assurance.push({task:item.task,method:'operator-confirmed',status:'accepted',independentlyVerified:false});}else{need(item.method==='native-json'&&evidence[item.task],'NATIVE_EVIDENCE_MISSING');const result=nativeEvidence(evidence[item.task],item.task);assurance.push(result);}}catch(e){issues.push((e.code||'EVIDENCE_INVALID')+':'+item.task);}}
 return{task:'Fase 8 / Tarefa 12',kind:'acceptance-review',referenceBase:BASE,status:issues.length?'pending':'ready-for-operator-closure',issues,assurance,independentlyVerified:false,requiresExplicitPhaseClosure:true,phaseClosed:false,nativeWorkflowsExecuted:false,doesNotAuthorizeDeploy:true,scope:'Revisão local/estrutural e declaração do operador; não certifica execução, deploy ou autenticidade do JSON.'};}
function linkedJson(checklistFile,value){need(typeof value==='string'&&value.trim()&&value.toLowerCase().endsWith('.json')&&!/^https?:/i.test(value),'EVIDENCE_JSON_PATH');const file=path.isAbsolute(value)?value:path.resolve(path.dirname(checklistFile),value);return{data:jsonFile(file),sha256:hash(fs.readFileSync(file))};}
async function check(root,env){newDestination(env.F812_REPORT);need(Number(process.versions.node.split('.')[0])>=20,'NODE_20_REQUIRED');sameMirror(root,env.MIRROR);const before=fingerprint(root);const plan=suitePlan(root);const phpBin=env.PHP_BIN||'php';const runtimes=runtimeProbe(root,env,phpBin);const guards=await selfTest(false);need(guards.failed===0,'CONSOLIDATOR_GUARDS_FAILED');const suites=[];const startedAt=new Date().toISOString();for(const spec of plan){const start=Date.now();const run=cp.spawnSync(spec.command,spec.args,{cwd:root,env:childEnv(root,env,phpBin),encoding:'utf8',timeout:90000,maxBuffer:2097152,shell:false});const out=suiteOutcome(spec,run,Date.now()-start);suites.push(out);process.stderr.write((out.status==='passed'?'PASS':'FAIL')+' local '+spec.task+' (--check)\n');}
 let unchanged=false;try{unchanged=fingerprint(root).digest===before.digest;}catch(e){unchanged=false;}const report={task:'Fase 8 / Tarefa 12',kind:'local-contracts-batch',referenceBase:BASE,status:allPassed(suites)&&unchanged?'passed':'failed',startedAt,finishedAt:new Date().toISOString(),runtimes,selfTests:guards,suites,inputCount:INPUTS.length,sourceFingerprint:before.digest,sourceUnchanged:unchanged,sourceFiles:before.files,nativeWorkflowsExecuted:false,phaseClosed:false,coverage:'Somente contratos locais das sete entradas F8-05..11; grupos compartilhados repetem e não devem ser somados como E2Es distintos.',notExecuted:['HTTP/DB da aplicação/navegador/SMTP/integrações/deploy','suítes legadas destrutivas','homologação visual/ACL de outro perfil/ambiente real','auditoria completa de toda a Fase8 ou system/vendor/catalog']};writeReport(env.F812_REPORT,report);return report;}
function review(root,env,file){newDestination(env.F812_REVIEW_REPORT);const absolute=path.resolve(file);const doc=jsonFile(absolute);checklistShape(doc);let local=null;const hashes={};if(doc.localReport){const entry=linkedJson(absolute,doc.localReport);local=entry.data;hashes.localReport=entry.sha256;}const evidence={};for(const item of doc.taskEvidence){if(item.method==='native-json'&&item.path){const entry=linkedJson(absolute,item.path);evidence[item.task]=entry.data;hashes[item.task]=entry.sha256;}}
 const result=assess(doc,local,fingerprint(root).digest,evidence);result.inputHashes=hashes;result.reviewedAt=new Date().toISOString();writeReport(env.F812_REVIEW_REPORT,result);return result;}
async function selfTest(verbose=true){let passed=0,failed=0;const temp=fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(),'f812-models-')));fs.mkdirSync(path.join(temp,'dev'),{mode:0o700});fs.writeFileSync(path.join(temp,'a.txt'),'alpha');fs.writeFileSync(path.join(temp,'b.txt'),'beta');const d='a'.repeat(64);
 const native=(task)=>{const s=NATIVE[task];return{task:s.task,kind:s.kind,status:'passed',steps:Array.from({length:s.steps},(_,i)=>({label:'modelo etapa '+i,status:'pass'})),cleanup:'complete',...(task==='F8-11'?{operation:'nondefault-sort-and-restore',restoration:'complete',defaultsUnchanged:true,languageCode:'pt-br'}:{})};};
 const local=()=>({task:'Fase 8 / Tarefa 12',kind:'local-contracts-batch',status:'passed',sourceUnchanged:true,sourceFingerprint:d,inputCount:INPUTS.length,selfTests:{passed:32,failed:0},runtimes:{dependencyMatch:true},nativeWorkflowsExecuted:false,suites:SUITES.map(s=>suiteOutcome(s,{status:0,stdout:REQUIRED[s.task].join('\n')},1))});
 const operator=(complete=false)=>({schema:'reamur-f8-acceptance-v1',kind:complete?'operator-checklist':'operator-checklist-template',status:complete?'passed':'pending',referenceBase:BASE,environment:{kind:complete?'disposable-local':null,syntheticData:complete?true:null},checks:CHECK_IDS.map(id=>({id,status:complete?'pass':'pending'})),taskEvidence:SUITES.map(s=>({task:s.task,method:'operator-confirmed',status:'accepted',path:null,reference:'Modelo de confirmação; não é execução real.'})),finalDecision:complete?'approved-for-phase-closure':'pending'});
 async function t(label,fn){try{await fn();passed++;if(verbose)console.log('PASS '+label);}catch(e){failed++;if(verbose)console.error('FAIL '+label+' ['+(e.code||'MODEL_ASSERTION')+']');}}
 try{
  await t('01 · CLI padrão só check',()=>{assert.equal(parseArgs([]).mode,'--check');assert.equal(parseArgs(['--review','x.json']).mode,'--review');});
  await t('02 · modos de gravação/backend/argumento extra recusados',()=>{for(const a of[['--http'],['--browser'],['--backend'],['--prepare-browser'],['--check','extra']])assert.throws(()=>parseArgs(a));});
  await t('03 · whitelist exata de sete entradas e único argumento check',()=>{assert.equal(SUITES.length,7);assert.deepEqual(SUITES.map(s=>s.task),['F8-05','F8-06','F8-07','F8-08','F8-09','F8-10','F8-11']);assert.ok(SUITES.every(s=>s.args.length===1&&s.args[0]==='--check'));});
  await t('04 · ambiente remove autorizações/credenciais/overrides',()=>{const e=childEnv(temp,{PATH:'/bin',ALLOW_F811_HTTP:'YES',F811_PASSWORD:'secret',B:'http://prod',FUI:'/prod',ADMIN_JS_SOURCE:'/wrong',BASH_ENV:'/evil',NODE_OPTIONS:'--require evil',TWIG_AUTOLOAD:'/qa/autoload.php'},'php');assert.equal(e.MIRROR,path.join(temp,'dev'));assert.equal(e.NODE_BIN,process.execPath);assert.equal(e.TWIG_AUTOLOAD,'/qa/autoload.php');for(const k of['ALLOW_F811_HTTP','F811_PASSWORD','B','FUI','ADMIN_JS_SOURCE','BASH_ENV','NODE_OPTIONS'])assert.equal(e[k],undefined);});
  await t('05 · MIRROR externo não é aceito',()=>{assert.throws(()=>sameMirror(temp,'/nonexistent-f812'));});
  await t('06 · MIRROR do mesmo projeto é aceito',()=>{sameMirror(temp,path.join(temp,'dev'));});
  await t('07 · fingerprint determinístico sem contar ordem da lista',()=>{assert.equal(fingerprint(temp,['a.txt','b.txt']).digest,fingerprint(temp,['b.txt','a.txt']).digest);});
  await t('08 · mudança de fonte altera fingerprint',()=>{const before=fingerprint(temp,['a.txt']).digest;fs.writeFileSync(path.join(temp,'a.txt'),'changed');assert.notEqual(before,fingerprint(temp,['a.txt']).digest);fs.writeFileSync(path.join(temp,'a.txt'),'alpha');});
  await t('09 · fingerprint não aceita duplicidade',()=>{assert.throws(()=>fingerprint(temp,['a.txt','a.txt']));});
  await t('10 · input symlink/traversal não é fonte válida',()=>{fs.symlinkSync('a.txt',path.join(temp,'link.txt'));assert.throws(()=>safeFile(temp,'link.txt'));assert.throws(()=>safeFile(temp,'../a.txt'));});
  await t('11 · runtime Twig precisa coincidir com lock',()=>{const p={phpMajor:8,phpId:80426,mbstring:true,twig:'3.30.0'};assert.equal(dependencyMatches(p,'3.30.0'),true);assert.equal(dependencyMatches(p,'3.27.0'),false);});
  await t('12 · relatório exige arquivo novo e não sobrescreve',()=>{const f=path.join(temp,'x.json');newDestination(f);writeReport(f,{kind:'model-only'});assert.throws(()=>newDestination(f));assert.throws(()=>writeReport(f,{}));});
  await t('13 · exit0 e sinais esperados viram apenas resumo permitido',()=>{const s=SUITES[0];const out=suiteOutcome(s,{status:0,stdout:REQUIRED[s.task].join('\n')+'\nsecret-not-exported'},1);assert.equal(out.status,'passed');assert.ok(!JSON.stringify(out).includes('secret-not-exported'));});
  await t('14 · exit0 sem sinais não é verde',()=>{assert.equal(suiteOutcome(SUITES[0],{status:0,stdout:'ok'},1).status,'failed');});
  await t('15 · exit1 não é escondido por stdout verde',()=>{assert.equal(suiteOutcome(SUITES[0],{status:1,stdout:REQUIRED['F8-05'].join('\n')},1).status,'failed');});
  await t('16 · timeout é falha, sem repetição',()=>{const out=suiteOutcome(SUITES[0],{status:null,stdout:'',error:{code:'ETIMEDOUT'}},1);assert.equal(out.errorCode,'SUITE_TIMEOUT');});
  await t('17 · uma suíte falha impede lote verde',()=>{const s=local().suites;assert.equal(allPassed(s),true);s[0].status='failed';assert.equal(allPassed(s),false);});
  await t('18 · relatório nativo estrutural válido é aceito, não autenticado',()=>{const out=nativeEvidence(native('F8-05'),'F8-05');assert.equal(out.steps,14);assert.equal(out.independentlyVerified,false);nativeEvidence(native('F8-08'),'F8-08');});
  await t('19 · fixture-data-only/tarefa errada não é evidência nativa',()=>{const n=native('F8-10');n.kind='fixture-data-only';assert.throws(()=>nativeEvidence(n,'F8-10'));assert.throws(()=>nativeEvidence(native('F8-06'),'F8-07'));});
  await t('20 · relatório failed/blocked/running não vira aprovado',()=>{for(const status of['failed','blocked','running']){const n=native('F8-06');n.status=status;assert.throws(()=>nativeEvidence(n,'F8-06'));}});
  await t('21 · quantidade/estado das etapas deve conferir',()=>{const n=native('F8-06');n.steps.pop();assert.throws(()=>nativeEvidence(n,'F8-06'));const m=native('F8-06');m.steps[0].status='fail';assert.throws(()=>nativeEvidence(m,'F8-06'));});
  await t('22 · repetir etapa para completar quantidade é recusado',()=>{const n=native('F8-06');n.steps[1].label=n.steps[0].label;assert.throws(()=>nativeEvidence(n,'F8-06'));});
  await t('23 · cleanup/probe pendentes impedem aceite nativo',()=>{const n=native('F8-06');n.cleanup='pending';assert.throws(()=>nativeEvidence(n,'F8-06'));n.cleanup='complete';n.probeCleanup='pending';assert.throws(()=>nativeEvidence(n,'F8-06'));});
  await t('24 · idiomas exige restauração e defaults intactos',()=>{const n=native('F8-11');nativeEvidence(n,'F8-11');n.defaultsUnchanged=false;assert.throws(()=>nativeEvidence(n,'F8-11'));n.defaultsUnchanged=true;n.restoration='pending';assert.throws(()=>nativeEvidence(n,'F8-11'));});
  await t('25 · local-report precisa ter tipo/resultado/guardas corretos',()=>{const l=local();localEvidence(l,d);l.kind='http-workflow';assert.throws(()=>localEvidence(l,d));const m=local();m.selfTests.failed=1;assert.throws(()=>localEvidence(m,d));});
  await t('26 · relatório local de fonte antiga é recusado',()=>{assert.throws(()=>localEvidence(local(),'b'.repeat(64)));});
  await t('27 · modelo/checklist pendente nunca encerra fase',()=>{const out=assess(operator(),null,d,{});assert.equal(out.status,'pending');assert.equal(out.phaseClosed,false);assert.ok(out.issues.length>0);});
  await t('28 · schema/check duplicado ou ausente bloqueia',()=>{const a=operator();a.schema='wrong';assert.throws(()=>checklistShape(a));const b=operator();b.checks[1].id=b.checks[0].id;assert.throws(()=>checklistShape(b));});
  await t('29 · aceite humano completo é só pronto para decisão explícita',()=>{const out=assess(operator(true),local(),d,{});assert.equal(out.status,'ready-for-operator-closure');assert.equal(out.independentlyVerified,false);assert.equal(out.phaseClosed,false);assert.equal(out.doesNotAuthorizeDeploy,true);});
  await t('30 · JSON nativo opcional não exige repetir tarefas aceitas',()=>{const a=operator(true);a.taskEvidence[0]={task:'F8-05',method:'native-json',status:'accepted',path:'/model.json'};const out=assess(a,local(),d,{'F8-05':native('F8-05')});assert.equal(out.status,'ready-for-operator-closure');assert.equal(out.assurance[0].method,'native-json');});
  await t('31 · evidência falha não pode ser mascarada por confirmação',()=>{const a=operator(true);a.taskEvidence[0].path='/failed.json';assert.equal(assess(a,local(),d,{}).status,'pending');a.taskEvidence[0].method='native-json';const bad=native('F8-05');bad.status='failed';assert.equal(assess(a,local(),d,{'F8-05':bad}).status,'pending');});
  await t('32 · JSON local limitado/sem código e saída não replica conteúdo',()=>{assert.throws(()=>jsonFile(path.join(temp,'a.txt')));assert.throws(()=>jsonFile('https://example.com/x.json'));fs.writeFileSync(path.join(temp,'bad.json'),'not json');assert.throws(()=>jsonFile(path.join(temp,'bad.json')));fs.writeFileSync(path.join(temp,'large.json'),' '.repeat(524289));assert.throws(()=>jsonFile(path.join(temp,'large.json')));const n=native('F8-10');n.message='sensitive-not-exported';assert.ok(!JSON.stringify(nativeEvidence(n,'F8-10')).includes(n.message));});
 }finally{for(const f of['a.txt','b.txt','link.txt','x.json','bad.json','large.json']){try{fs.unlinkSync(path.join(temp,f));}catch(e){if(e.code!=='ENOENT')throw e;}}fs.rmdirSync(path.join(temp,'dev'));fs.rmdirSync(temp);}
 if(verbose)console.log(`F8-12 guardas/revisão: ${passed} PASS / ${failed} FAIL — modelos e FS temporário; não é homologação de backend/browser.`);return{passed,failed};
}
function help(){return`Uso: bash tools/e2e/e2e_f812.sh [--check|--self-test|--review /caminho/aceite.json|--help]

--check       Padrão. Whitelist das sete entradas F8-05..11 somente --check.
              PHP/Twig/Node locais; DB/serviços/DOM/fetch simulados conforme
              cada Tool. Sem HTTP/DB/browser, login, deploy, reset ou writes CMS.
              MIRROR deve ser o dev deste projeto; nenhuma fonte externa.
--self-test   32 guardas/modelos do consolidador, só Node/FS temporário.
--review      Lê checklist e relatórios JSON locais; não executa seus comandos
              nem fluxos nativos. Confirmações anteriores do usuário podem ser
              mantidas sem repetir tarefas; evidência JSON opcional é estrutural,
              não atestado independente. Nunca encerra fase/autoriza deploy.

Node20+/Bash. --check requer PHP8.1+/mbstring e Twig igual ao composer.lock,
 já instalado no projeto, ou TWIG_AUTOLOAD absoluto local de QA confiável.
 Não instala dependências nem carrega configs/framework do CMS no consolidador.
 Autorizações/credenciais/overrides de fonte/HTTP não são repassados aos filhos.

F812_REPORT=/tmp/novo-f812-local.json salva --check com0600 sem sobrescrever.
F812_REVIEW_REPORT=/tmp/novo-f812-review.json salva review opcional, mesmas regras.
stdout contém JSON em check/review; progresso vai a stderr. Nunca incluir
PII/cookies/tokens nos checklists. Sem --http/--backend/--browser/prepare/clean.

Guia: docs/F8-12-Consolidacao-Validacao-Painel.md
Modelo pendente: docs/F8-12-Aceite-Painel.json
`;}
async function main(argv,root=ROOT,env=process.env){const args=parseArgs(argv);if(args.mode==='--help'){console.log(help());return 0;}if(args.mode==='--self-test'){const r=await selfTest(true);return r.failed?1:0;}if(args.mode==='--check'){const r=await check(root,env);console.log(JSON.stringify(r,null,2));return r.status==='passed'?0:1;}const r=review(root,env,args.file);console.log(JSON.stringify(r,null,2));return r.status==='ready-for-operator-closure'?0:1;}
if(require.main===module)main(process.argv.slice(2)).then(code=>{process.exitCode=code;}).catch(e=>{console.log(JSON.stringify({task:'Fase 8 / Tarefa 12',kind:'consolidation-control',status:'blocked',errorCode:e instanceof GateError?e.code:'CONTROL_IO_OR_RUNTIME_ERROR',phaseClosed:false,nativeWorkflowsExecuted:false},null,2));process.exitCode=2;});
module.exports={BASE,ROOT,INPUTS,CHECK_IDS,SUITES,NATIVE,REQUIRED,parseArgs,safeFile,fingerprint,suitePlan,sameMirror,childEnv,jsonFile,newDestination,suiteOutcome,allPassed,dependencyMatches,nativeEvidence,localEvidence,checklistShape,assess,linkedJson,check,review,selfTest,main};
