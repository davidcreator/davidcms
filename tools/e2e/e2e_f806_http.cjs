#!/usr/bin/env node
'use strict';
/** F8-06 — E2E HTTP opt-in, sem reinstalação/SQL/drop; NÃO é navegador.
 * Reutiliza guardas de laboratório F8-05 já versionadas, sem chamar seu fluxo.
 * Cookies e tokens ficam apenas em memória. Só página própria é removida.
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const h = require('./e2e_f805_browser.js').helpers;
const lab = require('./e2e_f805.cjs');
const FILES = ['admin/controller/cms/page.php', 'admin/model/cms/page.php', 'admin/view/template/cms/page_list.twig', 'admin/view/template/cms/page_form.twig', 'admin/view/js/cms-page.js', 'admin/view/css/admin.css', ...['en-us','pt-br','es-eu'].map(c => `admin/language/${c}/cms/page.php`)];
function ok(condition, message) { if (!condition) throw new Error(message); }
function decode(text) {
  return String(text).replace(/&(?:amp|lt|gt|quot|apos|#(?:[0-9]+|x[0-9a-f]+));/gi, entity => {
    const names = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'" };
    if (names[entity.toLowerCase()]) return names[entity.toLowerCase()];
    const value = entity.toLowerCase().startsWith('&#x') ? parseInt(entity.slice(3, -1), 16) : parseInt(entity.slice(2, -1), 10);
    return value > 0 && value <= 0x10ffff && !(value >= 0xd800 && value <= 0xdfff) ? String.fromCodePoint(value) : '\uFFFD';
  });
}
function attrs(text) {
  const result = {};
  for (const m of text.matchAll(/([\w:-]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g)) result[m[1].toLowerCase()] = decode(m[2] ?? m[3] ?? m[4] ?? '');
  return result;
}
function forms(html) {
  return [...html.matchAll(/<form\b([^>]*)>([\s\S]*?)<\/form>/gi)].map(m => {
    const fields = {};
    for (const input of m[2].matchAll(/<input\b([^>]*)>/gi)) {
      const a = attrs(input[1]); if (!a.name || (['checkbox','radio'].includes(a.type) && !Object.hasOwn(a, 'checked'))) continue;
      fields[a.name] = a.value ?? '';
    }
    for (const ta of m[2].matchAll(/<textarea\b([^>]*)>([\s\S]*?)<\/textarea>/gi)) { const a = attrs(ta[1]); if (a.name) fields[a.name] = decode(ta[2]); }
    return { attrs: attrs(m[1]), fields, html: m[2] };
  });
}
function formFor(html, base, route) { const form = forms(html).find(f => f.attrs.action && h.routeOf(f.attrs.action, base) === route); ok(form, 'Form/action esperados não encontrados.'); return form; }
function pageLinks(html, base, marker) {
  const links = [];
  const region = (html.match(/<tbody\b[^>]*>([\s\S]*?)<\/tbody>/i) || [])[1] || '';
  for (const m of region.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)) {
    const a = attrs(m[1]); if (!a.href || !(a.class || '').split(/\s+/).includes('link-plain') || !decode(m[2].replace(/<[^>]*>/g, '')).includes(marker)) continue;
    const route = h.routeOf(a.href, base); const id = route.match(/^cms\/page\.edit\|([1-9][0-9]*)$/);
    if (id) { ok(Number.isSafeInteger(Number(id[1])), 'ID fora do intervalo seguro.'); links.push({ id: Number(id[1]), url: h.scopedUrl(a.href, base).href }); }
  }
  return links;
}
function uri(base, route, params = {}) { const u = new URL('admin/index.php', h.normalizeBase(base)); u.searchParams.set('route', route); for (const [k,v] of Object.entries(params)) u.searchParams.set(k, v); return u.href; }
function languageIds(html) {
  const result = {};
  for (const match of html.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/gi)) {
    const id = (attrs(match[1]).id || '').match(/^tab-page-lang-([1-9][0-9]*)$/);
    const code = decode(match[2].replace(/<[^>]*>/g, '')).match(/\((en-us|pt-br|es-eu)\)/);
    if (id && code) result[code[1]] = Number(id[1]);
  }
  ok(['en-us','pt-br','es-eu'].every(code => Number.isSafeInteger(result[code])), 'Idiomas esperados não foram descobertos nas abas.');
  return result;
}
function payload(marker, version, ids = { 'en-us': 1, 'pt-br': 2, 'es-eu': 3 }) {
  const out = { sort_order: '7' };
  const phrases = { 'en-us': 'English & body', 'pt-br': 'Ação e edição: ç, ã, €', 'es-eu': 'Español: ñ y énfasis' };
  for (const [code, id] of Object.entries(ids)) {
    const text = phrases[code];
    const stem = `page_description[${id}]`;
    out[`${stem}[title]`] = `${marker} ${text} v${version}`;
    out[`${stem}[description]`] = `<h2>${marker} ${id} v${version}</h2><p>${text}</p>`;
    out[`${stem}[keyword]`] = `${marker}-${id}-v${version}`;
    out[`${stem}[meta_title]`] = `Meta ${text}`;
    out[`${stem}[meta_description]`] = `Descrição ${text}`;
  }
  return out;
}
function compareFields(actual, expected) { for (const [name, value] of Object.entries(expected)) ok(actual[name] === value, 'Campo/idioma não persistiu: ' + name); }
class CookieJar {
  constructor() { this.values = new Map(); }
  accept(lines) { for (const line of lines) { const pair = line.split(';', 1)[0]; const i = pair.indexOf('='); if (i < 1) continue; const name = pair.slice(0,i); const value = pair.slice(i+1); if (!value || /;\s*max-age=0(?:;|$)/i.test(line)) this.values.delete(name); else this.values.set(name, value); } }
  header() { return [...this.values].map(([k,v]) => `${k}=${v}`).join('; '); }
}
class Client {
  constructor(base, fetchFn = fetch) { this.base = h.normalizeBase(base); this.fetchFn = fetchFn; this.jar = new CookieJar(); }
  async request(candidate, body, acceptedErrors = []) {
    let url = h.scopedUrl(candidate, this.base).href; let method = body ? 'POST' : 'GET'; let encoded = body ? new URLSearchParams(body).toString() : undefined;
    for (let turn = 0; turn < 6; turn++) {
      const headers = { Accept: 'text/html', 'Cache-Control': 'no-cache' }; if (this.jar.header()) headers.Cookie = this.jar.header(); if (encoded !== undefined) headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
      const response = await this.fetchFn(url, { method, body: encoded, headers, redirect: 'manual', signal: AbortSignal.timeout(15000) });
      ok(typeof response.headers.getSetCookie === 'function', 'Node sem Headers.getSetCookie; atualize o runtime de teste.');
      this.jar.accept(response.headers.getSetCookie());
      if ([301,302,303,307,308].includes(response.status)) {
        const location = response.headers.get('location'); ok(location, 'Redirect sem destino.');
        const next = h.scopedUrl(new URL(location, url).href, this.base).href;
        ok(!(method === 'POST' && [307,308].includes(response.status)), 'Não repetir POST em redirect 307/308. Inspecione o laboratório.');
        if (method === 'POST') { method = 'GET'; encoded = undefined; }
        url = next; continue;
      }
      ok((response.status >= 200 && response.status < 400) || acceptedErrors.includes(response.status), 'Resposta HTTP não aprovada: ' + response.status);
      return { url, status: response.status, html: await response.text() };
    }
    throw new Error('Limite de redirects; nenhum POST é reenviado automaticamente.');
  }
}
function preflight(env) {
  ok(env.ALLOW_F806_HTTP === 'YES_WRITE_REAMUR_TEST', 'HTTP bloqueado: ALLOW_F806_HTTP=YES_WRITE_REAMUR_TEST é obrigatório.');
  ok(env.F806_USERNAME && env.F806_PASSWORD, 'Defina credenciais fictícias do laboratório em F806_USERNAME/F806_PASSWORD, não no chat.');
  const settings = lab.preflight({ ...env, ALLOW_F805_BROWSER: env.ALLOW_F806_HTTP });
  for (const file of FILES) {
    const a = path.join(settings.mirror, file); const b = path.join(settings.fui, file);
    ok(!fs.lstatSync(b).isSymbolicLink() && fs.realpathSync(b) === b, 'Symlink no runtime CMS recusado.');
    ok(fs.readFileSync(a).equals(fs.readFileSync(b)), 'CMS MIRROR/FUI divergentes: ' + file);
  }
  const controller = fs.readFileSync(path.join(settings.mirror, FILES[0]), 'utf8');
  ok(controller.includes('csrfValid()') && controller.includes('getTotalPages('), 'CMS da Tarefa 06 não foi aplicado.');
  if (env.F806_REPORT) ok(path.isAbsolute(env.F806_REPORT) && !fs.existsSync(env.F806_REPORT) && fs.statSync(path.dirname(env.F806_REPORT)).isDirectory(), 'Relatório exige caminho absoluto novo em diretório existente.');
  return settings;
}
async function live(env) {
  const settings = preflight(env); const base = settings.base;
  const nonce = crypto.randomBytes(16).toString('hex'); const marker = 'f806-' + nonce.slice(0,16);
  const probe = path.join(settings.fui, `admin/view/js/f806-probe-${nonce}.txt`); let madeProbe = false; let wrote = false; let id = null;
  const client = new Client(base);
  const report = { task: 'Fase 8 / Tarefa 06', kind: 'http-workflow', marker, status: 'running', startedAt: new Date().toISOString(), steps: [], cleanup: 'not-needed', pageId: null, notCovered: ['navegador/DOM/CSS/contraste', 'sessão de outro perfil ACL no servidor', 'volume de 21+ registros no servidor'] };
  const hide = message => { let text = h.redact(message); for (const secret of [env.F806_USERNAME, env.F806_PASSWORD]) if (secret) text = text.split(secret).join('[REDACTED]'); return text; };
  async function step(label, fn) { try { await fn(); report.steps.push({ label, status: 'pass' }); } catch (e) { report.steps.push({ label, status: 'fail', error: hide(e.message) }); throw e; } }
  const list = () => client.request(uri(base, 'cms/page', { filter_title: marker, filter_status: 'all' }));
  let token = ''; let ids = null;
  try {
    await step('Probe único confere a cópia servida antes do login', async () => {
      fs.writeFileSync(probe, nonce, { flag: 'wx', mode: 0o644 }); madeProbe = true;
      const response = await client.request(new URL(`admin/view/js/f806-probe-${nonce}.txt`, base).href);
      ok(response.html === nonce, 'B não serve o FUI autorizado; nenhuma gravação de página autorizada.');
    });
    await step('Login único com CSRF e cookies só em memória', async () => {
      const response = await client.request(uri(base, 'common/login')); const form = formFor(response.html, base, 'common/login');
      ok(/^[a-f0-9]{64}$/.test(form.fields.csrf_token || ''), 'CSRF de login ausente.');
      const result = await client.request(form.attrs.action, { ...form.fields, username: env.F806_USERNAME, password: env.F806_PASSWORD });
      ok(h.routeOf(result.url, base) !== 'common/login', 'Login recusado. Não repetir tentativas automaticamente.');
    });
    await step('Form GET em en/pt-BR/es, campos e csrf', async () => {
      for (const [locale, title] of [['en-us','Content Pages'],['pt-br','Páginas de Conteúdo'],['es-eu','Páginas de Contenido']]) {
        const response = await client.request(uri(base,'cms/page.add',{language:locale})); const form = formFor(response.html,base,'cms/page.add'); token = form.fields.csrf_token;
        ok(token && response.html.includes(title) && response.html.includes('cmsPageLangTabs') && /<button[^>]*type="submit"/.test(form.html), 'UI/i18n/CSRF/modify não renderizados.');
        const discovered = languageIds(form.html); if (ids) assert.deepEqual(discovered,ids); else ids = discovered;
        ok(Object.hasOwn(form.fields, `page_description[${ids['es-eu']}][description]`) && !response.html.includes('data-rte'), 'Contrato textarea mudou.');
      }
      ok(pageLinks((await list()).html,base,marker).length === 0, 'Marcador já existente; não reutilizar este ensaio.');
    });
    await step('Add sem token recusa e não cria o marcador', async () => {
      wrote = true; report.cleanup = 'pending'; const response = await client.request(uri(base,'cms/page.add'),payload(marker,1,ids));
      ok(h.routeOf(response.url,base)==='cms/page.add' && pageLinks((await list()).html,base,marker).length===0,'Add sem CSRF persistiu ou não retornou o form.');
    });
    await step('Criar página desabilitada com três idiomas', async () => {
      const response = await client.request(uri(base,'cms/page.add'),{...payload(marker,1,ids),csrf_token:token}); ok(h.routeOf(response.url,base)==='cms/page','Add não retornou à lista.');
      const listing=await list(); const links=pageLinks(listing.html,base,marker); ok(links.length===1 && pageLinks(listing.html,base,'').length===1,'Não encontrado exatamente um ID próprio ou filtro trouxe outras páginas.'); id=links[0].id; report.pageId=id;
    });
    await step('Reabrir por GET e conferir corpo/SEO/ordenação', async () => {
      const response=await client.request(uri(base,`cms/page.edit|${id}`)); const form=formFor(response.html,base,`cms/page.edit|${id}`); compareFields(form.fields,payload(marker,1,ids)); ok(!Object.hasOwn(form.fields,'status'),'Status inicial não permaneceu desabilitado.'); token=form.fields.csrf_token;
    });
    await step('Edit com token incorreto não altera o salvo', async () => {
      await client.request(uri(base,`cms/page.edit|${id}`),{...payload(marker,2,ids),csrf_token:'wrong'});
      const response=await client.request(uri(base,`cms/page.edit|${id}`)); compareFields(formFor(response.html,base,`cms/page.edit|${id}`).fields,payload(marker,1,ids));
    });
    await step('Editar mesmo ID, habilitar e reabrir conteúdo v2', async () => {
      await client.request(uri(base,`cms/page.edit|${id}`),{...payload(marker,2,ids),status:'1',csrf_token:token});
      const response=await client.request(uri(base,`cms/page.edit|${id}`)); const form=formFor(response.html,base,`cms/page.edit|${id}`); compareFields(form.fields,payload(marker,2,ids)); ok(form.fields.status==='1','Status habilitado não persistiu.'); token=form.fields.csrf_token;
      const enabled=await client.request(uri(base,'cms/page',{filter_title:marker,filter_status:'1'})); const disabled=await client.request(uri(base,'cms/page',{filter_title:marker,filter_status:'0'}));
      ok(pageLinks(enabled.html,base,marker).length===1 && pageLinks(disabled.html,base,marker).length===0,'Filtros de status/título divergentes.');
    });
    await step('GET delete e POST sem token não excluem', async () => {
      await client.request(uri(base,`cms/page.delete|${id}`),undefined,[403]); ok(pageLinks((await list()).html,base,marker).length===1,'GET delete excluiu.');
      await client.request(uri(base,`cms/page.delete|${id}`),{csrf_token:'wrong'}); ok(pageLinks((await list()).html,base,marker).length===1,'POST inválido excluiu.');
    });
    await step('Excluir somente a página própria validada e conferir ausência', async () => {
      const response=await client.request(uri(base,`cms/page.edit|${id}`)); const form=formFor(response.html,base,`cms/page.edit|${id}`); compareFields(form.fields,payload(marker,2,ids));
      await client.request(uri(base,`cms/page.delete|${id}`),{csrf_token:form.fields.csrf_token}); ok(pageLinks((await list()).html,base,marker).length===0,'Página própria ainda existe.'); report.cleanup='complete';
    });
    report.status='passed';
  } catch(e) { report.status=wrote?'failed':'blocked'; report.error=hide(e.message); }
  finally {
    if(madeProbe) { try { ok(!fs.lstatSync(probe).isSymbolicLink() && fs.readFileSync(probe,'utf8')===nonce,'Probe foi alterado; não removido automaticamente.'); fs.unlinkSync(probe); } catch(e) { report.probeCleanup='pending'; report.probeName=path.basename(probe); report.error=hide(e.message); report.status='failed'; } }
    report.finishedAt=new Date().toISOString(); report.summary={passed:report.steps.filter(s=>s.status==='pass').length,failed:report.steps.filter(s=>s.status==='fail').length};
    console.log(JSON.stringify(report,null,2));
    if(env.F806_REPORT) fs.writeFileSync(env.F806_REPORT,JSON.stringify(report,null,2)+'\n',{flag:'wx',mode:0o600});
  }
  return report.status==='passed'?0:1;
}
async function selfTest() {
  let pass=0,fail=0; async function t(label,fn){try{await fn();pass++;console.log('PASS '+label);}catch(e){fail++;console.error('FAIL '+label+': '+e.message);}}
  const base='http://127.0.0.1:8090/dev/';
  await t('01 · decode HTML uma vez e Unicode',()=>{assert.equal(decode('&amp;lt;&quot;&#039;&#xE7;'),'&lt;"\'ç');});
  await t('02 · atributos com checked/aspas/HTML',()=>{const a=attrs('name="title" value="A &amp; B" checked');assert.equal(a.value,'A & B');assert.ok(Object.hasOwn(a,'checked'));});
  await t('03 · form preserva textarea/checkbox sem inventar status',()=>{const f=forms('<form action="x"><input name="csrf_token" value="secret"><input type="checkbox" name="status" value="1"><textarea name="body">&lt;p&gt;ação&lt;/p&gt;</textarea></form>')[0];assert.equal(f.fields.body,'<p>ação</p>');assert.equal(f.fields.status,undefined);});
  await t('04 · ID descoberto só em link com marcador próprio',()=>{const l=pageLinks('<tbody><a class="link-plain" href="'+uri(base,'cms/page.edit|37')+'">mine-token</a><a class="link-plain" href="'+uri(base,'cms/page.edit|1')+'">other</a></tbody>',base,'mine-token');assert.equal(l.length,1);assert.equal(l[0].id,37);});
  await t('05 · URI e query escapadas',()=>{const u=new URL(uri(base,'cms/page',{filter_title:'A & B'}));assert.equal(u.searchParams.get('filter_title'),'A & B');});
  await t('06 · compara HTML/SEO de três idiomas',()=>{compareFields(payload('f806-test',2),payload('f806-test',2));assert.throws(()=>compareFields(payload('f806-test',1),payload('f806-test',2)));});
  await t('07 · cookie rotação e remoção só em memória',()=>{const j=new CookieJar();j.accept(['RMSSESSION=old; Path=/','lang=en-us; Path=/']);j.accept(['RMSSESSION=new; Path=/']);assert.ok(j.header().includes('RMSSESSION=new'));j.accept(['lang=; Max-Age=0']);assert.ok(!j.header().includes('lang='));});
  await t('08 · B externo recusado antes de rede',()=>{assert.throws(()=>new Client('https://example.com/dev/'));});
  await t('09 · HTTP sem opt-in recusa antes de ler ambiente',()=>{assert.throws(()=>preflight({}),/bloqueado/);});
  await t('10 · credenciais explícitas sem padrão público',()=>{assert.throws(()=>preflight({ALLOW_F806_HTTP:'YES_WRITE_REAMUR_TEST'}),/credenciais/);});
  await t('11 · redirect externo não recebe segundo request/cookie',async()=>{let calls=0;const c=new Client(base,async()=>{calls++;return new Response('',{status:302,headers:{Location:'https://example.com/'}});});await assert.rejects(c.request(uri(base,'cms/page')));assert.equal(calls,1);});
  await t('12 · POST 307 não é repetido',async()=>{let calls=0;const c=new Client(base,async()=>{calls++;return new Response('',{status:307,headers:{Location:uri(base,'cms/page')}});});await assert.rejects(c.request(uri(base,'cms/page.add'),{csrf_token:'x'}),/Não repetir POST/);assert.equal(calls,1);});
  await t('13 · POST 302 segue como GET e troca cookie',async()=>{const seen=[];const c=new Client(base,async(u,o)=>{seen.push(o);return seen.length===1?new Response('',{status:302,headers:{Location:uri(base,'cms/page'),'Set-Cookie':'RMSSESSION=fresh; Path=/'}}):new Response('ok');});await c.request(uri(base,'cms/page.add'),{title:'x'});assert.deepEqual(seen.map(x=>x.method),['POST','GET']);assert.equal(seen[1].body,undefined);assert.equal(seen[1].headers.Cookie,'RMSSESSION=fresh');});
  await t('14 · falha de fetch não repete mutação',async()=>{let calls=0;const c=new Client(base,async()=>{calls++;throw new Error('timeout fixture');});await assert.rejects(c.request(uri(base,'cms/page.add'),{title:'x'}));assert.equal(calls,1);});
  await t('15 · 403 só aceito explicitamente no controle negativo',async()=>{const c=new Client(base,async()=>new Response('denied',{status:403}));await assert.rejects(c.request(uri(base,'cms/page')));const r=await c.request(uri(base,'cms/page.delete|37'),undefined,[403]);assert.equal(r.status,403);});
  await t('16 · language_id descoberto, sem presumir IDs 2/3',()=>{const ids=languageIds('<button id="tab-page-lang-1">English (en-us)</button><button id="tab-page-lang-12">Português <span>(pt-br)</span></button><button id="tab-page-lang-18">Español (es-eu)</button>');assert.equal(ids['pt-br'],12);const p=payload('f806-test',1,ids);assert.ok(p['page_description[18][title]'].includes('Español'));assert.ok(!Object.hasOwn(p,'page_description[3][title]'));});
  console.log(`F8-06 HTTP helpers: ${pass} PASS / ${fail} FAIL — 16 casos com fetch/respostas simulados, sem servidor/banco.`); return fail?1:0;
}
if(require.main===module){(async()=>{ok(process.argv.length===3,'Use e2e_f806.sh --help.');if(process.argv[2]==='--self-test')return selfTest();ok(process.argv[2]==='--http','Modo inválido.');return live(process.env);})().then(code=>{process.exitCode=code;}).catch(e=>{console.error('BLOQUEADO/ERRO: '+h.redact(e.message));process.exitCode=2;});}
module.exports={decode,attrs,forms,pageLinks,uri,payload,languageIds,compareFields,CookieJar,Client,preflight,live,selfTest};
