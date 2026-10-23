#!/usr/bin/env node
'use strict';
/** F8-07 — E2E HTTP mutável opt-in, não é navegador.
 * Reutiliza Client/parser/guardas F8-06/F8-05 sem executar seus fluxos.
 * Duas categorias próprias + PNG 2x2. Sem SQL direto/reset/limpeza ampla.
 */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const shared = require('./e2e_f806_http.cjs');
const h = require('./e2e_f805_browser.js').helpers;
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGOQqgj4D8IMMAYAPqgHhR+OGvoAAAAASUVORK5CYII=', 'base64');
const sha = b => crypto.createHash('sha256').update(b).digest('hex');
const FILES = ['admin/controller/catalog/category.php','admin/model/catalog/category.php','admin/view/template/catalog/category_list.twig','admin/view/template/catalog/category_form.twig','admin/view/js/catalog-category.js','admin/view/css/admin.css',...['en-us','pt-br','es-eu'].map(c => `admin/language/${c}/catalog/category.php`)];
const IMAGE = /^view\/image\/category\/[0-9]{14}-[a-f0-9]{12}\.png$/;
function ok(condition,message){if(!condition)throw new Error(message);}
function formFor(html,base,route){
  const form=shared.forms(html).find(f=>f.attrs.action&&h.routeOf(f.attrs.action,base)===route);ok(form,'Form/action esperado não encontrado.');
  form.parents=[];
  for(const select of form.html.matchAll(/<select\b([^>]*)>([\s\S]*?)<\/select>/gi)){
    const attrs=shared.attrs(select[1]);if(attrs.name!=='parent_id')continue;
    const options=[...select[2].matchAll(/<option\b([^>]*)>([\s\S]*?)<\/option>/gi)].map(m=>shared.attrs(m[1]));
    ok(options.length>0,'Opções de pai ausentes.');form.fields.parent_id=(options.find(a=>Object.hasOwn(a,'selected'))||options[0]).value;
    form.parents=options.map(a=>Number(a.value));
  }
  return form;
}
function languageIds(html){
  const ids={};for(const m of html.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/gi)){
    const id=(shared.attrs(m[1]).id||'').match(/^tab-category-lang-([1-9][0-9]*)$/);
    const code=shared.decode(m[2].replace(/<[^>]*>/g,'')).match(/\((en-us|pt-br|es-eu)\)/);
    if(id&&code)ids[code[1]]=Number(id[1]);
  }
  ok(['en-us','pt-br','es-eu'].every(c=>Number.isSafeInteger(ids[c])),'Não encontrados os três idiomas nas tabs.');return ids;
}
function categoryLinks(html,base,marker){
  const region=(html.match(/<tbody\b[^>]*>([\s\S]*?)<\/tbody>/i)||[])[1]||'';const links=[];
  for(const m of region.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)){
    const a=shared.attrs(m[1]);if(!a.href||!(a.class||'').split(/\s+/).includes('link-plain')||!shared.decode(m[2].replace(/<[^>]*>/g,'')).includes(marker))continue;
    const id=h.routeOf(a.href,base).match(/^catalog\/category\.edit\|([1-9][0-9]*)$/);if(id){ok(Number.isSafeInteger(Number(id[1])),'ID fora do intervalo seguro.');links.push({id:Number(id[1]),url:h.scopedUrl(a.href,base).href});}
  }return links;
}
function payload(marker,role,version,ids,parent=0,image=''){
  const out={parent_id:String(parent),image,sort_order:'7'};const phrases={'en-us':'English & name','pt-br':'Ação e edição ç, ã, €','es-eu':'Español ñ'};
  for(const[code,id]of Object.entries(ids)){const stem=`category_description[${id}]`;const text=phrases[code];out[`${stem}[name]`]=`${marker}-${role} ${text} v${version}`;out[`${stem}[description]`]=`<h2>${marker}-${role} ${id} v${version}</h2><p>${text}</p>`;out[`${stem}[keyword]`]=`${marker}-${role}-${id}-v${version}`;out[`${stem}[meta_title]`]=`Meta ${text}`;out[`${stem}[meta_description]`]=`Descrição ${text}`;}return out;
}
function compare(actual,expected){shared.compareFields(actual,expected);}
function imageDirectory(settings){return path.join(settings.fui,'catalog/view/image/category');}
function checkImageAncestors(settings){
  let current=settings.fui;
  for(const part of ['catalog','view','image','category']){current=path.join(current,part);let stat;try{stat=fs.lstatSync(current);}catch(e){if(e.code==='ENOENT')break;throw e;}ok(stat.isDirectory()&&!stat.isSymbolicLink()&&fs.realpathSync(current)===current,'Diretório de imagem não canônico/symlink recusado.');}
}
function imageNames(settings){checkImageAncestors(settings);const dir=imageDirectory(settings);return fs.existsSync(dir)?fs.readdirSync(dir).sort():[];}
function ownedImage(settings,relative){
  ok(typeof relative==='string'&&IMAGE.test(relative),'Path de PNG de teste inesperado.');checkImageAncestors(settings);
  const file=path.join(settings.fui,'catalog',relative);const stat=fs.lstatSync(file);ok(stat.isFile()&&!stat.isSymbolicLink()&&fs.realpathSync(file)===file,'Imagem não é arquivo regular do FUI.');
  ok(sha(fs.readFileSync(file))===sha(PNG),'PNG de teste alterado ou não persistido no FUI.');return file;
}
class Client extends shared.Client{
  async multipart(candidate,body){
    const url=h.scopedUrl(candidate,this.base).href;const headers={Accept:'application/json','Cache-Control':'no-cache'};if(this.jar.header())headers.Cookie=this.jar.header();
    const response=await this.fetchFn(url,{method:'POST',body,headers,redirect:'manual',signal:AbortSignal.timeout(30000)});
    ok(typeof response.headers.getSetCookie==='function','Atualize Node: Headers.getSetCookie ausente.');this.jar.accept(response.headers.getSetCookie());
    ok(response.status>=200&&response.status<300,'Upload HTTP não aprovado; não seguir/repetir POST: '+response.status);
    const json=await response.json();ok(json&&typeof json.success==='boolean'&&typeof json.path==='string','JSON de upload inesperado.');return json;
  }
  async imageBytes(relative){
    ok(IMAGE.test(relative),'Path de imagem inesperado.');const url=h.scopedUrl(new URL('catalog/'+relative,this.base).href,this.base).href;
    const response=await this.fetchFn(url,{method:'GET',headers:{Accept:'image/png','Cache-Control':'no-cache'},redirect:'manual',signal:AbortSignal.timeout(15000)});
    ok(response.status===200,'PNG não servido no catalog.');const bytes=Buffer.from(await response.arrayBuffer());ok(sha(bytes)===sha(PNG),'PNG servido não coincide com a fixture.');return bytes;
  }
}
function preflight(env){
  ok(env.ALLOW_F807_HTTP==='YES_WRITE_REAMUR_TEST','HTTP bloqueado: ALLOW_F807_HTTP=YES_WRITE_REAMUR_TEST obrigatório.');
  ok(env.F807_USERNAME&&env.F807_PASSWORD,'Defina credenciais fictícias em F807_USERNAME/F807_PASSWORD.');
  const settings=shared.preflight({...env,ALLOW_F806_HTTP:env.ALLOW_F807_HTTP,F806_USERNAME:env.F807_USERNAME,F806_PASSWORD:env.F807_PASSWORD,F806_REPORT:undefined});
  for(const relative of [...FILES,'system/library/media_upload.php']){for(const root of[settings.mirror,settings.fui]){const file=path.join(root,relative);ok(fs.lstatSync(file).isFile()&&fs.realpathSync(file)===file,'Fonte categoria não canônica.');}ok(fs.readFileSync(path.join(settings.mirror,relative)).equals(fs.readFileSync(path.join(settings.fui,relative))),'Categoria MIRROR/FUI divergente: '+relative);}
  const controller=fs.readFileSync(path.join(settings.mirror,FILES[0]),'utf8');ok(controller.includes('getTotalCategories(')&&controller.includes('csrfValid()'),'Tarefa07 não aplicada.');
  checkImageAncestors(settings);
  if(env.F807_REPORT)ok(path.isAbsolute(env.F807_REPORT)&&!fs.existsSync(env.F807_REPORT)&&fs.statSync(path.dirname(env.F807_REPORT)).isDirectory(),'Relatório exige caminho absoluto novo em diretório existente.');
  return settings;
}
function multipart(token,name){const data=new FormData();if(token)data.append('csrf_token',token);data.append('file',new Blob([PNG],{type:'image/png'}),name);return data;}
async function live(env){
  const settings=preflight(env);const base=settings.base;const nonce=crypto.randomBytes(16).toString('hex');const marker='f807-'+nonce.slice(0,16);
  const probe=path.join(settings.fui,`admin/view/js/f807-probe-${nonce}.txt`);let madeProbe=false;let wrote=false;let ids=null;let token='';let uploadUrl='';let rootId=null;let childId=null;let image='';
  const beforeImages=imageNames(settings);const client=new Client(base);
  const report={task:'Fase 8 / Tarefa 07',kind:'http-workflow',marker,status:'running',startedAt:new Date().toISOString(),steps:[],cleanup:'not-needed',artifacts:{rootId:null,childId:null,image:''},notCovered:['navegador/DOM/Bootstrap/foco/preview/CSS/contraste','perfil readonly real separado','volume de 21+ categorias no servidor','vínculos reais com projetos preexistentes']};
  function hide(message){let text=h.redact(message);for(const secret of[env.F807_USERNAME,env.F807_PASSWORD])if(secret)text=text.split(secret).join('[REDACTED]');return text.replace(/RMSSESSION=[^;\s]+/g,'RMSSESSION=[REDACTED]');}
  async function step(label,fn){try{await fn();report.steps.push({label,status:'pass'});}catch(e){report.steps.push({label,status:'fail',error:hide(e.message)});throw e;}}
  const uri=(route,params={})=>shared.uri(base,route,params);
  const list=()=>client.request(uri('catalog/category',{filter_name:marker,filter_status:'all'}));
  async function read(id){const response=await client.request(uri(`catalog/category.edit|${id}`));return formFor(response.html,base,`catalog/category.edit|${id}`);}
  async function discover(ownMarker){const response=await client.request(uri('catalog/category',{filter_name:ownMarker,filter_status:'all'}));const links=categoryLinks(response.html,base,ownMarker);ok(links.length===1&&categoryLinks(response.html,base,'').length===1,'Filtro não retornou somente uma categoria própria.');return links[0].id;}
  try{
    await step('Probe único identifica o FUI antes de enviar credenciais',async()=>{fs.writeFileSync(probe,nonce,{flag:'wx',mode:0o644});madeProbe=true;const response=await client.request(new URL(`admin/view/js/f807-probe-${nonce}.txt`,base).href);ok(response.html===nonce,'B não serve o FUI autorizado.');});
    await step('Login único, CSRF e cookies somente em memória',async()=>{const response=await client.request(uri('common/login'));const form=formFor(response.html,base,'common/login');ok(/^[a-f0-9]{64}$/.test(form.fields.csrf_token||''),'CSRF login ausente.');const result=await client.request(form.attrs.action,{...form.fields,username:env.F807_USERNAME,password:env.F807_PASSWORD});ok(h.routeOf(result.url,base)!=='common/login','Login recusado; não repetir automaticamente.');});
    await step('Form en/pt-BR/es, modify, language_id e upload URL reais',async()=>{
      for(const[locale,heading]of[['en-us','Portfolio Categories'],['pt-br','Categorias do Portfólio'],['es-eu','Categorías del Portafolio']]){
        const response=await client.request(uri('catalog/category.add',{language:locale}));const form=formFor(response.html,base,'catalog/category.add');
        ok(response.html.includes(heading)&&response.html.includes('categoryLangTabs')&&/<button[^>]*type="submit"/.test(form.html),'UI/i18n/modify não renderizados.');const found=languageIds(form.html);if(ids)assert.deepEqual(found,ids);else ids=found;
        const config=JSON.parse(form.attrs['data-category-config']||'{}');ok(h.routeOf(config.upload,base)==='catalog/category.upload','Rota de upload da UI inesperada.');uploadUrl=config.upload;token=form.fields.csrf_token;ok(token&&form.fields.parent_id==='0','CSRF/pai padrão ausentes.');
      }
      ok(categoryLinks((await list()).html,base,marker).length===0,'Marcador já existente; não reutilizar.');
    });
    await step('Add sem CSRF recusa sem criar categoria',async()=>{wrote=true;report.cleanup='pending';const response=await client.request(uri('catalog/category.add'),payload(marker,'root',1,ids));ok(h.routeOf(response.url,base)==='catalog/category.add'&&categoryLinks((await list()).html,base,marker).length===0,'Add inválido persistiu ou retornou fora do form.');});
    await step('Upload sem CSRF recusa sem gravar arquivo',async()=>{const json=await client.multipart(uploadUrl,multipart('',marker+'-negative.png'));const after=imageNames(settings);const added=after.filter(n=>!beforeImages.includes(n));if(json.path)report.artifacts.unexpectedUpload=json.path;if(added.length)report.artifacts.unexpectedFiles=added;ok(json.success===false&&json.path===''&&added.length===0,'Upload sem CSRF gravou/retornou um arquivo.');});
    await step('Upload PNG2x2 com CSRF, bytes no FS e catalog HTTP',async()=>{const json=await client.multipart(uploadUrl,multipart(token,marker+'.png'));ok(json.success===true,'Upload válido recusado.');image=json.path;report.artifacts.image=image;ok(!beforeImages.includes(path.basename(image)),'Nome de upload já existia; não remover arquivo anterior.');ownedImage(settings,image);await client.imageBytes(image);});
    await step('Criar raiz desabilitada com imagem e três idiomas',async()=>{const response=await client.request(uri('catalog/category.add'),{...payload(marker,'root',1,ids,0,image),csrf_token:token});ok(h.routeOf(response.url,base)==='catalog/category','Add raiz não voltou à lista.');rootId=await discover(marker+'-root');report.artifacts.rootId=rootId;});
    await step('Criar filho próprio ligado à raiz',async()=>{const fresh=await client.request(uri('catalog/category.add'));const form=formFor(fresh.html,base,'catalog/category.add');ok(form.parents.includes(rootId),'Raiz ausente do dropdown completo.');const response=await client.request(form.attrs.action,{...payload(marker,'child',1,ids,rootId),status:'1',csrf_token:form.fields.csrf_token});ok(h.routeOf(response.url,base)==='catalog/category','Add filho não voltou à lista.');childId=await discover(marker+'-child');report.artifacts.childId=childId;});
    await step('Reabrir dados/SEO/imagem e opções anti-ciclo',async()=>{const root=await read(rootId);compare(root.fields,payload(marker,'root',1,ids,0,image));ok(!Object.hasOwn(root.fields,'status')&&!root.parents.includes(rootId)&&!root.parents.includes(childId),'Status/opções anti-ciclo incorretos.');const child=await read(childId);compare(child.fields,payload(marker,'child',1,ids,rootId));token=root.fields.csrf_token;});
    await step('Edit CSRF inválido e pais self/descendente não persistem',async()=>{await client.request(uri(`catalog/category.edit|${rootId}`),{...payload(marker,'root',2,ids,0,image),csrf_token:'wrong'});compare((await read(rootId)).fields,payload(marker,'root',1,ids,0,image));for(const invalid of[rootId,childId]){await client.request(uri(`catalog/category.edit|${rootId}`),{...payload(marker,'root',2,ids,invalid,image),csrf_token:token});compare((await read(rootId)).fields,payload(marker,'root',1,ids,0,image));}});
    await step('Editar raiz v2 mesmo ID, habilitar e conferir filtros',async()=>{await client.request(uri(`catalog/category.edit|${rootId}`),{...payload(marker,'root',2,ids,0,image),status:'1',csrf_token:token});const root=await read(rootId);compare(root.fields,payload(marker,'root',2,ids,0,image));ok(root.fields.status==='1','Status não persistiu.');token=root.fields.csrf_token;for(const[value,expected]of[['1',1],['0',0]]){const response=await client.request(uri('catalog/category',{filter_name:marker+'-root',filter_status:value}));ok(categoryLinks(response.html,base,marker+'-root').length===expected,'Filtro nome/status incorreto.');}});
    await step('GET delete e POST sem token mantêm raiz/filho/imagem',async()=>{await client.request(uri(`catalog/category.delete|${rootId}`),undefined,[403]);ok(categoryLinks((await list()).html,base,marker).length===2,'GET delete removeu registro.');await client.request(uri(`catalog/category.delete|${rootId}`),{csrf_token:'wrong'});ok(categoryLinks((await list()).html,base,marker).length===2,'Delete inválido removeu registro.');ownedImage(settings,image);});
    await step('Excluir só raiz própria, remover PNG e promover filho à raiz',async()=>{const root=await read(rootId);compare(root.fields,payload(marker,'root',2,ids,0,image));ownedImage(settings,image);await client.request(uri(`catalog/category.delete|${rootId}`),{csrf_token:root.fields.csrf_token});const links=categoryLinks((await list()).html,base,marker);ok(links.length===1&&links[0].id===childId,'Raiz não removida ou filho afetado.');ok(!fs.existsSync(path.join(settings.fui,'catalog',image)),'PNG da raiz não removido pelo fluxo existente.');const child=await read(childId);compare(child.fields,payload(marker,'child',1,ids,0));});
    await step('Excluir só filho próprio e conferir limpeza do ensaio',async()=>{const child=await read(childId);compare(child.fields,payload(marker,'child',1,ids,0));await client.request(uri(`catalog/category.delete|${childId}`),{csrf_token:child.fields.csrf_token});ok(categoryLinks((await list()).html,base,marker).length===0,'Categoria própria ainda existe.');ok(imageNames(settings).filter(n=>!beforeImages.includes(n)).length===0,'Arquivo novo inesperado; não remover amplamente.');report.cleanup='complete';});
    report.status='passed';
  }catch(e){report.status=wrote?'failed':'blocked';report.error=hide(e.message);}
  finally{
    if(madeProbe){try{ok(!fs.lstatSync(probe).isSymbolicLink()&&fs.readFileSync(probe,'utf8')===nonce,'Probe alterado; não remover automaticamente.');fs.unlinkSync(probe);}catch(e){report.status='failed';report.cleanup='pending';report.probeCleanup='pending';report.probeName=path.basename(probe);report.error=hide(e.message);}}
    report.finishedAt=new Date().toISOString();report.summary={passed:report.steps.filter(s=>s.status==='pass').length,failed:report.steps.filter(s=>s.status==='fail').length};console.log(JSON.stringify(report,null,2));if(env.F807_REPORT)fs.writeFileSync(env.F807_REPORT,JSON.stringify(report,null,2)+'\n',{flag:'wx',mode:0o600});
  }
  return report.status==='passed'?0:1;
}
async function selfTest(){
  const inherited=await shared.selfTest();let pass=0,fail=0;async function t(label,fn){try{await fn();pass++;console.log('PASS '+label);}catch(e){fail++;console.error('FAIL '+label+': '+e.message);}}
  const base='http://127.0.0.1:8090/dev/';const uri=(route)=>shared.uri(base,route);
  await t('01 · select parent/language_id extraídos do HTML',()=>{const html='<form action="'+uri('catalog/category.add')+'"><input name="csrf_token" value="x"><select name="parent_id"><option value="0">None</option><option value="37" selected>Parent</option></select><button id="tab-category-lang-1">English (en-us)</button><button id="tab-category-lang-12">PT (pt-br)</button><button id="tab-category-lang-18">ES (es-eu)</button></form>';const f=formFor(html,base,'catalog/category.add');assert.equal(f.fields.parent_id,'37');assert.deepEqual(f.parents,[0,37]);assert.equal(languageIds(f.html)['es-eu'],18);});
  await t('02 · ID só por link/nome próprio no tbody',()=>{const html='<tbody><a class="link-plain" href="'+uri('catalog/category.edit|37')+'">f807-own-root</a><a class="icon-btn" href="'+uri('catalog/category.edit|1')+'">f807-own-root</a></tbody>';const found=categoryLinks(html,base,'f807-own-root');assert.equal(found.length,1);assert.equal(found[0].id,37);});
  await t('03 · payload nome/parent/image/Unicode sem contrato de página',()=>{const p=payload('f807-own','root',2,{'en-us':1,'pt-br':12,'es-eu':18},37,'view/image/category/a.png');assert.equal(p.parent_id,'37');assert.ok(p['category_description[12][name]'].includes('Ação'));assert.ok(!Object.hasOwn(p,'category_description[1][title]'));assert.equal(PNG.readUInt32BE(16),2);assert.equal(PNG.readUInt32BE(20),2);});
  await t('04 · multipart inclui cookie e não define boundary manual',async()=>{let seen;const c=new Client(base,async(u,o)=>{seen=o;return new Response(JSON.stringify({success:true,path:'x'}),{headers:{'Content-Type':'application/json'}});});c.jar.accept(['RMSSESSION=fixture; Path=/']);const fd=multipart('token','f807.png');await c.multipart(uri('catalog/category.upload'),fd);assert.equal(seen.body.get('csrf_token'),'token');assert.equal(seen.body.get('file').size,PNG.length);assert.equal(seen.headers.Cookie,'RMSSESSION=fixture');assert.equal(seen.headers['Content-Type'],undefined);assert.equal(seen.redirect,'manual');});
  await t('05 · upload 307 nunca é repetido/seguido',async()=>{let calls=0;const c=new Client(base,async()=>{calls++;return new Response('',{status:307,headers:{Location:uri('catalog/category.upload')}});});await assert.rejects(c.multipart(uri('catalog/category.upload'),multipart('x','f807.png')));assert.equal(calls,1);});
  await t('06 · timeout upload sem retry',async()=>{let calls=0;const c=new Client(base,async()=>{calls++;throw new Error('timeout fixture');});await assert.rejects(c.multipart(uri('catalog/category.upload'),multipart('x','f807.png')));assert.equal(calls,1);});
  await t('07 · PNG servido verificado como bytes, sem cookie',async()=>{let seen;const c=new Client(base,async(u,o)=>{seen=o;return new Response(PNG);});await c.imageBytes('view/image/category/20261023160000-abcdef012345.png');assert.equal(seen.headers.Cookie,undefined);});
  await t('08 · autorização antiga não habilita F8-07',()=>{assert.throws(()=>preflight({ALLOW_F806_HTTP:'YES_WRITE_REAMUR_TEST'}),/bloqueado/);assert.throws(()=>preflight({ALLOW_F807_HTTP:'YES_WRITE_REAMUR_TEST'}),/credenciais/);});
  await t('09 · guardas FS rejeitam traversal/hash/symlink em fixture privada',()=>{
    const root=fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(),'f807-http-')));const settings={fui:root};const relative='view/image/category/20261023160000-abcdef012345.png';const dir=path.join(root,'catalog/view/image/category');fs.mkdirSync(dir,{recursive:true,mode:0o700});const file=path.join(root,'catalog',relative);
    try{fs.writeFileSync(file,PNG);assert.equal(ownedImage(settings,relative),file);assert.throws(()=>ownedImage(settings,'../escape.png'));fs.writeFileSync(file,'changed');assert.throws(()=>ownedImage(settings,relative));fs.unlinkSync(file);fs.symlinkSync('missing.png',file);assert.throws(()=>ownedImage(settings,relative));fs.unlinkSync(file);fs.renameSync(dir,dir+'-real');
      try{fs.symlinkSync('category-real',dir,'dir');assert.throws(()=>checkImageAncestors(settings));}finally{try{fs.unlinkSync(dir);}catch(e){if(e.code!=='ENOENT')throw e;}fs.renameSync(dir+'-real',dir);}}
    finally{if(fs.existsSync(file)||fs.lstatSync(dir).isDirectory()){try{fs.unlinkSync(file);}catch(e){if(e.code!=='ENOENT')throw e;}}for(const p of['catalog/view/image/category','catalog/view/image','catalog/view','catalog',''])fs.rmdirSync(p?path.join(root,p):root);}
  });
  console.log(`F8-07 HTTP auxiliares novos: ${pass} PASS / ${fail} FAIL — mais 16 F8-06 compartilhados; fetch/respostas e FS privados simulados, sem servidor/upload reais.`);return inherited||fail?1:0;
}
if(require.main===module){(async()=>{ok(process.argv.length===3,'Use e2e_f807.sh --help.');if(process.argv[2]==='--self-test')return selfTest();ok(process.argv[2]==='--http','Modo inválido.');return live(process.env);})().then(code=>{process.exitCode=code;}).catch(e=>{console.error('BLOQUEADO/ERRO: '+h.redact(e.message));process.exitCode=2;});}
module.exports={Client,formFor,languageIds,categoryLinks,payload,preflight,ownedImage,checkImageAncestors,imageNames,PNG,live,selfTest};
