'use strict';
/*
 * Optional authenticated E2E runner. Use only on a disposable CMS test page.
 * Authentication stays in a local Playwright storage-state file; no credentials are read from CLI or logged.
 * Required env: CMS_EDIT_URL, PUBLIC_PAGE_URL, PLAYWRIGHT_STORAGE_STATE.
 * Install Playwright outside the CMS project: npm install --no-save playwright && npx playwright install chromium
 */
const fs = require('node:fs');
const path = require('node:path');

function required(name) {
  const value = process.env[name];
  if (!value) throw new Error(`Missing environment variable ${name}`);
  return value;
}
function safeUrl(name) {
  const url = new URL(required(name));
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error(`${name} must use HTTP(S)`);
  return url.href;
}
function assert(condition, message) { if (!condition) throw new Error(message); }

(async function main() {
  let browser;
  try {
    let chromium;
    try { ({ chromium } = require('playwright')); } catch (_) { throw new Error('Playwright is missing. Install it locally with npm install --no-save playwright, then install Chromium.'); }
    assert(process.env.E2E_CONFIRM_DISPOSABLE === '1', 'This test edits and saves page content. Set E2E_CONFIRM_DISPOSABLE=1 only for a disposable CMS test page.');
    const adminUrl = safeUrl('CMS_EDIT_URL');
    const publicUrl = safeUrl('PUBLIC_PAGE_URL');
    const statePath = path.resolve(required('PLAYWRIGHT_STORAGE_STATE'));
    assert(fs.existsSync(statePath), 'Storage-state file was not found. Keep it local; do not commit or share it.');
    const stateStat = fs.statSync(statePath);
    assert(stateStat.isFile(), 'PLAYWRIGHT_STORAGE_STATE must be a local file path.');

    browser = await chromium.launch({ headless: process.env.HEADLESS !== '0' });
    const context = await browser.newContext({ storageState: statePath });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    const marker = `/* BDREditor-E2E-${Date.now()} */`;
    const userScript = `${marker}\nconst mesh = new THREE.Mesh(new THREE.BoxGeometry(0.8,0.8,0.8), new THREE.MeshStandardMaterial({color:0xff33aa}));\nmesh.position.set(1,0,0); scene.add(mesh);\nlet ticks=0; onFrame(({delta})=>{ mesh.rotation.y += delta*0.6; if(++ticks===1) canvas.dataset.e2eFrame='1'; });`;

    await page.goto(adminUrl, { waitUntil: 'domcontentloaded' });
    const root = page.locator('[data-blog-block-editor]:visible').first();
    await root.waitFor({ state: 'visible' });
    await page.waitForFunction(element => element.classList.contains('rb-editor') && element.dataset.valid === 'true', await root.elementHandle());

    await root.locator('[data-action="toggle-inserter"]').first().click();
    await root.locator('.rb-inserter-option[data-type="core/threejs_thumb"]').click();
    const block = root.locator('.rb-type-core-threejs-thumb').last();
    await block.waitFor({ state: 'visible' });
    await block.locator('[data-action="threejs-toggle-editor"]').click();
    const scriptField = block.locator('textarea[data-attribute="custom_js"]');
    await scriptField.fill(userScript);
    await block.locator('[data-action="threejs-add-object"]').click();
    await block.locator('[data-action="threejs-add-light"]').click();
    assert(await root.getAttribute('data-valid') === 'true', 'Editor validation failed after adding scene objects/script.');

    const postResponse = page.waitForResponse(response => {
      try { return response.request().method() === 'POST' && new URL(response.url()).origin === new URL(adminUrl).origin; }
      catch (_) { return false; }
    }, { timeout: 30000 });
    await page.locator('[data-cms-page-form] button[type="submit"]').first().click();
    const response = await postResponse;
    assert(response.status() < 400, `CMS save returned HTTP ${response.status()}.`);
    await page.goto(adminUrl, { waitUntil: 'domcontentloaded' });
    const reopenedRoot = page.locator('[data-blog-block-editor]:visible').first();
    await page.waitForFunction(element => element.classList.contains('rb-editor') && element.dataset.valid === 'true', await reopenedRoot.elementHandle());
    const toggles = reopenedRoot.locator('.rb-type-core-threejs-thumb [data-action="threejs-toggle-editor"]');
    for (let i = 0; i < await toggles.count(); i += 1) {
      const toggle = toggles.nth(i);
      if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
    }
    const savedScripts = await reopenedRoot.locator('textarea[data-attribute="custom_js"]').evaluateAll(fields => fields.map(field => field.value));
    assert(savedScripts.some(value => value.includes(marker)), 'Saved script did not survive reopening the CMS page.');
    console.log('PASS CMS editor inserted the block, changed scene/script, saved, and reopened the data.');

    await page.goto(publicUrl, { waitUntil: 'domcontentloaded' });
    const thumbs = page.locator('[data-threejs-thumb][data-custom-js]');
    await thumbs.first().waitFor({ state: 'attached' });
    const targetIndex = await thumbs.evaluateAll((elements, needle) => elements.findIndex(element => {
      try {
        const raw = atob(element.getAttribute('data-custom-js') || '');
        const bytes = Uint8Array.from(raw, character => character.charCodeAt(0));
        return new TextDecoder().decode(bytes).includes(needle);
      } catch (_) { return false; }
    }), marker);
    assert(targetIndex >= 0, 'The public renderer did not emit the saved script in a thumbnail.');
    const thumb = thumbs.nth(targetIndex);
    await thumb.scrollIntoViewIfNeeded();
    await page.waitForFunction(index => {
      const item = document.querySelectorAll('[data-threejs-thumb][data-custom-js]')[index];
      return item && ['custom-script','error'].includes(item.dataset.threejsReady || '');
    }, targetIndex, { timeout: 30000 });
    const runtimeState = await thumb.getAttribute('data-threejs-ready');
    if (runtimeState !== 'custom-script') {
      const diagnostic = await thumb.locator('.rms-block-threejs-thumb__status').textContent().catch(() => 'no diagnostic text');
      throw new Error(`Public thumb runtime reported an error: ${String(diagnostic || 'unknown').slice(0, 300)}`);
    }
    const frame = thumb.locator('iframe.rms-block-threejs-thumb__sandbox');
    await frame.waitFor({ state: 'attached' });
    assert(await frame.getAttribute('sandbox') === 'allow-scripts', 'Custom script iframe sandbox flags are not restricted.');
    const canvas = thumb.frameLocator('iframe.rms-block-threejs-thumb__sandbox').locator('canvas#stage');
    await canvas.waitFor({ state: 'visible' });
    let frameTick = false;
    for (let attempt = 0; attempt < 20 && !frameTick; attempt += 1) {
      frameTick = (await canvas.getAttribute('data-e2e-frame')) === '1';
      if (!frameTick) await page.waitForTimeout(250);
    }
    assert(frameTick, 'The custom onFrame callback did not run in the public sandbox.');
    console.log('PASS public page rendered the isolated script and the onFrame callback executed.');
    await context.close();
  } catch (error) {
    const message = String(error && error.message || error).replace(/[\r\n\t]+/g, ' ').slice(0, 500);
    console.error(`FAIL E2E: ${message}`);
    process.exitCode = 1;
  } finally {
    if (browser) await browser.close();
  }
})();
