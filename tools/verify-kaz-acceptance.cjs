/* ReamurCMS — Frente AJ · Chromium real: aceitacao visivel SEM console,
 * 5 estados. Requer Playwright e os HTMLs de teste servidos por um servidor
 * HTTP local (AJ_BASE, padrao http://127.0.0.1:8123), ou 5 URLs explicitas.
 * Uso: node tools/verify-kaz-acceptance.cjs [<5 urls>]
 * Gera PNGs em /tmp/aj_shots (ou AJ_SHOTS). */
const { chromium } = require('playwright');
const fs = require('fs');
const B = process.env.AJ_BASE || 'http://127.0.0.1:8123';
const U = [
  process.argv[2] || B + '/aj_normal.html',
  process.argv[3] || B + '/aj_sem_js.html',
  process.argv[4] || B + '/aj_asset_ausente.html',
  process.argv[5] || B + '/aj_boot_abortado.html',
  process.argv[6] || B + '/aj_controle_ah.html'
];
const OUT = process.env.AJ_SHOTS || '/tmp/aj_shots';
let pass = 0, fail = 0;
const ok = (n, c, x) => {
  if (c) { pass++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { fail++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const FLAGS = ['--no-sandbox','--disable-dev-shm-usage'];
  /* Um browser por estado: o sandbox tem ~2GB e paginas de 230KB esgotam
     o renderer quando varios contextos ficam vivos. */
  async function medir(u, jsEnabled = true, viewport = { width: 900, height: 600 }, shot = null) {
    const b = await chromium.launch({ headless: true, args: FLAGS });
    const ctx = await b.newContext({ viewport, javaScriptEnabled: jsEnabled });
    const p = await ctx.newPage();
    const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(u, { waitUntil: 'load' });
    await p.waitForTimeout(900); // fail-loud da AE/AJ dispara em 500ms
    const r = await p.evaluate(() => {
      const s = document.querySelector('#kaz-acceptance');
      const box = s ? s.querySelector('.alert') : null;
      const st = s ? s.querySelector('[data-kaz-acceptance-state]') : null;
      const dt = s ? s.querySelector('[data-kaz-acceptance-detail]') : null;
      const root = document.getElementById('kaz-theme-editor');
      const rel = document.getElementById('kaz-release-status');
      return {
        state: s ? s.getAttribute('data-kaz-acceptance') : null,
        badge: st ? (st.textContent || '') : '',
        detail: dt ? (dt.textContent || '') : '',
        cls: box ? box.className : '',
        bg: box ? getComputedStyle(box).backgroundColor : null,
        text: s ? (s.textContent || '') : '',
        noscript: s ? s.textContent.includes('JavaScript desabilitado') : false,
        bootWarning: !!document.querySelector('[data-kaz-boot-warning]'),
        health: root ? root.getAttribute('data-kaz-health') : null,
        visible: s ? s.getBoundingClientRect().height > 0 : false,
        release: rel ? rel.getAttribute('data-kaz-release-verdict') : null
      };
    });
    if (shot) await p.screenshot({ path: OUT + '/' + shot, fullPage: true });
    await ctx.close(); await b.close();
    return { r, errs };
  }
  /* Serial: 5 paginas de ~230KB simultaneas estouram o Chromium headless. */
  const N = await medir(U[0], true, { width: 900, height: 600 }, 'normal.png');
  const S = await medir(U[1], false, { width: 900, height: 600 }, 'sem_javascript.png');
  const A = await medir(U[2], true, { width: 900, height: 600 }, 'asset_ausente.png');
  const X = await medir(U[3], true, { width: 900, height: 600 }, 'boot_abortado.png');
  const C = await medir(U[4], true, { width: 900, height: 600 }, 'controle_ah.png');
  ok('normal: painel visivel', N.r.visible === true);
  ok('normal: PRONTO', N.r.state === 'pronto', String(N.r.state));
  ok('normal: badge Editor pronto', N.r.badge.includes('Editor pronto'), N.r.badge);
  ok('normal: verde', N.r.cls.includes('alert-success'), N.r.cls);
  ok('normal: asset -aj', N.r.detail.includes('-aj'), N.r.detail.slice(0, 120));
  ok('normal: modulos e camadas', /\d+ módulos/.test(N.r.detail) && /\d+ camadas/.test(N.r.detail));
  ok('normal: veredito da AH', N.r.detail.includes('PUBLICADO') || N.r.detail.includes('RASCUNHO_PENDENTE'), N.r.detail.slice(0, 150));
  ok('normal: paridade previa/catalogo', N.r.detail.includes('mesmo tema') || N.r.detail.includes('use Publicar'), N.r.detail.slice(0, 180));
  ok('normal: health ad+ae intacto', N.r.health === 'ad+ae', String(N.r.health));
  ok('normal: sem boot-warning', N.r.bootWarning === false);
  ok('normal: health panel AH', N.r.release === 'RASCUNHO_PENDENTE' || N.r.release === 'PUBLICADO', String(N.r.release));

  ok('sem JS: painel visivel', S.r.visible === true);
  ok('sem JS: AGUARDANDO', S.r.state === 'aguardando', String(S.r.state));
  ok('sem JS: noscript renderizado', S.r.noscript === true);
  ok('sem JS: amarelo', S.r.cls.includes('alert-warning'), S.r.cls);
  ok('sem JS: painel AH tambem visivel', S.r.release !== null, String(S.r.release));

  /* Asset 404 => NENHUM JS roda, inclusive o fail-loud. O aviso amarelo
     server-side e justamente o que resta — e ja diz a causa sem console. */
  ok('asset ausente: AGUARDANDO (nenhum JS rodou)', A.r.state === 'aguardando', String(A.r.state));
  ok('asset ausente: amarelo', A.r.cls.includes('alert-warning'), A.r.cls);
  ok('asset ausente: sem boot-warning (JS nao carregou)', A.r.bootWarning === false);
  ok('asset ausente: texto server-side orienta', A.r.detail.includes('JavaScript não carregou'), A.r.detail.slice(0, 140));

  ok('boot abortado: FALHOU', X.r.state === 'falhou', String(X.r.state));
  ok('boot abortado: boot-warning presente', X.r.bootWarning === true);
  ok('boot abortado: vermelho', X.r.cls.includes('alert-danger'), X.r.cls);

  /* Controle: o JS da AH marca bootDone antes do return intermediario.
     Sem a AJ, a tela fica silenciosa — exatamente a lacuna fechada. */
  ok('controle AH: painel permanece AGUARDANDO', C.r.state === 'aguardando', String(C.r.state));
  ok('controle AH: NENHUM boot-warning (lacuna da AE)', C.r.bootWarning === false);
  ok('controle AH: health ad+ae', C.r.health === 'ad+ae', String(C.r.health));
  ok('AJ fecha a lacuna', X.r.state === 'falhou' && C.r.state === 'aguardando');

  ok('cores distintas por estado',
     new Set([N.r.bg, S.r.bg, X.r.bg]).size === 3,
     [N.r.bg, S.r.bg, X.r.bg].join(' / '));
  ok('sem excecoes JS', [N, S, A, X, C].every(v => v.errs.length === 0),
     JSON.stringify([].concat(...[N, S, A, X, C].map(v => v.errs))));

  const M = await medir(U[0], true, { width: 390, height: 844 }, 'normal_mobile.png');
  ok('mobile 390: PRONTO', M.r.state === 'pronto', String(M.r.state));
  ok('mobile 390: painel visivel', M.r.visible === true);
  console.log('\nverify-kaz-acceptance: ' + pass + ' OK / ' + fail + ' FALHAS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
