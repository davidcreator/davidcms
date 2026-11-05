/* Frente AO — verificador Chromium headless. A/B: Admin sem AO x Admin com AO. */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.AO_BASE || 'http://127.0.0.1:8123/ao';
const OUT = process.env.AO_SHOTS || '/tmp/ao_shots';
const U = process.argv.slice(2);
const URLS = U.length >= 2 ? U.slice(0, 2) : [BASE + '/admin_controle.html', BASE + '/admin_ao.html'];
const FLAGS = ['--no-sandbox', '--disable-dev-shm-usage'];
const DESK = { width: 1440, height: 900 }, MOB = { width: 390, height: 844 };
fs.mkdirSync(OUT, { recursive: true });
let P = 0, F = 0; const ERRS = [];
const ok = (n, c, x) => { if (c) { P++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
                          else { F++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); } };
const probe = () => {
  const q = s => document.querySelector(s);
  const bg = s => { const e = q(s); return e ? getComputedStyle(e).backgroundColor : ''; };
  const col = s => { const e = q(s); return e ? getComputedStyle(e).color : ''; };
  const sum = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
    return m ? +m[1] + +m[2] + +m[3] : -1; };
  const h = q('[data-blog-block-editor]');
  const claros = [];
  [document.documentElement, ...document.querySelectorAll('body *')].forEach(e => {
    const c = getComputedStyle(e);
    if (!/rgba?\(/.test(c.backgroundColor) || sum(c.backgroundColor) <= 690) return;
    if (c.display === 'none' || e.getBoundingClientRect().height <= 2) return;
    if (e.closest('.rb-canvas')) return;
    claros.push((typeof e.className === 'string' ? e.className : '').slice(0, 42));
  });
  let thumb = 0, focus = '', focusOffset = '', aoRules = 0, aoSelectors = 0;
  for (const s of document.styleSheets) { let rs; try { rs = s.cssRules; } catch (e) { continue; }
    for (const r of rs) {
      if (!r.selectorText) continue;
      if (r.selectorText.indexOf('::-webkit-scrollbar-thumb') >= 0) thumb++;
      if (r.selectorText.indexOf('body:has([data-blog-block-editor])') >= 0) {
        aoRules++;
        aoSelectors += (r.selectorText.match(/body:has\(\[data-blog-block-editor\]\)/g) || []).length;
      }
      if (r.selectorText.indexOf(':focus-visible') >= 0 && r.style) {
        if (r.style.outline) focus = r.style.outline;
        if (r.style.outlineOffset) focusOffset = r.style.outlineOffset;
      }
    }
  }
  const hidden = q('#ao-description');
  return {
    hasSupport: CSS.supports('selector(body:has(*))'),
    themeAttr: document.documentElement.getAttribute('data-bs-theme'),
    editor: !!q('.rb-editor'), bar: !!q('.kaz-bdre-bar'),
    htmlBg: getComputedStyle(document.documentElement).backgroundColor,
    bodyBg: getComputedStyle(document.body).backgroundColor,
    cardBg: bg('.card'), formBg: bg('.form-control'), inputBg: bg('.input-group-text'),
    btnBg: bg('.btn-primary'), alertBg: bg('.alert'), navBg: bg('.nav-link.active'),
    titleColor: col('.page-title'), labelColor: col('.form-label'),
    canvasBg: bg('.rb-canvas'), canvasText: bg('.rb-canvas') ? col('.rb-canvas') : '',
    dialogBg: bg('.rb-command-dialog'), patternBg: bg('.rb-pattern-library'),
    previewBg: bg('.rb-preview-table-of-contents'), inserterBg: bg('.rb-inserter-menu'),
    bsBody: getComputedStyle(document.body).getPropertyValue('--bs-body-bg').trim(),
    primary: getComputedStyle(document.body).getPropertyValue('--kaz-bdre-primary').trim(),
    scroll: getComputedStyle(document.body).scrollbarColor,
    claros: claros.length, amostra: claros.slice(0, 8),
    thumb, focus, focusOffset, aoRules, aoSelectors,
    docValue: hidden ? hidden.value : '',
    diag: typeof window.__kazBdre === 'function' ? window.__kazBdre() : null,
    scale: h ? h.getAttribute('data-kaz-ui-scale') || 'normal' : null,
    fold: h ? h.getAttribute('data-kaz-fold') || '' : null,
    overflow: Math.max(0, document.documentElement.scrollWidth - window.innerWidth)
  };
};
async function medir(u, vp, shot, acao, tag) {
  const b = await chromium.launch({ headless: true, args: FLAGS });
  let ctx = null; const before = ERRS.length;
  try {
    ctx = await b.newContext({ viewport: vp });
    const p = await ctx.newPage(); p.setDefaultTimeout(15000);
    p.on('pageerror', e => ERRS.push(tag + ': ' + e.message));
    p.on('console', m => { if (m.type() === 'error') ERRS.push(tag + ': ' + m.text()); });
    await p.goto(u, { waitUntil: 'load' });
    await p.waitForTimeout(1000);
    if (acao) await acao(p);
    await p.waitForTimeout(240);
    const r = await p.evaluate(probe);
    if (shot) await p.screenshot({ path: OUT + '/' + shot, fullPage: true });
    r._novosErros = ERRS.length - before;
    return r;
  } finally { if (ctx) await ctx.close().catch(() => {}); await b.close().catch(() => {}); }
}
const escuro = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
  return m ? (+m[1] + +m[2] + +m[3]) < 260 : false; };
const claro = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
  return m ? (+m[1] + +m[2] + +m[3]) > 690 : false; };
(async () => {
  const K = await medir(URLS[0], DESK, 'controle_sem_ao.png', null, 'controle');
  ok('motor suporta :has()', K.hasSupport, String(K.hasSupport));
  ok('controle: preferencia light preservada', K.themeAttr === 'light', K.themeAttr);
  ok('controle: editor montado', K.editor);
  ok('controle: barra AN presente', K.bar);
  ok('controle: html/body claros', claro(K.htmlBg) && claro(K.bodyBg), K.htmlBg + ' / ' + K.bodyBg);
  ok('controle: card/campo/botao claros', claro(K.cardBg) && claro(K.formBg), K.cardBg + ' / ' + K.formBg);
  ok('controle: overlays claros', claro(K.dialogBg) && claro(K.patternBg) && claro(K.previewBg));
  ok('controle: sem regras AO', K.aoRules === 0, String(K.aoRules));
  ok('controle: token Bootstrap claro', K.bsBody === '#f4f6f9', K.bsBody);
  ok('controle: fundos claros contados', K.claros >= 15, String(K.claros));
  ok('controle: canvas branco', claro(K.canvasBg), K.canvasBg);

  const N = await medir(URLS[1], DESK, 'ao_admin.png', null, 'ao');
  ok('AO: preferencia light NAO foi alterada', N.themeAttr === 'light', N.themeAttr);
  ok('AO: sempre escuro KAZ', escuro(N.htmlBg) && escuro(N.bodyBg),
     K.htmlBg + ' -> ' + N.htmlBg + ' / ' + K.bodyBg + ' -> ' + N.bodyBg);
  ok('AO: regras carregadas no motor', N.aoRules >= 15 && K.aoRules === 0,
     K.aoRules + ' -> ' + N.aoRules);
  ok('AO: mais de 100 seletores escopados no motor', N.aoSelectors >= 100,
     String(N.aoSelectors));
  ok('AO: card ESCURO', escuro(N.cardBg), K.cardBg + ' -> ' + N.cardBg);
  ok('AO: campo ESCURO', escuro(N.formBg), K.formBg + ' -> ' + N.formBg);
  ok('AO: input-group ESCURO', escuro(N.inputBg), N.inputBg);
  ok('AO: alert ESCURO', escuro(N.alertBg), K.alertBg + ' -> ' + N.alertBg);
  ok('AO: botao primario verde #31efb8', N.btnBg === 'rgb(49, 239, 184)', N.btnBg);
  ok('AO: titulo legivel no escuro', N.titleColor === 'rgb(233, 233, 238)', N.titleColor);
  ok('AO: labels em muted', N.labelColor === 'rgb(155, 155, 167)', N.labelColor);
  ok('AO: token Bootstrap sobrescrito', N.bsBody === '#18181b', K.bsBody + ' -> ' + N.bsBody);
  ok('AO: accent verde', N.primary === '#31efb8', N.primary);
  ok('AO: overlays ESCUROS', escuro(N.dialogBg) && escuro(N.patternBg) && escuro(N.previewBg) && escuro(N.inserterBg),
     K.dialogBg + ' -> ' + N.dialogBg);
  ok('AO: ZERO fundos claros fora do canvas', N.claros === 0,
     K.claros + ' -> ' + N.claros + (N.amostra.length ? ' :: ' + N.amostra.join(', ') : ''));
  ok('AO: CANVAS continua CLARO', claro(N.canvasBg), N.canvasBg);
  ok('AO: scrollbar-color tematica', /srgb|rgb/.test(N.scroll) && !/auto/.test(N.scroll), N.scroll);
  /* O controle ja carrega a AN (3 regras de thumb no editor). A AO
     acrescenta as regras de thumb para a PAGINA inteira. */
  ok('AO: thumb para a pagina inteira acrescentado', N.thumb > K.thumb,
     K.thumb + ' -> ' + N.thumb);
  ok('AO: foco #174ea6 carregado', /174ea6|23, ?78, ?166/i.test(N.focus) && /3px/.test(N.focus), N.focus);
  ok('AO: outline-offset 2px', /2px/.test(N.focusOffset), N.focusOffset);
  ok('AO: editor continua montado', N.editor);
  ok('AO: barra AN continua', N.bar && !!N.diag && N.diag[0].ready === true);
  ok('AO: documento de blocos identico', K.docValue === N.docValue && N.docValue.length > 100,
     N.docValue.length + ' bytes');
  ok('AO: sem excecoes novas', N._novosErros <= K._novosErros, K._novosErros + ' -> ' + N._novosErros);
  ok('desktop: zero overflow', N.overflow === 0 && K.overflow === 0, K.overflow + ' / ' + N.overflow);

  /* Nao-regressao da AN: escala e dobras continuam operando. */
  const S = await medir(URLS[1], DESK, 'ao_amplo.png', async p => {
    await p.selectOption('select[data-kaz-bdre-scale]', 'large');
  }, 'amplo');
  ok('AN nao regrediu: escala ampla', S.scale === 'large' && S.diag && S.diag[0].scale === 'large');
  ok('AN nao regrediu: canvas intacto', claro(S.canvasBg));
  const R = await medir(URLS[1], DESK, 'ao_recolhido.png', async p => {
    await p.click('[data-kaz-bdre-all]');
  }, 'recolhido');
  ok('AN nao regrediu: 3 dobras', R.fold && R.fold.split(' ').length === 3, String(R.fold));
  ok('AN nao regrediu: barra permanece', R.bar);

  const M = await medir(URLS[1], MOB, 'ao_mobile.png', null, 'mobile');
  ok('mobile 390: html/body escuros', escuro(M.htmlBg) && escuro(M.bodyBg), M.htmlBg);
  ok('mobile 390: card escuro', escuro(M.cardBg), M.cardBg);
  ok('mobile 390: canvas claro', claro(M.canvasBg), M.canvasBg);
  ok('mobile 390: barra visivel', M.bar);
  ok('mobile 390: zero claros fora do canvas', M.claros === 0, String(M.claros));
  ok('mobile 390: documento preservado', M.docValue === K.docValue);

  console.log('\nverify-kaz-bdre-total: ' + P + ' OK / ' + F + ' FALHAS');
  if (F) process.exitCode = 1;
})().catch(e => { console.log('ERRO FATAL ' + e.message); process.exitCode = 1; });
