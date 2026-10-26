'use strict';
/*
 * Optional authenticated E2E for the native Three.js settings.
 * Runs only on a disposable test installation and restores the original values in finally.
 * Required env: ADMIN_SETTINGS_URL, PUBLIC_HOME_URL, PUBLIC_404_URL, PLAYWRIGHT_STORAGE_STATE.
 * Install Playwright outside the product dependencies.
 */
const fs = require('node:fs');
const path = require('node:path');

function required(name) {
  const value = process.env[name];
  if (!value) throw new Error(`Missing environment variable ${name}`);
  const url = new URL(value);
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error(`${name} must use HTTP(S)`);
  return url.href;
}
function assert(condition, message) { if (!condition) throw new Error(message); }
async function setField(page, name, value) {
  const field = page.locator(`[name="${name}"]`).first();
  if (await field.evaluate(element => element.tagName === 'SELECT')) await field.selectOption(String(value));
  else await field.fill(String(value));
}
function safeError(error) {
  return String(error && error.message || error).replace(/https?:\/\/\S+/g, '[url]').replace(/[\r\n\t]+/g, ' ').slice(0, 450);
}
const fieldNames = ['three_hero_enabled','three_not_found_enabled','three_hero_particle_count','three_hero_color','three_hero_speed'];

(async function main() {
  let browser;
  let context;
  let page;
  let settingsUrl;
  let original = null;
  let restoreFailed = false;
  try {
    let chromium;
    try { ({ chromium } = require('playwright')); }
    catch (_) { throw new Error('Playwright is missing; install it only in the local test environment.'); }
    assert(process.env.E2E_CONFIRM_DISPOSABLE === '1', 'Refusing to modify settings without E2E_CONFIRM_DISPOSABLE=1 on a disposable test site.');
    settingsUrl = required('ADMIN_SETTINGS_URL');
    const homeUrl = required('PUBLIC_HOME_URL');
    const notFoundUrl = required('PUBLIC_404_URL');
    assert(new URL(homeUrl).origin === new URL(notFoundUrl).origin, 'Homepage and 404 URLs must share the public origin.');
    const statePath = path.resolve(process.env.PLAYWRIGHT_STORAGE_STATE || '');
    assert(fs.existsSync(statePath) && fs.statSync(statePath).isFile(), 'PLAYWRIGHT_STORAGE_STATE must point to a local session-state file.');

    browser = await chromium.launch({ headless: process.env.HEADLESS !== '0' });
    context = await browser.newContext({ storageState: statePath });
    page = await context.newPage();
    const threeAssetFailures = [];
    page.on('response', response => {
      try {
        const pathname = new URL(response.url()).pathname;
        if (/\/(?:three\.module\.js|three\.core\.js|main\.js|scene-runner\.js|hero-particles\.js|not-found\.js|threejs-thumb\.(?:js|css))$/.test(pathname) && response.status() >= 400) threeAssetFailures.push(pathname);
      } catch (_) {}
    });
    page.setDefaultTimeout(15000);
    await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
    const fields = Object.fromEntries(await Promise.all(fieldNames.map(async name => {
      const field = page.locator(`[name="${name}"]`).first();
      await field.waitFor({ state: 'visible' });
      return [name, await field.inputValue()];
    })));
    original = fields;

    const submit = async values => {
      for (const [name,value] of Object.entries(values)) await setField(page, name, value);
      const save = page.locator('form button[type="submit"]').first();
      const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 });
      await save.click();
      await nav;
      const error = page.locator('.alert-danger').first();
      assert(await error.count() === 0, 'Settings form returned a validation error.');
    };

    const probe = {
      three_hero_enabled: '0', three_not_found_enabled: '0',
      three_hero_particle_count: '276', three_hero_color: '#2357b5', three_hero_speed: '0.07'
    };
    await submit(probe);
    await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
    for (const [name,value] of Object.entries(probe)) assert(await page.locator(`[name="${name}"]`).first().inputValue() === value, `Persisted setting mismatch: ${name}`);
    console.log('PASS admin persisted bounded Three.js settings through the standard Settings form.');

    await page.goto(homeUrl, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('[data-scene="hero-particles"]').count() > 0, 'Homepage hero scene container/fallback is missing.');
    assert(await page.locator('script[type="module"][src*="/three/main.js"]').count() === 0, 'Homepage loaded the native Three.js module while the scene was disabled.');
    await page.goto(notFoundUrl, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('[data-scene="not-found"]').count() > 0, '404 scene container/fallback is missing.');
    assert(await page.locator('script[type="module"][src*="/three/main.js"]').count() === 0, '404 loaded the native Three.js module while the scene was disabled.');
    console.log('PASS disabled scenes retain their fallback and do not enqueue the WebGL module.');

    await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
    await submit({ ...probe, three_hero_enabled: '1', three_not_found_enabled: '1' });
    for (const [url,sceneName] of [[homeUrl,'hero-particles'],[notFoundUrl,'not-found']]) {
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      assert(await page.locator(`[data-scene="${sceneName}"]`).count() > 0, `${sceneName} container is missing while enabled.`);
      assert(await page.locator('script[type="importmap"]').count() === 1, `${sceneName} route did not emit the conditional import map.`);
      const importMap = await page.locator('script[type="importmap"]').textContent();
      assert(importMap.includes('/catalog/view/js/three.module.js'), `${sceneName} import map is not self-hosted at the shared local module.`);
      assert(await page.locator('script[type="module"][src*="/three/main.js"]').count() === 1, `${sceneName} route did not load the native scene runner.`);
    }
    assert(threeAssetFailures.length === 0, `Three.js asset requests returned HTTP errors: ${threeAssetFailures.join(', ')}`);
    console.log('PASS enabled homepage and 404 emit the self-hosted import map and native scene runner without Three.js asset 4xx/5xx.');
  } catch (error) {
    console.error(`FAIL native Three.js E2E: ${safeError(error)}`);
    process.exitCode = 1;
  } finally {
    if (page && original && settingsUrl) {
      try {
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
        for (const [name,value] of Object.entries(original)) await setField(page, name, value);
        const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.locator('form button[type="submit"]').first().click();
        await nav;
        if (await page.locator('.alert-danger').count() > 0) throw new Error('Settings restore validation failed.');
        console.log('PASS original Three.js settings restored.');
      } catch (_) {
        restoreFailed = true;
        console.error('FAIL restoration: manually restore the original Three.js settings on the disposable test site.');
        process.exitCode = 1;
      }
    }
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    if (restoreFailed) process.exitCode = 1;
  }
})();
