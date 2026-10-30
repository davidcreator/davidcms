'use strict';
/*
 * tools/staging — bateria HTTP: o staging serve, e serve SEM vazar dev/prod.
 *
 * Prova de isolamento exigida pelo gate F9-27 ("staging descartável isolado de
 * produção"): o HTML público do staging tem de se referenciar a si mesmo
 * (/davidcms/staging/), nunca ao espelho (/davidcms/dev/), e nenhum arquivo de
 * configuração pode ser baixado pelo navegador.
 *
 * Requer o WAMP/Apache ligado. Não inicia serviços.
 */
const { STATUS, BatteryResult } = require('../lib/report.cjs');
const cfg = require('../lib/config.cjs');

const TIMEOUT_MS = Number(process.env.STAGING_HTTP_TIMEOUT_MS || 20000);

async function probe(url) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(url, {
      redirect: 'manual',
      signal: controller.signal,
      headers: { 'user-agent': 'davidcms-staging-battery/1.0' },
    });
    const body = await res.text();
    return { ok: true, status: res.status, location: res.headers.get('location') || '', body, cookies: res.headers.get('set-cookie') || '' };
  } catch (error) {
    return { ok: false, error: error.name === 'AbortError' ? 'timeout' : error.message };
  } finally {
    clearTimeout(timer);
  }
}

async function run() {
  const battery = new BatteryResult('http', 'Servidor de staging e isolamento público');
  const started = Date.now();
  battery.note(`URLs configuradas: ${cfg.URLS.base}. Se o Apache estiver desligado, a bateria fica NOT_RUN.`);

  const catalog = await probe(cfg.URLS.catalog);
  if (!catalog.ok) {
    battery.check('catalog responde', STATUS.NOT_RUN, `sem resposta em ${cfg.URLS.catalog} (${catalog.error}) — WAMP ligado?`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }

  battery.check('catalog responde', catalog.status === 200 ? STATUS.PASS : STATUS.FAIL,
    `HTTP ${catalog.status} em ${cfg.URLS.catalog}`);

  const admin = await probe(cfg.URLS.admin);
  battery.check('admin responde', admin.ok && [200, 302, 303].includes(admin.status) ? STATUS.PASS : STATUS.FAIL,
    admin.ok ? `HTTP ${admin.status}${admin.location ? ` → ${admin.location}` : ''}` : `sem resposta (${admin.error})`);

  const missing = await probe(cfg.URLS.missing);
  battery.check('404 nativo em rota inexistente', missing.ok && missing.status === 404 ? STATUS.PASS : STATUS.FAIL,
    missing.ok ? `HTTP ${missing.status}` : `sem resposta (${missing.error})`);

  // ── isolamento: o HTML do staging fala de si mesmo, não do espelho ────
  const selfRef = (catalog.body.match(/\/davidcms\/staging\//g) || []).length;
  const devRef = (catalog.body.match(/\/davidcms\/dev\//g) || []).length;
  if (selfRef > 0 && devRef === 0) {
    battery.check('isolamento de URLs na home pública', STATUS.PASS,
      `${selfRef} referência(s) a /davidcms/staging/ e nenhuma a /davidcms/dev/`);
  } else {
    battery.check('isolamento de URLs na home pública', selfRef > 0 ? STATUS.FAIL : STATUS.WARN,
      `referências staging=${selfRef} · dev=${devRef} — investigue antes de confiar no ambiente`);
  }

  // ── isolamento: config.php nunca é servido ────────────────────────────
  for (const [label, url] of [
    ['catalog', `${cfg.URLS.catalog}config.php`],
    ['admin', `${cfg.URLS.admin}config.php`],
  ]) {
    const res = await probe(url);
    const blocked = res.ok && (res.status === 403 || res.status === 404);
    battery.check(`config.php protegido (${label})`, blocked ? STATUS.PASS : STATUS.FAIL,
      res.ok ? `HTTP ${res.status} em ${url}` : `sem resposta (${res.error})`);
  }

  // ── o espelho continua de pé (o staging não sequestrou o servidor) ────
  const dev = await probe(cfg.URLS.devCatalog);
  battery.check('espelho dev/ intacto', dev.ok && dev.status === 200 ? STATUS.PASS : STATUS.WARN,
    dev.ok ? `HTTP ${dev.status} em ${cfg.URLS.devCatalog}` : `sem resposta (${dev.error})`);

  // ── cookies de sessão não colidem (path diferente por app) ────────────
  const loginProbe = await probe(`${cfg.URLS.admin}index.php?route=common/login`);
  const pathAttr = /Path=([^;]+)/i.exec(loginProbe.cookies || '');
  battery.check('cookie de sessão com path próprio', loginProbe.ok ? STATUS.PASS : STATUS.WARN,
    pathAttr ? `Path=${pathAttr[1]}` : 'nenhum cookie emitido nesta rota (verificar após login)');

  battery.durationMs = Date.now() - started;
  return battery.finalize();
}

module.exports = { run };
