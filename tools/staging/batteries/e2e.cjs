'use strict';
/*
 * tools/staging — bateria E2E de navegador real (Playwright).
 *
 * Executa os runners autenticados do repositório contra as URLs do STAGING.
 * Depende de sessão local gravada (PLAYWRIGHT_STORAGE_STATE) — que NUNCA vai ao
 * git. Sem sessão, ou sem E2E_CONFIRM_DISPOSABLE=1 nos runners que gravam, a
 * bateria é NOT_RUN: um teste não executado não é um teste aprovado.
 *
 * Como preparar a sessão (uma vez, com o WAMP ligado):
 *   npx playwright codegen --save-storage=staging/reports/e2e-state.json \
 *       "http://localhost/davidcms/staging/admin/index.php?route=common/login"
 *   (faça login, feche o navegador, e exporte PLAYWRIGHT_STORAGE_STATE para o
 *    caminho absoluto do arquivo gerado)
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { STATUS, BatteryResult } = require('../lib/report.cjs');
const cfg = require('../lib/config.cjs');

const MUTATING = new Set(['threejs_cms_playwright.cjs', 'threejs_native_admin_playwright.cjs']);

async function run() {
  const battery = new BatteryResult('e2e', 'E2E em navegador real (Playwright)');
  const started = Date.now();

  if (!cfg.exists(path.join(cfg.ROOT, 'node_modules', 'playwright'))) {
    battery.check('Playwright instalado', STATUS.NOT_RUN, 'npm i --no-save playwright && npx playwright install chromium');
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }
  battery.check('Playwright instalado', STATUS.PASS, 'node_modules/playwright presente');

  const statePath = cfg.E2E.storageState ? path.resolve(cfg.E2E.storageState) : '';
  if (!statePath || !fs.existsSync(statePath)) {
    battery.check('sessão autenticada (PLAYWRIGHT_STORAGE_STATE)', STATUS.NOT_RUN,
      'defina PLAYWRIGHT_STORAGE_STATE para o arquivo de sessão local — veja tools/staging/README.md');
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }
  battery.check('sessão autenticada (PLAYWRIGHT_STORAGE_STATE)', STATUS.PASS, `estado local em ${statePath}`);

  const reachable = await (async () => {
    try {
      const res = await fetch(cfg.URLS.catalog, { redirect: 'manual' });
      return res.status;
    } catch (_) { return null; }
  })();
  if (reachable === null) {
    battery.check('staging acessível', STATUS.NOT_RUN, `${cfg.URLS.catalog} não responde — WAMP ligado?`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }

  const env = { ...process.env, ...cfg.E2E.urls, PLAYWRIGHT_STORAGE_STATE: statePath };
  env.HEADLESS = cfg.E2E.headless ? '1' : '0';
  if (cfg.E2E.confirmDisposable) env.E2E_CONFIRM_DISPOSABLE = '1';
  env.MIRROR = cfg.URLS.devCatalog;

  battery.note(`URLs E2E: ${JSON.stringify(cfg.E2E.urls, null, 0)}`);

  for (const runner of cfg.E2E.runners) {
    const file = path.join(cfg.TOOLS_E2E_DIR, runner);
    if (!fs.existsSync(file)) {
      battery.check(runner, STATUS.WARN, 'runner não encontrado no repositório');
      continue;
    }
    if (MUTATING.has(runner) && !cfg.E2E.confirmDisposable) {
      battery.check(runner, STATUS.NOT_RUN, 'runner grava conteúdo: exige E2E_CONFIRM_DISPOSABLE=1');
      continue;
    }
    const res = spawnSync(process.execPath, [file, cfg.ROOT], {
      cwd: cfg.ROOT,
      env,
      encoding: 'utf8',
      timeout: cfg.E2E.timeouts,
      maxBuffer: 16 * 1024 * 1024,
    });
    const out = `${res.stdout || ''}${res.stderr || ''}`.trim();
    const lastLine = out.split(/\r?\n/).filter(Boolean).pop() || '';
    if (res.error && res.error.code === 'ETIMEDOUT') {
      battery.check(runner, STATUS.FAIL, 'timeout');
    } else if (res.status === 0) {
      battery.check(runner, STATUS.PASS, lastLine.slice(0, 220));
    } else {
      battery.check(runner, STATUS.FAIL, `exit ${res.status} — ${lastLine.slice(0, 220)}`);
    }
  }

  battery.durationMs = Date.now() - started;
  return battery.finalize();
}

module.exports = { run };
