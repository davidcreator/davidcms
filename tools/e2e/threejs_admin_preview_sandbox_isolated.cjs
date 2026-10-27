'use strict';
/*
 * Isolated browser smoke test for the self-hosted Three.js custom-script sandbox.
 * Serves only the three local runtime assets and an in-memory fixture. It never
 * opens the CMS, reads credentials, writes application data, or calls external hosts.
 * Run from the DavidCMS root; requires Playwright in the local QA environment.
 */
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

function assert(condition, message) { if (!condition) throw new Error(message); }
function safeError(error) { return String(error && error.message || error).replace(/[\r\n\t]+/g, ' ').slice(0, 400); }

(async function main() {
  let browser;
  let server;
  try {
    let chromium;
    try { ({ chromium } = require('playwright')); }
    catch (_) { throw new Error('Playwright is missing; install it only in the local QA environment.'); }

    const root = process.cwd();
    const assets = new Map([
      ['/threejs-thumb.js', 'dev/system/library/three/runtime/threejs-thumb.js'],
      ['/three.module.js', 'dev/system/library/three/runtime/three.module.js'],
      ['/three.core.js', 'dev/system/library/three/runtime/three.core.js']
    ]);
    for (const relative of assets.values()) {
      assert(fs.existsSync(path.join(root, relative)), `Missing local runtime asset: ${relative}`);
    }

    const scene = {
      version: 1,
      background: '#17243a',
      camera: { fov: 45, position: [0, 0, 4] },
      lights: [{ type: 'ambient', color: '#ffffff', intensity: 1 }],
      objects: [{ type: 'box', color: '#ff66aa', position: [0, 0, 0], rotation: [0, 0, 0], scale: 1, animation: 'none' }],
      auto_rotate: false
    };
    const customScript = "parent.postMessage({type:'threejs-sandbox-probe',marker:'utf8-✓-á'},'*');";
    const sceneJson = JSON.stringify(scene).replace(/</g, '\\u003c');
    const scriptBase64 = Buffer.from(customScript, 'utf8').toString('base64');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Isolated Three.js QA</title><style>body{margin:0;background:#f5f7fb;font:16px sans-serif}#stage{width:480px;height:320px;position:relative}.rms-block-threejs-thumb__stage{width:100%;height:100%;position:relative}.rms-block-threejs-thumb__fallback{display:block}</style></head><body><div id="stage" data-threejs-thumb="" data-scene='${sceneJson}' data-custom-js="${scriptBase64}" aria-label="Isolated test scene"><span class="rms-block-threejs-thumb__fallback">Fallback</span></div><script>window.__probe=null;window.__messages=[];addEventListener('message',e=>{window.__messages.push({origin:e.origin,data:e.data});if(e.data&&e.data.type==='threejs-sandbox-probe')window.__probe={origin:e.origin,data:e.data};});</script><script src="/threejs-thumb.js"></script></body></html>`;

    server = http.createServer((request, response) => {
      const pathname = new URL(request.url, 'http://127.0.0.1').pathname;
      if (request.method !== 'GET') { response.writeHead(405); response.end(); return; }
      if (pathname === '/') { response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store' }); response.end(html); return; }
      const relative = assets.get(pathname);
      if (!relative) { response.writeHead(404); response.end(); return; }
      const file = path.join(root, relative);
      const type = pathname.endsWith('.js') ? 'text/javascript; charset=utf-8' : 'application/octet-stream';
      response.writeHead(200, { 'content-type': type, 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
      fs.createReadStream(file).pipe(response);
    });
    await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
    const address = server.address();
    const baseUrl = `http://127.0.0.1:${address.port}`;
    const externalRequests = [];
    browser = await chromium.launch({
      headless: process.env.HEADLESS !== '0',
      args: ['--enable-webgl', '--ignore-gpu-blocklist', '--use-gl=angle', '--use-angle=swiftshader']
    });
    const context = await browser.newContext({ viewport: { width: 800, height: 600 } });
    await context.route('**/*', route => {
      const url = new URL(route.request().url());
      if (url.protocol === 'data:') return route.continue();
      if (url.origin !== baseUrl) { externalRequests.push(url.origin); return route.abort(); }
      return route.continue();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    await page.goto(baseUrl, { waitUntil: 'load' });

    const frame = page.locator('iframe.rms-block-threejs-thumb__sandbox');
    await frame.waitFor({ state: 'attached' });
    assert(await frame.getAttribute('sandbox') === 'allow-scripts', 'Sandbox iframe must have only allow-scripts (no allow-same-origin).');
    await page.waitForFunction(() => window.__probe || document.querySelector('#stage')?.dataset.threejsReady === 'error', null, { timeout: 20000 }).catch(() => {});
    const result = await page.evaluate(() => ({
      probe: window.__probe,
      messages: window.__messages,
      ready: document.querySelector('#stage')?.dataset.threejsReady || '',
      frameCount: document.querySelectorAll('#stage iframe.rms-block-threejs-thumb__sandbox').length,
      sandbox: document.querySelector('#stage iframe.rms-block-threejs-thumb__sandbox')?.getAttribute('sandbox') || '',
      runtimeError: document.querySelector('#stage .rms-block-threejs-thumb__status')?.textContent || ''
    }));
    assert(result.frameCount === 1, 'Expected one isolated iframe for custom_js.');
    assert(result.sandbox === 'allow-scripts', 'Iframe sandbox flags are not restricted to allow-scripts.');
    assert(result.probe && result.probe.origin === 'null', 'Custom script did not report from an opaque (null) iframe origin. ' + (result.runtimeError || ''));
    assert(result.probe.data.marker === 'utf8-✓-á', 'UTF-8 custom script did not round-trip into the sandbox.');
    assert(externalRequests.length === 0, `Unexpected non-local request attempted: ${externalRequests.join(', ')}`);
    assert(result.ready === 'custom-script', `Sandbox runtime did not report ready (state: ${result.ready || 'unset'}).`);
    console.log('PASS custom_js executed in a single opaque sandbox iframe (sandbox=allow-scripts only).');
    console.log('PASS UTF-8 payload round-tripped; no non-local requests were made.');
    console.log('ISOLATED QA ONLY: no CMS URL, credentials, cookies, storage state, or application writes were used.');
    await context.close();
  } catch (error) {
    console.error(`FAIL isolated Three.js sandbox browser test: ${safeError(error)}`);
    process.exitCode = 1;
  } finally {
    if (browser) await browser.close().catch(() => {});
    if (server) await new Promise(resolve => server.close(resolve));
  }
})();
