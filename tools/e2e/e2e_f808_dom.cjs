#!/usr/bin/env node
'use strict';
// Fonte real; DOM/Bootstrap/fetch em modelos. Não é navegador/preview/foco real.
const fs=require('node:fs');const path=require('node:path');const vm=require('node:vm');const assert=require('node:assert/strict');
const source=fs.readFileSync(process.argv[2]||path.resolve(__dirname,'../../dev/admin/view/js/catalog-project.js'),'utf8');
let pass=0,fail=0;async function test(label,fn){try{await fn();pass++;console.log('PASS '+label);}catch(e){fail++;console.error('FAIL '+label+': '+e.message);}}
class Element {
  constructor(tag){this.tagName=tag.toUpperCase();this.children=[];this.attributes={};this.handlers=[];this.parentElement=null;this.events=[];this.value='';this.disabled=false;this._text='';const classes=new Set();this.classList={add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c)};}
  set textContent(value){this._text=String(value);for(const c of this.children)c.parentElement=null;this.children=[];}
  get textContent(){return this._text;}
  set innerHTML(value){throw new Error('Não usar innerHTML com imagem/labels/retorno de upload');}
  setAttribute(k,v){this.attributes[k]=String(v);if(['name','id','type'].includes(k))this[k]=String(v);}
  hasAttribute(k){return Object.hasOwn(this.attributes,k);}
  appendChild(child){child.parentElement=this;this.children.push(child);return child;}
  addEventListener(type,fn,capture){this.handlers.push({type,fn,capture});}
  dispatchEvent(event){this.events.push(event.type);return true;}
  contains(node){return node===this||this.children.some(c=>c.contains(node));}
  closest(selector){let n=this;while(n){if(selector==='[data-row]'&&n.hasAttribute('data-row'))return n;if(selector==='[data-remove]'&&n.hasAttribute('data-remove'))return n;n=n.parentElement;}return null;}
  remove(){if(this.parentElement){const p=this.parentElement;p.children=p.children.filter(c=>c!==this);this.parentElement=null;}}
  querySelectorAll(selector){const out=[];function visit(n){for(const c of n.children){if(selector==='input[data-img]'&&c.tagName==='INPUT'&&c.hasAttribute('data-img'))out.push(c);else if(selector==='[data-row]'&&c.hasAttribute('data-row'))out.push(c);visit(c);}}visit(this);return out;}
}
function descendants(root){const out=[];for(const n of root.children){out.push(n,...descendants(n));}return out;}
function fixture(options={}){
  const requests=[],ready=[],listeners=[];let firstTarget=null;
  const pane={id:'pane-project-lang-1',active:false,classList:{contains:()=>pane.active}};
  const tab={count:0,click(){this.count++;pane.active=true;}};
  const controls=[new Element('button'),new Element('button'),new Element('input'),new Element('input')];const[coverButton,galleryButton,coverInput,galleryInput]=controls;
  for(const c of controls)c.disabled=Boolean(options.readonly);coverInput.files=options.noFile?[]:[{name:'cover.png'}];galleryInput.files=options.noFile?[]:[{name:'gallery.png'}];
  const hidden=new Element('input');hidden.value='view/image/project/old.png';const preview=new Element('div');const gallery=new Element('div');const errorBox=new Element('div');errorBox.classList.add('d-none');
  const existing=[];
  for(const index of options.indices||[]){const row=new Element('div');row.setAttribute('data-row','');const image=new Element('input');image.name=`project_image[${index}][image]`;image.value=`view/image/project/old-${index}.png`;image.setAttribute('data-img','');row.appendChild(image);const alt=new Element('input');alt.name=`project_image[${index}][alt]`;alt.value='original alt '+index;row.appendChild(alt);const sort=new Element('input');sort.name=`project_image[${index}][sort_order]`;sort.value=String(index+1);row.appendChild(sort);const remove=new Element('button');remove.setAttribute('data-remove','');remove.disabled=Boolean(options.readonly);remove.appendChild(new Element('i'));row.appendChild(remove);gallery.appendChild(row);existing.push({row,image,alt,sort,remove});}
  const csrf={value:options.noCsrf?'':'c'.repeat(64)};
  const config={upload:options.foreign?'https://example.com/upload':'http://127.0.0.1:8090/dev/admin/index.php?route=catalog/project.upload',catalog:'http://127.0.0.1:8090/dev/catalog/',error:'Falha no envio',wait:'Aguarde o envio',labels:{alt:'Alt ação <b>texto</b>',sort:'Ordem',remove:'Remover'}};
  const form={dataset:{projectConfig:JSON.stringify(config)},querySelector(selector){if(selector==='input:invalid, select:invalid, textarea:invalid')return firstTarget;if(selector==='[data-bs-target="#pane-project-lang-1"]')return tab;const map={'[data-upload]':coverButton,'#btn-add-image':galleryButton,'#input-cover-file':coverInput,'#input-gallery-file':galleryInput,'#input-image':hidden,'#cover-preview':preview,'#gallery':gallery,'#project-upload-error':errorBox,'[name="csrf_token"]':csrf};assert.ok(Object.hasOwn(map,selector),'Selector inesperado: '+selector);return map[selector];},addEventListener(type,fn,capture){listeners.push({type,fn,capture});}};
  const document={readyState:options.state||'interactive',querySelector(selector){assert.equal(selector,'[data-project-form]');return options.noForm?null:form;},addEventListener(type,fn,flags){ready.push({type,fn,flags});},createElement(tag){return new Element(tag);}};
  class FormDataFake{constructor(){this.values=new Map();}append(k,v){this.values.set(k,v);}}
  class EventFake{constructor(type,flags){this.type=type;this.bubbles=flags.bubbles;}}
  const fetchFn=async(url,settings)=>{requests.push({url,settings});if(options.fetchFn)return options.fetchFn(url,settings);return{ok:true,async json(){return options.result||{success:true,path:'view/image/project/20261023170000-abcdef012345.png'};}};};
  const context=vm.createContext({document,window:{location:{href:'http://127.0.0.1:8090/dev/admin/index.php?route=catalog/project.add',origin:'http://127.0.0.1:8090'},bootstrap:options.noBootstrap?undefined:{Tab:function(){}}},URL,FormData:FormDataFake,Event:EventFake,fetch:fetchFn,AbortSignal});
  return{requests,ready,listeners,form,document,pane,tab,controls,coverButton,galleryButton,coverInput,galleryInput,hidden,preview,gallery,errorBox,csrf,config,existing,
    run(){vm.runInContext(source,context,{timeout:1000});},domReady(){document.readyState='interactive';for(const e of ready.splice(0))e.fn();},
    click(which='cover'){const button=which==='cover'?coverButton:galleryButton;const e=button.handlers.find(e=>e.type==='click');return e?e.fn():Promise.resolve();},
    invalid(later=false){const target={closest:()=>later?{id:'pane-project-lang-2',classList:{contains:()=>false}}:pane};if(!later)firstTarget=target;for(const e of listeners.filter(e=>e.type==='invalid'))e.fn({target,preventDefault(){throw new Error('Não impedir validação nativa');}});},
    submit(){const e={prevented:false,preventDefault(){this.prevented=true;}};for(const l of listeners.filter(l=>l.type==='submit'))l.fn(e);return e;},
    remove(target){for(const l of gallery.handlers.filter(l=>l.type==='click'))l.fn({target});}
  };
}
(async()=>{
  await test('01 · DOM ready once e reexecução idempotente',()=>{const x=fixture({state:'loading'});x.run();assert.equal(x.listeners.length,0);assert.equal(x.ready[0].flags.once,true);x.domReady();x.run();assert.equal(x.coverButton.handlers.length,1);assert.equal(x.galleryButton.handlers.length,1);const y=fixture({noForm:true});y.run();assert.equal(y.coverButton.handlers.length,0);});
  await test('02 · só primeiro inválido abre a tab',()=>{const x=fixture();x.run();assert.equal(x.listeners.find(e=>e.type==='invalid').capture,true);x.invalid();x.invalid(true);assert.equal(x.tab.count,1);assert.equal(x.pane.active,true);});
  await test('03 · readonly não associa upload/remover',async()=>{const x=fixture({readonly:true,indices:[0]});x.run();await x.click();await x.click('gallery');x.remove(x.existing[0].remove);assert.equal(x.requests.length,0);assert.equal(x.gallery.children.length,1);});
  await test('04 · sem arquivo/CSRF não envia',async()=>{const x=fixture({noFile:true});x.run();await x.click();await x.click('gallery');assert.equal(x.requests.length,0);const y=fixture({noCsrf:true});y.run();await y.click();assert.equal(y.requests.length,0);assert.equal(y.errorBox.textContent,y.config.error);});
  await test('05 · capa: CSRF/file, eventos e preview DOM seguro',async()=>{const x=fixture();x.run();await x.click();const o=x.requests[0].settings;assert.equal(o.body.values.get('csrf_token'),'c'.repeat(64));assert.equal(o.body.values.get('file'),x.coverInput.files[0]);assert.equal(o.credentials,'same-origin');assert.equal(o.redirect,'error');assert.deepEqual(x.hidden.events,['input','change']);assert.equal(x.preview.children[0].src,'http://127.0.0.1:8090/dev/catalog/'+x.hidden.value);});
  await test('06 · galeria cria três campos e labels como texto',async()=>{const x=fixture();x.run();await x.click('gallery');const row=x.gallery.children[0];const nodes=descendants(row);for(const name of['project_image[0][image]','project_image[0][alt]','project_image[0][sort_order]'])assert.ok(nodes.some(n=>n.name===name));assert.ok(nodes.some(n=>n.tagName==='LABEL'&&n.textContent===x.config.labels.alt));assert.ok(!nodes.some(n=>n.tagName==='B'||n.tagName==='SCRIPT'));const hidden=nodes.find(n=>n.hasAttribute('data-img'));assert.deepEqual(hidden.events,['input','change']);});
  await test('07 · índices não colidem após remoção, inclusive via ícone',async()=>{const x=fixture({indices:[0,2]});x.run();await x.click('gallery');let fields=x.gallery.querySelectorAll('input[data-img]');assert.ok(fields.some(n=>n.name==='project_image[3][image]'));x.remove(x.existing[1].remove.children[0]);await x.click('gallery');fields=x.gallery.querySelectorAll('input[data-img]');assert.ok(fields.some(n=>n.name==='project_image[4][image]'));assert.equal(new Set(fields.map(n=>n.name)).size,fields.length);assert.ok(!fields.some(n=>n.name==='project_image[2][image]'));});
  await test('08 · erro JSON é texto e mantém estado',async()=>{const x=fixture({indices:[0],result:{success:false,error:'<script>erro</script>',path:''}});x.run();await x.click();assert.equal(x.errorBox.textContent,'<script>erro</script>');assert.equal(x.hidden.value,'view/image/project/old.png');assert.equal(x.gallery.children.length,1);});
  await test('09 · path/origem inválidos não alteram capa/galeria',async()=>{for(const p of['../bad.png','https://example.com/x.png','view/image/category/x.png']){const x=fixture({result:{success:true,path:p}});x.run();await x.click('gallery');assert.equal(x.gallery.children.length,0);assert.equal(x.hidden.value,'view/image/project/old.png');}const y=fixture({foreign:true});y.run();await y.click();assert.equal(y.requests.length,0);});
  await test('10 · busy comum bloqueia submit/remover/upload concorrente',async()=>{let resolve;const pending=new Promise(r=>{resolve=r;});const x=fixture({indices:[0],fetchFn:()=>pending});x.run();const first=x.click();await x.click('gallery');assert.equal(x.requests.length,1);assert.equal(x.submit().prevented,true);x.remove(x.existing[0].remove);assert.equal(x.gallery.children.length,1);assert.ok(x.controls.every(c=>c.disabled));resolve({ok:true,async json(){return{success:true,path:'view/image/project/20261023170000-abcdef012345.png'};}});await first;assert.ok(x.controls.every(c=>!c.disabled));assert.equal(x.submit().prevented,false);assert.equal(x.errorBox.classList.contains('d-none'),true);});
  await test('11 · timeout/falha restaura controles sem retry',async()=>{const x=fixture({fetchFn:async()=>{throw new Error('timeout fixture');}});x.run();await x.click('gallery');assert.equal(x.requests.length,1);assert.ok(x.controls.every(c=>!c.disabled));assert.equal(x.errorBox.textContent,x.config.error);});
  await test('12 · boot não reescreve alt/ordem/paths existentes',()=>{const x=fixture({indices:[0,5]});const before=x.existing.map(e=>[e.image.name,e.image.value,e.alt.value,e.sort.value]);x.run();assert.deepEqual(x.existing.map(e=>[e.image.name,e.image.value,e.alt.value,e.sort.value]),before);const y=fixture({noBootstrap:true});y.run();y.invalid();assert.equal(y.tab.count,0);});
  console.log(`F8-08 DOM/upload/galeria simulados: ${pass} PASS / ${fail} FAIL — sem navegador, foco, preview ou upload reais.`);process.exitCode=fail?1:0;
})();
