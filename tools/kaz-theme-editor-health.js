/* ReamurCMS — KAZTheme-Editor · diagnostico de PRODUCAO (Frente AE).
 * SOMENTE LEITURA. Nenhuma rede, nenhuma escrita no DOM, nenhum eval,
 * nenhum armazenamento local de navegador, nenhuma excecao deliberada.
 *
 * COMO USAR: abra a pagina de edicao de tema no Admin, abra o DevTools,
 * cole este arquivo no Console e pressione Enter (ele ja imprime o relatorio).
 * Depois execute  copy(__kazHealth())  para copiar o JSON ao suporte.
 * Nao faz parte do runtime do CMS: e uma ferramenta de diagnostico.
 */
window.__kazHealth = function () {
  'use strict';
  function q(sel) { return document.querySelector(sel); }
  function box(el) { if (!el) return null; var r = el.getBoundingClientRect();
    return { w: Math.round(r.width), h: Math.round(r.height), x: Math.round(r.x), y: Math.round(r.y) }; }
  var MODULES = [
    ['kaz-theme-editor', 'kaz-theme-editor.js'],
    ['theme-package', 'theme-package.js'],
    ['theme-condition-builder', 'theme-condition-builder.js'],
    ['theme-scene-editor', 'theme-scene-editor.js'],
    ['theme-section-builder', 'theme-section-builder.js'],
    ['theme-component-library', 'theme-component-library.js'],
    ['theme-schedule', 'theme-schedule.js'],
    ['theme-media', 'theme-media.js'],
    ['theme-sites', 'theme-sites.js']
  ];
  var tags = Array.prototype.slice.call(document.scripts).map(function (t) {
    return { src: t.getAttribute('src') || '', defer: !!t.defer,
             head: !!(t.parentNode && t.parentNode.nodeName === 'HEAD') };
  });
  var links = Array.prototype.slice.call(
    document.querySelectorAll('link[rel="stylesheet"]')).map(function (l) {
      return l.getAttribute('href') || '';
    });
  function assetJs(name) {
    return tags.filter(function (t) { return t.src.indexOf(name) >= 0; })
      .map(function (t) { var u = t.src.split('?'); return {
        path: u[0], version: (u[1] || '').replace(/^v=/, ''), defer: t.defer, inHead: t.head }; });
  }
  function assetCss(name) {
    return links.filter(function (h) { return h.indexOf(name) >= 0; })
      .map(function (h) { var u = h.split('?'); return {
        path: u[0], version: (u[1] || '').replace(/^v=/, '') }; });
  }
  var root = document.getElementById('kaz-theme-editor');
  var api = root && root.__kaz && typeof root.__kaz === 'object' ? root.__kaz : null;
  var scene = null, zoom = null, screen = null;
  try { if (api) { scene = api.state(); zoom = api.zoom(); screen = api.screen(); } } catch (e) { scene = null; }
  var modules = {};
  MODULES.forEach(function (m) {
    var a = assetJs(m[1]);
    modules[m[0]] = { root: !!document.getElementById(m[0]),
                      asset: a.length > 0, version: (a[0] || {}).version || null,
                      defer: (a[0] || {}).defer || false };
  });
  var vp = q('[data-kaz-viewport]'), sh = q('.kaz-shell'), ctr = q('.kaz-center'),
      stg = q('[data-kaz-stage]'), frame = q('[data-kaz-screen-frame]');
  var scrolls = Array.prototype.slice.call(document.querySelectorAll('*'))
    .filter(function (e) { return e.scrollHeight > e.clientHeight + 8 && e.clientHeight > 40; }).length;
  var csrf = q('meta[name="csrf-token"]');
  var form = document.getElementById('kaz-theme-form');
  var pub = q('.kaz-publish');
  var warning = q('[data-kaz-boot-warning]');
  var kaz = assetJs('kaz-theme-editor.js'), kazCss = assetCss('kaz-theme-editor.css');

  var problems = [];
  if (!root) problems.push('SEM_ROOT:#kaz-theme-editor ausente');
  if (!kaz.length) problems.push('SEM_ASSET_JS:kaz-theme-editor.js nao referenciado');
  if (!kazCss.length) problems.push('SEM_ASSET_CSS:kaz-theme-editor.css nao referenciado');
  if (kaz.length && !kaz.some(function (a) { return a.version.indexOf('-kaz-theme-editor-a') >= 0; }))
    problems.push('ASSET_ANTIGO:sufixo de cache nao reconhecido');
  if (kaz.length && !kaz.some(function (a) { return a.defer; }))
    problems.push('SEM_DEFER:script sem defer (ressalva: a AE inicializa mesmo assim)');
  if (root && root.getAttribute('data-kaz-ready') !== '1') problems.push('SEM_BOOT:data-kaz-ready != 1');
  if (root && !api) problems.push('SEM_API:__kaz nao exposto');
  if (warning) problems.push('AVISO_VISIVEL:fail-loud disparou');
  if (!csrf) problems.push('SEM_CSRF:meta csrf-token ausente');
  if (!form) problems.push('SEM_FORM:#kaz-theme-form ausente');
  if (ctr && ctr.getBoundingClientRect().width < 40)
    problems.push('LAYOUT_CENTRO_ESTREITO:centro colapsou');
  if (document.documentElement.scrollWidth > window.innerWidth + 2)
    problems.push('OVERFLOW_HORIZONTAL:documento mais largo que a viewport');

  /* AE: o boot e resiliente mesmo sem defer. Logo a ausencia de defer e
     RESSALVA quando o editor inicializou, e FALHA apenas se o boot morreu. */
  var verdict;
  if (!kaz.length) verdict = 'FALHA_ASSET';
  else if (!api || (root && root.getAttribute('data-kaz-ready') !== '1'))
    verdict = 'FALHA_BOOT';
  else if (problems.length) verdict = 'OK_COM_RESSALVAS';
  else verdict = 'OK';

  var report = {
    ferramenta: 'kaz-theme-editor-health/ae',
    url: window.location.href,
    timestamp: new Date().toISOString(),
    viewport: { w: window.innerWidth, h: window.innerHeight },
    veredito: verdict,
    problemas: problems,
    boot: {
      root: !!root, ready: root ? root.getAttribute('data-kaz-ready') : null,
      api: api ? 'object' : null, health: root ? root.getAttribute('data-kaz-health') : null,
      saved: root ? root.getAttribute('data-kaz-saved') : null,
      layers: scene && scene.layers ? scene.layers.length : null,
      zoom: zoom, screen: screen
    },
    assets: { js: kaz, css: kazCss, totalScripts: tags.length },
    modulos: modules,
    layout: {
      shell: box(sh), centro: box(ctr), stage: box(stg), viewport: box(vp), frame: box(frame),
      scrollsAninhados: scrolls,
      overflowHorizontal: document.documentElement.scrollWidth - window.innerWidth
    },
    publicacao: {
      csrf: !!csrf, form: !!form, action: form ? form.getAttribute('action') : null,
      publicar: !!pub,
      canModify: q('[data-kaz-can-modify]') ? q('[data-kaz-can-modify]').getAttribute('data-kaz-can-modify') : 'nao exposto'
    },
    avisoVisivel: !!warning
  };
  console.log('%c[KAZ health] ' + verdict, 'font-weight:700;color:' +
    (verdict === 'OK' ? '#15803d' : '#b91c1c'), report);
  if (problems.length) console.warn('[KAZ health] problemas', problems);
  console.log('[KAZ health] execute copy(__kazHealth()) para copiar o JSON.');
  return report;
};
window.__kazHealth();
