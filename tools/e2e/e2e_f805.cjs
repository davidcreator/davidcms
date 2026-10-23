#!/usr/bin/env node
'use strict';
/** Fase 8 / Tarefa 05 — preflight local e testes de segurança do Tool de navegador.
 * Somente módulos nativos Node 20+. Não chama HTTP, PHP, SQL, shell ou navegador.
 * --prepare-browser cria dois arquivos QA temporários no FUI autorizado.
 * --clean-browser remove SOMENTE esses dois arquivos, conferindo nonce/hashes.
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const os = require('node:os');
const assert = require('node:assert/strict');
const browser = require('./e2e_f805_browser.js');
const h = browser.helpers;
const ROOT = path.resolve(__dirname, '../..');
const BROWSER_SOURCE = path.join(__dirname, 'e2e_f805_browser.js');
const ASSETS = ['admin/view/js/admin.js', 'admin/view/js/blog-media.js', 'admin/view/js/ckeditor/ckeditor.js'];
const FORM = 'admin/view/template/blog/blog_form.twig';
const sha256 = data => crypto.createHash('sha256').update(data).digest('hex');
function ensure(condition, message) { if (!condition) throw new Error(message); }

// Remove comentários sem interpretar/executar PHP; strings permanecem intactas.
function stripPhpComments(source) {
  let state = 'code'; let result = '';
  for (let i = 0; i < source.length; i++) {
    const c = source[i]; const next = source[i + 1];
    if (state === 'single' || state === 'double') {
      result += c;
      if (c === '\\' && next !== undefined) { result += next; i++; }
      else if (c === (state === 'single' ? "'" : '"')) state = 'code';
    } else if (state === 'line') {
      result += c === '\n' ? '\n' : ' ';
      if (c === '\n') state = 'code';
    } else if (state === 'block') {
      if (c === '*' && next === '/') { result += '  '; i++; state = 'code'; }
      else result += c === '\n' ? '\n' : ' ';
    } else if (c === "'" || c === '"') { state = c === "'" ? 'single' : 'double'; result += c; }
    else if (c === '/' && next === '/') { result += '  '; i++; state = 'line'; }
    else if (c === '#') { result += ' '; state = 'line'; }
    else if (c === '/' && next === '*') { result += '  '; i++; state = 'block'; }
    else result += c;
  }
  ensure(state !== 'block' && state !== 'single' && state !== 'double', 'Config PHP não reconhecida/sem fechamento.');
  return result;
}
function phpLiteral(source, key) {
  ensure(/^[A-Z_]+$/.test(key), 'Nome de constante inválido.');
  // Aceita só o formato plano do wizard: define(...);, sem include, if,
  // variáveis/instruções adicionais. Não confundir um define dentro de STRING
  // ou comentário com uma configuração executável. Não executar PHP.
  let rest = stripPhpComments(source).trim();
  ensure(rest.startsWith('<?php'), 'Config fora do formato PHP gerado pelo wizard.');
  rest = rest.slice(5).trim();
  const found = [];
  while (rest.length) {
    const head = rest.match(/^define\s*\(\s*(['"])([A-Z_]+)\1\s*,\s*/);
    ensure(head, 'Config contém instrução fora do formato plano do wizard.');
    let quote = null; let end = -1;
    for (let i = head[0].length; i < rest.length; i++) {
      const c = rest[i];
      if (quote) {
        if (c === '\\') i++;
        else if (c === quote) quote = null;
      } else if (c === "'" || c === '"') quote = c;
      else if (c === ')') { end = i; break; }
      else ensure(c !== '(' && c !== ';', 'Config dinâmica não é aceita pelo preflight.');
    }
    ensure(end >= 0 && /^\)\s*;/.test(rest.slice(end)), 'Definição PHP não reconhecida.');
    if (head[2] === key) {
      const expression = rest.slice(head[0].length, end).trim();
      const literal = expression.match(/^(?:'([^'\\]*)'|"([^"\\$]*)")$/);
      ensure(literal, key + ' precisa ser literal, sem expressão/interpolação.');
      found.push(literal[1] === undefined ? literal[2] : literal[1]);
    }
    rest = rest.slice(end).replace(/^\)\s*;/, '').trim();
  }
  ensure(found.length === 1, key + ' precisa ter uma única definição literal reconhecida.');
  return found[0];
}
function realDirectory(value, label) {
  ensure(typeof value === 'string' && path.isAbsolute(value) && path.resolve(value) !== path.parse(value).root, label + ' precisa ser caminho absoluto, diferente de /.');
  const resolved = path.resolve(value);
  ensure(fs.statSync(resolved).isDirectory() && fs.realpathSync(resolved) === resolved, label + ' precisa existir sem symlink no caminho.');
  return resolved;
}
function plainFile(root, relative) {
  const target = path.resolve(root, relative);
  ensure(target.startsWith(root + path.sep), 'Arquivo fora da raiz autorizada.');
  let current = root;
  for (const segment of relative.split('/')) {
    ensure(segment && segment !== '.' && segment !== '..', 'Caminho relativo inválido.');
    current = path.join(current, segment);
    ensure(!fs.lstatSync(current).isSymbolicLink(), 'Symlink recusado no laboratório.');
  }
  ensure(fs.statSync(target).isFile(), 'Arquivo obrigatório ausente.');
  return target;
}
function sourceContracts(mirror) {
  const admin = fs.readFileSync(plainFile(mirror, ASSETS[0]), 'utf8');
  const media = fs.readFileSync(plainFile(mirror, ASSETS[1]), 'utf8');
  const form = fs.readFileSync(plainFile(mirror, FORM), 'utf8');
  ensure(/customConfig\s*:\s*['"]['"]/.test(admin) && /function initRichText\(/.test(admin), 'Inicializador RTE aplicado não foi encontrado.');
  ensure(/function initBlogMedia\(/.test(media) && /DOMContentLoaded/.test(media), 'Boot de mídia aplicado não foi encontrado.');
  ['blog_description[{{ language.language_id }}][description]', 'id="input-description-{{ language.language_id }}"', 'name="csrf_token"', 'name="image" id="input-image"', 'id="blogMediaModal"', 'id="blogMediaFile"'].forEach(text => ensure(form.includes(text), 'Contrato Twig obrigatório ausente: ' + text));
}
function preflight(env) {
  ensure(env.ALLOW_F805_BROWSER === h.CONFIRM, 'Preparação bloqueada: ALLOW_F805_BROWSER=YES_WRITE_REAMUR_TEST é obrigatório.');
  ensure(env.B && env.FUI && env.MIRROR, 'Defina B, FUI e MIRROR explicitamente.');
  const base = h.normalizeBase(env.B);
  const fui = realDirectory(env.FUI, 'FUI');
  const mirror = realDirectory(env.MIRROR, 'MIRROR');
  ensure(fs.readFileSync(plainFile(fui, '.f805-disposable'), 'utf8').trim() === 'reamur_test', 'Marcador .f805-disposable inválido.');
  for (const app of ['admin', 'catalog']) {
    const configFile = plainFile(fui, app + '/config.php');
    ensure(fs.statSync(configFile).size < 65536, 'Config PHP maior que o formato esperado.');
    const source = fs.readFileSync(configFile, 'utf8');
    ensure(phpLiteral(source, 'DB_DATABASE') === 'reamur_test', 'Banco real do config não é reamur_test; operação bloqueada.');
    ensure(['localhost', '127.0.0.1', '::1', '[::1]'].includes(phpLiteral(source, 'DB_HOSTNAME')), 'Banco precisa estar em loopback.');
    ensure(phpLiteral(source, 'HTTP_SERVER') === base + app + '/', 'HTTP_SERVER não corresponde a B no config da aplicação.');
  }
  sourceContracts(mirror);
  const hashes = {};
  for (const relative of [...ASSETS, FORM]) {
    const source = fs.readFileSync(plainFile(mirror, relative));
    const served = fs.readFileSync(plainFile(fui, relative));
    ensure(source.equals(served), 'MIRROR/FUI divergem: ' + relative);
    if (ASSETS.includes(relative)) hashes[relative] = sha256(source);
  }
  return { base, fui, mirror, hashes };
}
function pathsFor(fui, nonce) {
  ensure(h.nonceValid(nonce), 'Nonce inválido.');
  return {
    permit: path.join(fui, 'admin/view/js/f805-' + nonce + '.json'),
    tool: path.join(fui, 'admin/view/js/f805-' + nonce + '.js')
  };
}
function prepare(env, options = {}) {
  const settings = preflight(env);
  const nonce = options.nonce || crypto.randomBytes(16).toString('hex');
  const now = options.now === undefined ? Date.now() : options.now;
  const targets = pathsFor(settings.fui, nonce);
  const toolBytes = fs.readFileSync(BROWSER_SOURCE);
  const permit = {
    kind: h.KIND, nonce, runId: h.runIdFor(nonce), base: settings.base, database: 'reamur_test',
    createdAt: now, expiresAt: now + h.TTL, hashes: settings.hashes, toolHash: sha256(toolBytes),
    toolBaseCommit: 'f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a'
  };
  h.validatePermit(permit, settings.base, nonce, now);
  // wx: nunca sobrescrever arquivo preexistente, mesmo com nonce reaproveitado.
  let madeTool = false;
  try {
    fs.writeFileSync(targets.tool, toolBytes, { flag: 'wx', mode: 0o644 }); madeTool = true;
    fs.writeFileSync(targets.permit, JSON.stringify(permit, null, 2) + '\n', { flag: 'wx', mode: 0o644 });
  } catch (error) {
    if (madeTool) fs.unlinkSync(targets.tool);
    throw error;
  }
  const toolUrl = new URL('admin/view/js/f805-' + nonce + '.js', settings.base).href;
  // Sem eval, credenciais ou tokens. Executa somente após a ação consciente do operador.
  const launch = '(function () {\n' +
    '  var script = document.createElement("script");\n' +
    '  script.src = ' + JSON.stringify(toolUrl) + ';\n' +
    '  script.onload = function () { console.info("Tool local carregado. Leia e execute o segundo comando impresso pelo CLI."); };\n' +
    '  script.onerror = function () { console.error("Tool local não carregou; confira a preparação/CSP."); };\n' +
    '  document.head.appendChild(script);\n' +
    '})();';
  const runCommand = 'ReamurF805.run(' + JSON.stringify({ base: settings.base, nonce, confirm: h.CONFIRM }) + ')';
  return { settings, nonce, targets, permit, launch, runCommand };
}
function clean(env) {
  const settings = preflight(env);
  const nonce = env.F805_NONCE;
  const targets = pathsFor(settings.fui, nonce);
  const permitFile = plainFile(settings.fui, path.relative(settings.fui, targets.permit).split(path.sep).join('/'));
  const permit = JSON.parse(fs.readFileSync(permitFile, 'utf8'));
  // Permissão expirada pode ser limpa; não pode executar novos testes.
  ensure(permit.kind === h.KIND && permit.nonce === nonce && permit.base === settings.base && permit.runId === h.runIdFor(nonce), 'Arquivo temporário não pertence ao ensaio informado.');
  const toolFile = plainFile(settings.fui, path.relative(settings.fui, targets.tool).split(path.sep).join('/'));
  ensure(/^[a-f0-9]{64}$/.test(permit.toolHash || '') && sha256(fs.readFileSync(toolFile)) === permit.toolHash, 'Tool temporário foi modificado; não apagar automaticamente.');
  fs.unlinkSync(toolFile); fs.unlinkSync(permitFile);
  return targets;
}

function selfTest() {
  let passed = 0; let failed = 0;
  function test(label, fn) {
    try { fn(); passed++; console.log('PASS ' + label); }
    catch (error) { failed++; console.error('FAIL ' + label + ': ' + error.message); }
  }
  const base = 'http://127.0.0.1:8090/davidcms/dev/';
  const nonce = '0123456789abcdef0123456789abcdef';
  const runId = h.runIdFor(nonce); const now = Date.now();
  const assetHashes = Object.fromEntries(ASSETS.map(p => [p, 'a'.repeat(64)]));
  const permit = { kind: h.KIND, nonce, runId, base, database: 'reamur_test', createdAt: now, expiresAt: now + h.TTL, hashes: assetHashes };
  function laboratory(fn) {
    const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'f805-unit-'));
    const fui = path.join(tmp, 'served'); const mirror = path.join(tmp, 'mirror');
    const env = { ALLOW_F805_BROWSER: h.CONFIRM, B: base, FUI: fui, MIRROR: mirror };
    const sources = {
      [ASSETS[0]]: "(function initRichText() { var config = { customConfig: '' }; })();\n",
      [ASSETS[1]]: "(function initBlogMedia() { var event = 'DOMContentLoaded'; })();\n",
      [ASSETS[2]]: '// Core NÃO executado: somente fixture pequena dos guardas.\n',
      [FORM]: '<textarea name="blog_description[{{ language.language_id }}][description]" id="input-description-{{ language.language_id }}"></textarea><input name="csrf_token"><input name="image" id="input-image"><div id="blogMediaModal"></div><input id="blogMediaFile">'
    };
    function put(root, relative, content) { const p = path.join(root, relative); fs.mkdirSync(path.dirname(p), { recursive: true }); fs.writeFileSync(p, content); return p; }
    for (const root of [fui, mirror]) for (const [relative, data] of Object.entries(sources)) put(root, relative, data);
    put(fui, '.f805-disposable', 'reamur_test\n');
    for (const app of ['admin', 'catalog']) put(fui, app + '/config.php', "<?php\ndefine('DB_DATABASE', 'reamur_test');\ndefine('DB_HOSTNAME', '127.0.0.1');\ndefine('HTTP_SERVER', '" + base + app + "/');\ndefine('DB_PASSWORD', 'FIXTURE_SECRET_DO_NOT_EXPORT');\n");
    try { fn({ fui, mirror, env, put }); } finally { fs.rmSync(tmp, { recursive: true, force: true }); }
  }
  test('01 · bases loopback canônicas IPv4/IPv6/localhost', () => {
    assert.equal(h.normalizeBase(base.slice(0, -1)), base);
    assert.equal(h.normalizeBase('https://localhost:8443/dev'), 'https://localhost:8443/dev/');
    assert.equal(h.normalizeBase('http://[::1]:8090/dev/'), 'http://[::1]:8090/dev/');
  });
  test('02 · produção/hosts externos recusados', () => { for (const b of ['https://davidalmeida.xyz/dev', 'https://example.com/dev', 'http://localhost.evil.test/dev']) assert.throws(() => h.normalizeBase(b)); });
  test('03 · credenciais/query/fragmento recusados em B', () => { for (const b of ['http://x:y@localhost/dev', 'http://localhost/dev?q=1', 'http://localhost/dev#x']) assert.throws(() => h.normalizeBase(b)); });
  test('04 · protocolo, app e path codificado recusados', () => { for (const b of ['file:///tmp/x', 'ftp://localhost/dev', 'http://localhost/dev/admin/', 'http://localhost/d%2fev']) assert.throws(() => h.normalizeBase(b)); });
  test('05 · URLs não podem escapar da base/origem', () => { for (const u of ['http://localhost:8090/davidcms/dev/admin/', 'http://127.0.0.1:8090/davidcms/devil/admin/', 'https://example.com/', '../outside', 'javascript:alert(1)']) assert.throws(() => h.scopedUrl(u, base)); });
  test('06 · gramática query e pretty das rotas existentes', () => {
    assert.equal(h.routeOf(base + 'admin/index.php?route=blog%2Fblog.edit%7C47', base), 'blog/blog.edit|47');
    assert.equal(h.routeOf(base + 'admin/blog/blog.edit%7C47', base), 'blog/blog.edit|47');
    assert.equal(h.postIdOf(base + 'admin/index.php?route=blog/blog.delete|47', base, 'delete'), 47);
  });
  test('07 · ID nunca presumido/zero/negativo/fora do inteiro seguro', () => { for (const id of ['0', '-1', '1e2', '9007199254740992', '1/2']) assert.throws(() => h.postIdOf(base + 'admin/index.php?route=blog/blog.edit|' + id, base, 'edit')); });
  test('08 · arquivo de outra pasta/traversal/URL recusado', () => {
    assert.equal(h.ownedImage('view/image/blog/' + runId + '/20261023-a.png', runId), '20261023-a.png');
    for (const p of ['view/image/blog/other/a.png', 'view/image/blog/' + runId + '/../a.png', 'view/image/blog/' + runId + '/%2e.png', 'https://example.com/a.png']) assert.throws(() => h.ownedImage(p, runId));
  });
  test('09 · nonce e identificador restritos, sem path livre', () => { assert.equal(runId, 'f805-0123456789abcdef'); for (const n of ['', '../x', 'A'.repeat(32), 'a'.repeat(31)]) assert.throws(() => h.runIdFor(n)); });
  test('10 · permissão válida de 15 minutos', () => { assert.equal(h.validatePermit(permit, base, nonce, now), permit); });
  test('11 · permissão de outro nonce/base/banco recusada', () => { for (const patch of [{ nonce: 'f'.repeat(32) }, { base: 'http://localhost/dev/' }, { database: 'production' }, { kind: 'other' }]) assert.throws(() => h.validatePermit({ ...permit, ...patch }, base, nonce, now)); });
  test('12 · permissão vencida/futura/prazo adulterado recusada', () => {
    assert.throws(() => h.validatePermit(permit, base, nonce, now + h.TTL));
    assert.throws(() => h.validatePermit({ ...permit, createdAt: now + 120000, expiresAt: now + 120000 + h.TTL }, base, nonce, now));
    assert.throws(() => h.validatePermit({ ...permit, expiresAt: now + h.TTL * 2 }, base, nonce, now));
  });
  test('13 · hashes precisam ter os três assets exatos', () => {
    assert.throws(() => h.validatePermit({ ...permit, hashes: {} }, base, nonce, now));
    assert.throws(() => h.validatePermit({ ...permit, hashes: { ...assetHashes, other: 'a'.repeat(64) } }, base, nonce, now));
  });
  test('14 · payload multilíngue atual e rascunho; dados não são relatório', () => {
    const rows = [{ id: '1', code: 'en-us' }, { id: '8', code: 'pt-br' }, { id: '9', code: 'es-eu' }];
    const payload = { image: 'own.png', published: false, csrfPresent: true, titles: {}, keywords: {}, bodies: {} };
    for (const row of rows) { const x = h.expected(runId, row.code, 2); payload.titles[row.id] = x.title; payload.keywords[row.id] = x.keyword; payload.bodies[row.id] = x.html; }
    h.assertPayload(payload, rows, runId, 2, 'own.png');
    assert.throws(() => h.assertPayload(payload, rows, runId, 1, 'own.png'));
    assert.throws(() => h.assertPayload({ ...payload, published: true }, rows, runId, 2, 'own.png'));
    assert.throws(() => h.assertPayload({ ...payload, csrfPresent: false }, rows, runId, 2, 'own.png'));
  });
  test('15 · redação de tokens/senha no erro', () => { const s = h.redact('csrf_token=abc&user_token=def password=ghi nonce=jkl \"csrfToken\":\"mno\" \"userToken\":\"pqr\"'); for (const x of ['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr']) assert.ok(!s.includes(x)); });
  test('16 · constantes PHP literais, sem executar PHP', () => {
    assert.equal(phpLiteral("<?php define('DB_DATABASE', 'reamur_test');", 'DB_DATABASE'), 'reamur_test');
    assert.equal(phpLiteral('<?php define("DB_DATABASE", "reamur_test");', 'DB_DATABASE'), 'reamur_test');
  });
  test('17 · define em comentários não satisfaz a guarda', () => {
    assert.throws(() => phpLiteral("<?php /*\ndefine('DB_DATABASE', 'reamur_test');\n*/", 'DB_DATABASE'));
    assert.equal(phpLiteral("<?php // define('DB_DATABASE','wrong');\ndefine('DB_DATABASE','reamur_test');", 'DB_DATABASE'), 'reamur_test');
  });
  test('18 · expressões/duplicatas/interpolação PHP recusadas', () => {
    for (const source of ["<?php define('DB_DATABASE', getenv('DB'));", "<?php define('DB_DATABASE','a');define('DB_DATABASE','b');", '<?php define("DB_DATABASE", "$db");', '<?php $decoy = "define(\'DB_DATABASE\', \'reamur_test\');";', "<?php if (false) define('DB_DATABASE','reamur_test');", "<?php define('DB_DATABASE','reamur_test'); include 'other.php';"]) assert.throws(() => phpLiteral(source, 'DB_DATABASE'));
  });
  test('19 · sem confirmação: bloqueia antes de ler/escrever ambiente', () => { assert.throws(() => preflight({}), /bloqueada/); });
  test('20 · B/FUI/MIRROR precisam ser explícitos', () => { for (const key of ['B', 'FUI', 'MIRROR']) laboratory(({ env }) => { delete env[key]; assert.throws(() => preflight(env), /explicitamente/); }); });
  test('21 · preflight positivo da fixture, sem servidor/core/banco', () => laboratory(({ env }) => { assert.equal(Object.keys(preflight(env).hashes).length, 3); }));
  test('22 · marcador ausente/incorreto bloqueia', () => laboratory(({ env, fui }) => {
    const file = path.join(fui, '.f805-disposable'); fs.writeFileSync(file, 'production'); assert.throws(() => preflight(env)); fs.unlinkSync(file); assert.throws(() => preflight(env));
  }));
  test('23 · config de banco não descartável bloqueia', () => laboratory(({ env, fui }) => { const p = path.join(fui, 'admin/config.php'); fs.writeFileSync(p, fs.readFileSync(p, 'utf8').replace('reamur_test', 'production')); assert.throws(() => preflight(env), /Banco real/); }));
  test('24 · banco remoto/HTTP_SERVER externo bloqueiam', () => {
    laboratory(({ env, fui }) => { const p = path.join(fui, 'catalog/config.php'); fs.writeFileSync(p, fs.readFileSync(p, 'utf8').replace("'127.0.0.1'", "'db.example.com'")); assert.throws(() => preflight(env), /loopback/); });
    laboratory(({ env, fui }) => { const p = path.join(fui, 'admin/config.php'); fs.writeFileSync(p, fs.readFileSync(p, 'utf8').replace(base + 'admin/', 'https://example.com/admin/')); assert.throws(() => preflight(env), /HTTP_SERVER/); });
  });
  test('25 · symlink no asset é recusado', () => laboratory(({ env, fui, mirror }) => { const p = path.join(fui, ASSETS[0]); fs.unlinkSync(p); fs.symlinkSync(path.join(mirror, ASSETS[0]), p); assert.throws(() => preflight(env), /Symlink/); }));
  test('26 · divergência MIRROR/FUI bloqueia', () => laboratory(({ env, fui }) => { fs.appendFileSync(path.join(fui, ASSETS[1]), '\n// divergiu'); assert.throws(() => preflight(env), /divergem/); }));
  test('27 · precondição do RTE antigo é recusada', () => laboratory(({ env, mirror }) => { fs.writeFileSync(path.join(mirror, ASSETS[0]), 'window.CKEDITOR.replace(ta, {});'); assert.throws(() => preflight(env), /RTE/); }));
  test('28 · preparar cria só dois QA temporários, sem secrets', () => laboratory(({ env, fui }) => {
    const result = prepare(env, { nonce, now });
    const data = fs.readFileSync(result.targets.permit, 'utf8');
    assert.ok(!data.includes('FIXTURE_SECRET') && !data.includes('DB_PASSWORD') && !result.launch.includes('eval('));
    assert.ok(!result.launch.includes('ReamurF805.run(') && result.runCommand.includes(h.CONFIRM), 'Carregar é separado de executar/gravar.');
    const fakeWindow = { open() { throw new Error('Não pode abrir ao carregar'); }, fetch() { throw new Error('Não pode fazer HTTP ao carregar'); } };
    require('node:vm').runInNewContext(fs.readFileSync(BROWSER_SOURCE, 'utf8'), { window: fakeWindow }, { timeout: 1000 });
    assert.equal(typeof fakeWindow.ReamurF805.run, 'function');
    assert.equal(fakeWindow.ReamurF805.lastReport, null);
    assert.deepEqual(fs.readFileSync(result.targets.tool), fs.readFileSync(BROWSER_SOURCE));
    assert.equal(fs.readdirSync(path.join(fui, 'admin/view/js')).filter(n => n.startsWith('f805-')).length, 2);
    h.validatePermit(JSON.parse(data), base, nonce, now);
  }));
  test('29 · colisão não sobrescreve arquivo já existente', () => laboratory(({ env }) => { const first = prepare(env, { nonce, now }); assert.throws(() => prepare(env, { nonce, now })); assert.ok(fs.existsSync(first.targets.tool)); }));
  test('30 · falha no segundo arquivo desfaz só a cópia recém-criada', () => laboratory(({ env, fui }) => {
    const targets = pathsFor(fui, nonce); fs.writeFileSync(targets.permit, 'existing'); assert.throws(() => prepare(env, { nonce, now }));
    assert.equal(fs.readFileSync(targets.permit, 'utf8'), 'existing'); assert.ok(!fs.existsSync(targets.tool));
  }));
  test('31 · limpar remove só o par exato; não toca imagem/post/config', () => laboratory(({ env, fui, put }) => {
    const result = prepare(env, { nonce, now: now - h.TTL * 2 });
    const image = put(fui, 'catalog/view/image/blog/other/keep.png', 'keep');
    clean({ ...env, F805_NONCE: nonce });
    assert.ok(!fs.existsSync(result.targets.tool) && !fs.existsSync(result.targets.permit));
    assert.equal(fs.readFileSync(image, 'utf8'), 'keep'); assert.ok(fs.existsSync(path.join(fui, 'admin/config.php')));
  }));
  test('32 · limpar recusa nonce, dono ou hash alterados', () => {
    laboratory(({ env }) => { assert.throws(() => clean({ ...env, F805_NONCE: '../x' })); });
    laboratory(({ env }) => { const x = prepare(env, { nonce, now }); fs.appendFileSync(x.targets.tool, 'changed'); assert.throws(() => clean({ ...env, F805_NONCE: nonce }), /modificado/); assert.ok(fs.existsSync(x.targets.tool)); });
    laboratory(({ env }) => { const x = prepare(env, { nonce, now }); const p = JSON.parse(fs.readFileSync(x.targets.permit, 'utf8')); p.kind = 'other'; fs.writeFileSync(x.targets.permit, JSON.stringify(p)); assert.throws(() => clean({ ...env, F805_NONCE: nonce })); });
  });
  console.log('\nF8-05 self-test: ' + passed + ' PASS / ' + failed + ' FAIL — 32 casos de guardas/modelos; sem navegador/PHP/HTTP/banco.');
  return failed ? 1 : 0;
}

function main() {
  ensure(process.argv.length === 3, 'Use o wrapper e2e_f805.sh --help.');
  const mode = process.argv[2];
  if (mode === '--self-test') { process.exitCode = selfTest(); return; }
  if (mode === '--inspect') { sourceContracts(realDirectory(process.env.MIRROR || path.join(ROOT, 'dev'), 'MIRROR')); console.log('PASS: contratos locais do RTE/mídia/Twig. Não é execução de navegador/servidor.'); return; }
  if (mode === '--prepare-browser') {
    const x = prepare(process.env);
    console.log('Preparação local concluída; nenhum HTTP/banco/navegador foi executado.');
    console.log('Validade: ' + new Date(x.permit.expiresAt).toISOString() + ' (15 minutos).');
    console.log('1. Faça login no admin do mesmo B com modify em blog/blog. Libere popup só nesse host.');
    console.log('2. Leia e execute o primeiro comando no Console: ele só carrega o Tool local.');
    console.log('\n' + x.launch + '\n');
    console.log('Aguarde "Tool local carregado". Depois execute o segundo comando abaixo: ele GRAVA um rascunho e uma capa no laboratório e limpa os itens próprios se passar.');
    console.log('\n' + x.runCommand + '\n');
    console.log('3. Ao terminar, ReamurF805.download() baixa o relatório JSON; envie-o para registrar a homologação.');
    console.log('4. Para remover somente os dois QA temporários, mantenha as variáveis e execute:');
    console.log('F805_NONCE=' + x.nonce + ' bash tools/e2e/e2e_f805.sh --clean-browser');
    console.log('NÃO versionar os arquivos f805-<nonce>.{js,json}; a limpeza não remove posts/imagens que ficaram de uma falha.');
    return;
  }
  if (mode === '--clean-browser') { clean(process.env); console.log('Removidos somente os dois QA temporários do nonce informado. Nenhum post, imagem, config ou banco foi alterado por esta limpeza.'); return; }
  throw new Error('Modo desconhecido. Use o wrapper e2e_f805.sh --help.');
}
if (require.main === module) {
  try { main(); } catch (error) { console.error('BLOQUEADO/ERRO: ' + h.redact(error.message)); process.exitCode = 2; }
}
module.exports = { stripPhpComments, phpLiteral, sourceContracts, preflight, prepare, clean, pathsFor, selfTest };
