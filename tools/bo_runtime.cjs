/* Frente BO — prova de runtime REAL no Chromium.
   Executa o SceneRunner do CMS + david-universe.js + Three.js r186 self-hosted.
   Nao simula WebGL: mede o canvas, a cena, a nave, as interacoes e o HUD.
   Observacao: o SceneRunner limita delta a 0.05s/frame. No SwiftShader
   (WebGL por software, ~5 fps) a suavizacao avanca mais devagar que o tempo
   real; por isso o teste valida COERENCIA (camZ = 8 - progress*320) e usa
   hold(1) + waitForFunction para provar a convergencia, nao um sleep fixo. */
const { chromium } = require('/home/user/browser/node_modules/playwright');
const fs = require('fs');
const OUT = '/home/user/bo_out';
const URL = 'http://127.0.0.1:8123/index.html';
let P = 0, F = 0;
const ok = (n, c, x = '') => { c ? P++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + n + (x !== '' ? ' · ' + x : '')); };
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--use-gl=angle', '--use-angle=swiftshader', '--enable-webgl', '--disable-dev-shm-usage'],
  });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const errors = [], consoleErrors = [], failed = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('requestfailed', r => failed.push(r.url() + ' · ' + (r.failure()?.errorText || '')));

  await page.goto(URL, { waitUntil: 'load' });
  await page.waitForFunction(() => !!window.__duScene, { timeout: 25000 });
  await sleep(700);

  const boot = await page.evaluate(() => {
    const canvas = document.querySelector('.theme-scene--hero canvas');
    let gl = null;
    if (canvas) gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
    return {
      debug: !!window.__duScene,
      revision: window.__duScene?.revision ?? null,
      counts: window.__duScene?.counts ?? null,
      sceneChildren: window.__duScene?.sceneChildren ?? null,
      sceneObjects: window.__duScene?.sceneObjects ?? null,
      canvas: !!canvas,
      canvasW: canvas?.width ?? 0,
      canvasH: canvas?.height ?? 0,
      gl: !!gl,
      glVersion: gl ? gl.getParameter(gl.VERSION) : '',
      renderer: gl ? gl.getParameter(gl.RENDERER) : '',
      slots: document.querySelectorAll('[data-scene="david-universe"]').length,
      fixed: canvas ? getComputedStyle(canvas.parentNode).position : '',
      fallbackOpacity: getComputedStyle(document.querySelector('.theme-scene__fallback')).opacity,
      hudOrbes: document.querySelector('[data-du-orbes]')?.textContent ?? '',
      hudDist: document.querySelector('[data-du-distancia]')?.textContent ?? '',
      hudFase: document.querySelector('[data-du-fase]')?.textContent ?? '',
      scrollHeight: document.documentElement.scrollHeight,
      target: window.__duScene?.target ?? null,
      phases: document.querySelectorAll('[id^="fase-"]').length,
    };
  });
  console.log('\n-- boot --');
  ok('SceneRunner montou a cena (debug hook)', boot.debug);
  ok('Three.js self-hosted r186', String(boot.revision) === '186', 'REVISION=' + boot.revision);
  ok('WebGL real (contexto criado)', boot.gl, boot.glVersion + ' · ' + boot.renderer);
  ok('canvas dentro do slot', boot.canvas && boot.canvasW > 0 && boot.canvasH > 0, boot.canvasW + '×' + boot.canvasH);
  ok('slot hero e backdrop fixo', boot.fixed === 'fixed', 'position=' + boot.fixed);
  ok('1 slot por pagina', boot.slots === 1, String(boot.slots));
  ok('fallback declarativo recua', parseFloat(boot.fallbackOpacity) < 0.3, boot.fallbackOpacity);
  ok('14 orbes', boot.counts?.orbs === 14, JSON.stringify(boot.counts));
  ok('8 gates', boot.counts?.gates === 8);
  ok('5 planetas + 46 asteroides + 2 astronautas',
     boot.counts?.planets === 5 && boot.counts?.asteroids === 46 && boot.counts?.astronauts === 2);
  ok('trilha de 60 particulas', boot.counts?.trail === 60);
  ok('estrelas limitadas por data-count', boot.counts?.stars === 520, String(boot.counts?.stars));
  ok('cena povoada (>150 objetos na arvore)', boot.sceneObjects > 150,
     boot.sceneObjects + ' objetos · ' + boot.sceneChildren + ' filhos diretos');
  ok('HUD ORBES 00/14', boot.hudOrbes === '00/14', boot.hudOrbes);
  ok('HUD DISTANCIA 0 KM', boot.hudDist === '0 KM', boot.hudDist);
  ok('HUD exibe fase numerada', /FASE \d\d — /.test(boot.hudFase), boot.hudFase);
  ok('9 fases observadas pelo IntersectionObserver', boot.phases === 9, String(boot.phases));
  ok('pagina longa o bastante para voar', boot.scrollHeight > 3000, boot.scrollHeight + 'px');

  // ── pilotagem: pointermove move a nave ─────────────────────────────
  const before = await page.evaluate(() => window.__duScene.ship);
  await page.evaluate(() => window.__duScene.setPointer(0.42, -0.25));
  await sleep(1500);
  const after = await page.evaluate(() => window.__duScene.ship);
  console.log('\n-- pilotagem --');
  ok('pointermove move a nave em X', Math.abs(after.x - before.x) > 1.0,
     `${before.x.toFixed(2)} → ${after.x.toFixed(2)}`);
  ok('pointermove move a nave em Y', Math.abs(after.y - before.y) > 0.4,
     `${before.y.toFixed(2)} → ${after.y.toFixed(2)}`);
  await page.screenshot({ path: OUT + '/shot-bo-hero.png' });

  // ── voo por scroll ─────────────────────────────────────────────────
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.55));
  await sleep(2200);
  const mid = await page.evaluate(() => {
    const d = window.__duScene;
    return {
      progress: d.progress, target: d.target, scroll: d.scroll,
      camZ: d.camera.z, fov: d.camera.fov, warp: d.warp, shipZ: d.ship.z,
      dist: document.querySelector('[data-du-distancia]').textContent,
      hint: document.querySelector('[data-du-hint-distancia]').textContent,
      bar: document.querySelector('[data-du-progresso]').style.width,
      fase: document.querySelector('[data-du-fase]').textContent,
    };
  });
  console.log('\n-- voo por scroll --');
  ok('scroll dispara setProgress (target > 0)', mid.target > 0.4, 'target=' + mid.target.toFixed(3));
  ok('progresso acompanha o target', mid.progress > 0.15, 'progress=' + mid.progress.toFixed(3));
  ok('camera = 8 - progress*320', Math.abs(mid.camZ - (8 - mid.progress * 320)) < 0.2,
     'camera.z=' + mid.camZ.toFixed(1));
  ok('scroll move a camera para dentro do tunel', mid.camZ < -40, 'camera.z=' + mid.camZ.toFixed(1));
  ok('nave acompanha a camera (z = camZ - 9)', Math.abs(mid.shipZ - (mid.camZ - 9)) < 0.5,
     'ship.z=' + mid.shipZ.toFixed(1));
  ok('warp altera FOV', mid.fov > 66, 'fov=' + mid.fov.toFixed(1) + ' warp=' + mid.warp.toFixed(2));
  ok('HUD distancia = round(progress*384400) km',
     mid.dist === Math.round(mid.progress * 384400).toLocaleString('en-US') + ' KM', mid.dist);
  ok('hint de distancia atualiza', mid.hint === mid.dist, mid.hint);
  ok('barra de progresso coerente', Math.abs(parseFloat(mid.bar) - mid.progress * 100) < 1, mid.bar);
  ok('IntersectionObserver troca a fase', !/FASE 00/.test(mid.fase), mid.fase);
  await page.screenshot({ path: OUT + '/shot-bo-voo.png' });

  // ── coleta de orbes ────────────────────────────────────────────────
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  await sleep(2500);
  const collected = await page.evaluate(() => {
    const d = window.__duScene;
    return {
      n: d.collected, total: d.totalOrbs,
      marked: d.orbPositions.filter(o => o.collected).length,
      orb: document.querySelector('[data-du-orbes]').textContent,
      toast: document.querySelector('[data-du-toast]').textContent,
      dist: document.querySelector('[data-du-distancia]').textContent,
    };
  });
  console.log('\n-- coleta de orbes --');
  ok('a coleta acontece durante o voo', collected.n > 0, `${collected.n}/${collected.total}`);
  ok('orbes coletados marcados na cena', collected.marked === collected.n,
     `${collected.marked} marcados`);
  ok('HUD ORBES reflete a coleta', collected.orb === String(collected.n).padStart(2, '0') + '/14', collected.orb);
  ok('toast de coleta aparece', /[Oo]rbe|Todos/.test(collected.toast), collected.toast);
  await page.screenshot({ path: OUT + '/shot-bo-coleta.png' });

  // ── convergencia: hold(1) prova que a matematica chega ao fim ───────
  await page.evaluate(() => window.__duScene.hold(1));
  let converged = false;
  try {
    await page.waitForFunction(() => window.__duScene.progress > 0.999, { timeout: 30000, polling: 200 });
    await sleep(1500);
    converged = true;
  } catch (e) { /* medido abaixo */ }
  const end = await page.evaluate(() => {
    const d = window.__duScene;
    return {
      progress: d.progress, target: d.target, camZ: d.camera.z,
      dist: document.querySelector('[data-du-distancia]').textContent,
      bar: document.querySelector('[data-du-progresso]').style.width,
    };
  });
  console.log('\n-- fim do voo (hold 1) --');
  ok('progresso converge para 1', converged && end.progress > 0.999, end.progress.toFixed(5));
  ok('camera chega a z=-312', Math.abs(end.camZ - (-312)) < 1.5, 'camera.z=' + end.camZ.toFixed(2));
  ok('camera = 8 - progress*320 no fim', Math.abs(end.camZ - (8 - end.progress * 320)) < 0.2,
     'camera.z=' + end.camZ.toFixed(2));
  const km = parseInt(end.dist.replace(/[^\d]/g, ''), 10);
  ok('distancia = round(progress*384400) km, chegando a ~384.400 KM',
     km === Math.round(end.progress * 384400) && km >= 384000, end.dist + ' (384.400 KM em progress=1)');
  ok('barra chega a 100%', parseFloat(end.bar) > 99.8, end.bar);
  await page.screenshot({ path: OUT + '/shot-bo-fim.png' });
  await page.evaluate(() => window.__duScene.resume());

  // ── erros ──────────────────────────────────────────────────────────
  console.log('\n-- erros --');
  ok('0 pageerrors', errors.length === 0, JSON.stringify(errors.slice(0, 3)));
  ok('0 console.error', consoleErrors.length === 0, JSON.stringify(consoleErrors.slice(0, 3)));
  ok('0 requests falhos (tudo self-hosted)', failed.length === 0, JSON.stringify(failed.slice(0, 3)));

  // ── reduced motion: runner nao monta (degradacao graciosa) ─────────
  const rmCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const rm = await rmCtx.newPage();
  await rm.goto(URL, { waitUntil: 'load' });
  await sleep(1200);
  const rmState = await rm.evaluate(() => ({
    debug: !!window.__duScene,
    canvas: !!document.querySelector('.theme-scene--hero canvas'),
    fallback: !!document.querySelector('.theme-scene__fallback'),
  }));
  console.log('\n-- reduced motion --');
  ok('runner respeita prefers-reduced-motion', rmState.debug === false && rmState.canvas === false,
     JSON.stringify(rmState));
  ok('fallback declarativo permanece', rmState.fallback);
  await rmCtx.close();

  // ── mobile ─────────────────────────────────────────────────────────
  const mCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 });
  const m = await mCtx.newPage();
  const mErr = [];
  m.on('pageerror', e => mErr.push(String(e)));
  await m.goto(URL, { waitUntil: 'load' });
  await m.waitForFunction(() => !!window.__duScene, { timeout: 25000 });
  await sleep(700);
  const mob = await m.evaluate(() => ({
    debug: !!window.__duScene,
    orbs: window.__duScene?.totalOrbs ?? 0,
    canvas: !!document.querySelector('.theme-scene--hero canvas'),
    scrollW: document.documentElement.scrollWidth,
    innerW: window.innerWidth,
    coarse: window.__duScene?.coarse ?? null,
  }));
  console.log('\n-- mobile 390px --');
  ok('cena monta no mobile', mob.debug && mob.canvas, JSON.stringify(mob));
  ok('14 orbes tambem no mobile', mob.orbs === 14, String(mob.orbs));
  ok('pointer coarse ativa autopiloto', mob.coarse === true, String(mob.coarse));
  ok('0 overflow horizontal', mob.scrollW <= mob.innerW, `${mob.scrollW} vs ${mob.innerW}`);
  ok('0 pageerrors no mobile', mErr.length === 0, JSON.stringify(mErr.slice(0, 2)));
  await m.screenshot({ path: OUT + '/shot-bo-mobile.png' });
  await mCtx.close();

  fs.writeFileSync(OUT + '/runtime-bo.json', JSON.stringify({
    boot, mid, collected, end, converged, rmState, mob,
    errors, consoleErrors, failed, P, F,
  }, null, 1));
  console.log(`\nCHROMIUM BO runtime: ${P} OK / ${F} FALHAS\n`);
  await browser.close();
  process.exit(F ? 1 : 0);
})().catch(e => { console.error('ERRO FATAL', e); process.exit(2); });
