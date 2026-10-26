'use strict';
/*
 * Read-only authenticated E2E for native Three.js scenes.
 * Uses GET navigation only: it does not submit forms, click save, or change settings.
 * Required env: ADMIN_SETTINGS_URL, PUBLIC_HOME_URL, PUBLIC_404_URL, PLAYWRIGHT_STORAGE_STATE.
 */
const fs = require('node:fs');
const path = require('node:path');

function safeUrl(name) {
  const raw = process.env[name];
  if (!raw) throw new Error(`Missing environment variable ${name}`);
  const url = new URL(raw);
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error(`${name} must use HTTP(S)`);
  return url.href;
}
function assert(condition, message) { if (!condition) throw new Error(message); }
function safeError(error) {
  return String(error && error.message || error).replace(/https?:\/\/\S+/g, '[url]').replace(/[\r\n\t]+/g, ' ').slice(0, 450);
}

(async function main() {
  let browser;
  let context;
  try {
    let chromium;
    try { ({ chromium } = require('playwright')); }
    catch (_) { throw new Error('Playwright is missing; install it only in the local test environment.'); }
    const settingsUrl = safeUrl('ADMIN_SETTINGS_URL');
    const homeUrl = safeUrl('PUBLIC_HOME_URL');
    const notFoundUrl = safeUrl('PUBLIC_404_URL');
    assert(new URL(homeUrl).origin === new URL(notFoundUrl).origin, 'Homepage and 404 URLs must share the public origin.');
    const statePath = path.resolve(process.env.PLAYWRIGHT_STORAGE_STATE || '');
    assert(fs.existsSync(statePath) && fs.statSync(statePath).isFile(), 'PLAYWRIGHT_STORAGE_STATE must point to a local session-state file.');

    browser = await chromium.launch({ headless: process.env.HEADLESS !== '0' });
    context = await browser.newContext({ storageState: statePath });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    const failures = [];
    page.on('response', response => {
      try {
        const pathname = new URL(response.url()).pathname;
        if (/\/(?:three\.module\.js|three\.core\.js|main\.js|scene-runner\.js|hero-particles\.js|not-found\.js|threejs-thumb\.(?:js|css))$/.test(pathname) && response.status() >= 400) failures.push(pathname);
      } catch (_) {}
    });

    // GET-only inspection of the admin values; never mutate or submit this form.
    await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
    const names = ['three_hero_enabled','three_not_found_enabled','three_hero_particle_count','three_hero_color','three_hero_speed'];
    const values = {};
    for (const name of names) {
      const field = page.locator(`[name="${name}"]`).first();
      await field.waitFor({ state: 'visible' });
      values[name] = await field.inputValue();
    }
    const heroEnabled = values.three_hero_enabled === '1';
    const notFoundEnabled = values.three_not_found_enabled === '1';
    const count = Number(values.three_hero_particle_count);
    const speed = Number(values.three_hero_speed);
    assert(['0','1'].includes(values.three_hero_enabled) && ['0','1'].includes(values.three_not_found_enabled), 'Stored Three.js toggles are not normalized.');
    assert(Number.isInteger(count) && count >= 50 && count <= 800, 'Stored particle count is outside the supported range.');
    assert(/^#[0-9a-fA-F]{6}$/.test(values.three_hero_color), 'Stored scene color is not a valid #RRGGBB value.');
    assert(Number.isFinite(speed) && speed >= 0.01 && speed <= 0.20, 'Stored scene speed is outside the supported range.');
    console.log('PASS read-only Admin settings page is authenticated and contains normalized scene values.');

    async function inspectRoute(url, sceneName, enabled) {
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      const scene = page.locator(`[data-scene="${sceneName}"]`).first();
      await scene.waitFor({ state: 'attached' });
      assert(await scene.locator('[data-fallback]').count() > 0, `${sceneName} fallback markup is missing.`);
      const moduleCount = await page.locator('script[type="module"][src*="/three/main.js"]').count();
      const mapCount = await page.locator('script[type="importmap"]').count();
      if (enabled) {
        assert(moduleCount === 1 && mapCount === 1, `${sceneName} is enabled but the conditional module/import map is missing.`);
        const map = await page.locator('script[type="importmap"]').textContent();
        assert(map.includes('/catalog/view/js/three.module.js'), `${sceneName} import map does not reference the local Three.js module.`);
      } else {
        assert(moduleCount === 0 && mapCount === 0, `${sceneName} is disabled but still enqueues the WebGL module.`);
      }
    }

    await inspectRoute(homeUrl, 'hero-particles', heroEnabled);
    await inspectRoute(notFoundUrl, 'not-found', notFoundEnabled);
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    assert(failures.length === 0, `Three.js asset request returned HTTP error: ${failures.join(', ')}`);
    console.log('PASS home and 404 match the saved toggles, preserve fallbacks, and use local assets without HTTP errors.');
    console.log('READ-ONLY: no form was submitted and no setting was changed.');
  } catch (error) {
    console.error(`FAIL read-only native Three.js E2E: ${safeError(error)}`);
    process.exitCode = 1;
  } finally {
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
  }
})();
