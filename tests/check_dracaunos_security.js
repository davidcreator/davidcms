'use strict';
/*
 * KZ-40 (Fase C do Dracaunos-Security) — conferidor estatico da ferramenta
 * INTERNA Dracaunos-Security (KZ-34/36/38/39).
 *
 * Confere, sem banco e sem PHP, a fiacao da Fase B e da conta do cliente:
 *   1) startup/dracaunos no pre-action do admin, antes de startup/login,
 *      e startup/extension continua o ultimo (restricao do ShippedAndWiringTest);
 *   2) o pre-acao aplica IP em todas as rotas (escopo 'admin'), 403, auditoria
 *      e expiracao de senha;
 *   3) login do admin com UMA unica leitura de $this->request->ip()
 *      (restricao do ProxyWiringTest — regrediu no run 44299525501);
 *   4) SQL da Fase B idempotente e portatil (sem ADD COLUMN IF NOT EXISTS,
 *      que o MySQL 8 nao aceita — erro #1064 reportado);
 *   5) ferramenta interna: Dracaunos/JOW removidos de rms_tool (nunca publicos)
 *      e presentes so no internal-tools.json;
 *   6) conta do cliente no catalogo (KZ-38): controllers com CSRF, views e
 *      paridade de idiomas pt-br/en-us/es-eu;
 *   7) carimbo password_changed_at gravado ao trocar senha (perfil e admin);
 *   8) templates novos sem <script> inline nem atributo on* (regra do CSP).
 *
 *   node tests/check_dracaunos_security.js
 */
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function read(rel) { return fs.readFileSync(path.join(ROOT, rel), 'utf8'); }
function count(hay, needle) { return hay.split(needle).length - 1; }

// 1) pre-action do admin
const adminCfg = read('dev/system/config/admin.php');
const m = adminCfg.match(/\$_\['action_pre_action'\]\s*=\s*\[([^\]]*)\]/);
check(!!m, 'action_pre_action do admin encontrado');
const pre = [...m[1].matchAll(/'([a-z/_]+)'/g)].map(x => x[1]);
check(pre[pre.length - 1] === 'startup/extension', 'startup/extension continua o ultimo pre-action');
check(pre.indexOf('startup/dracaunos') !== -1, 'startup/dracaunos presente no pre-action');
check(pre.indexOf('startup/dracaunos') < pre.indexOf('startup/login'), 'startup/dracaunos antes de startup/login');
check(pre.indexOf('startup/login') < pre.indexOf('startup/extension'), 'startup/login antes de startup/extension');

// 2) conteudo do pre-acao
const startup = read('dev/admin/controller/startup/dracaunos.php');
check(startup.includes('$this->request->ip()'), 'startup/dracaunos le o IP via request->ip()');
check(!/REMOTE_ADDR|X_FORWARDED_FOR|CLIENT_IP|X_REAL_IP/.test(startup), 'startup/dracaunos nao usa superglobais de IP cruas');
check(startup.includes("ipAllowed($ip, 'admin')"), 'regras de IP aplicadas no escopo admin (todas as rotas)');
check(startup.includes('403 Forbidden'), 'bloqueio responde 403');
check(startup.includes('addAudit('), 'auditoria registrada no pre-action');
check(startup.includes('passwordExpired('), 'expiracao de senha conferida no pre-action');
check(startup.includes("'user/profile'"), 'rota de troca de senha liberada na expiracao');

// 3) login do admin: exatamente 1 leitura de IP (ProxyWiringTest)
const login = read('dev/admin/controller/common/login.php');
check(count(login, '$this->request->ip()') === 1, 'login do admin com exatamente 1 chamada a request->ip()');
check(login.includes('passwordExpired('), 'login redireciona para troca quando a senha venceu');

// 4) SQL da Fase B
const sql = read('tools/upgrade_kz39_dracaunos_fase_b.sql');
const sqlCode = sql.replace(/--[^\n]*/g, ''); // so o codigo, sem comentarios
check(sql.includes('rms_dracaunos_audit'), 'SQL cria a tabela de auditoria');
check(sql.includes('information_schema.COLUMNS'), 'SQL usa information_schema (portatil)');
check(!sqlCode.includes('ADD COLUMN IF NOT EXISTS'), 'SQL sem ADD COLUMN IF NOT EXISTS no codigo (MySQL 8 -> erro #1064)');
check(sql.includes('password_changed_at'), 'SQL adiciona o carimbo de troca de senha');
check(sql.includes('dracaunos_audit_days'), 'SQL registra a retencao da auditoria');

// 5) ferramenta interna nunca publica
const kz34 = read('tools/upgrade_kz34_dracaunos_security.sql');
check(/DELETE FROM `rms_tool` WHERE `vendor` IN \('JOW','Dracaunos'\)/.test(kz34), 'JOW/Dracaunos removidos de rms_tool (nao ficam publicos)');
const tools = JSON.parse(read('dev/admin/model/tool/internal-tools.json'));
const drac = tools.tools.find(t => t.id === 'dracaunos-security');
check(!!drac, 'dracaunos-security presente no internal-tools.json');
check(/^1\.\d+\./.test(drac.version) && !/^1\.[01]\./.test(drac.version), 'dracaunos-security na versao 1.x com Fase B aplicada (atual: ' + drac.version + ')');

// 6) conta do cliente no catalogo (KZ-38)
const accountControllers = ['login', 'logout', 'register', 'verify', 'forgotten', 'reset', 'account'];
const postControllers = ['login', 'register', 'forgotten', 'reset', 'account']; // logout sai direto; verify e GET do link
for (const c of accountControllers) {
  const src = read('dev/catalog/controller/account/' + c + '.php');
  if (c !== 'logout' && c !== 'verify') {
    check(fs.existsSync(path.join(ROOT, 'dev/catalog/view/template/account/' + c + '.twig')), 'view account/' + c + '.twig existe');
  }
  if (postControllers.includes(c)) {
    check(src.includes('csrfValid()') || src.includes('Csrf::token'), 'controller account/' + c + ' (POST) com protecao CSRF');
  }
}
const langs = ['pt-br', 'en-us', 'es-eu'];
for (const f of ['account', 'login', 'register', 'forgotten']) {
  const keys = langs.map(l => {
    const src = read('dev/catalog/language/' + l + '/account/' + f + '.php');
    return [...src.matchAll(/\$_\['([a-z_0-9]+)'\]/g)].map(x => x[1]).sort().join(',');
  });
  check(keys[0] === keys[1] && keys[1] === keys[2], 'paridade de chaves de idioma em account/' + f);
}

// 7) carimbo de troca de senha
const userModel = read('dev/admin/model/user/user.php');
check(count(userModel, 'password_changed_at') >= 3, 'password_changed_at gravado em addUser/editUser/editProfile');
const profile = read('dev/admin/controller/user/profile.php');
check(profile.includes('dracaunos_password_expired'), 'perfil trata o flag de senha expirada');

// 8) templates novos sem script inline nem on* (regra CSP); logout nao tem view
for (const c of accountControllers) {
  if (c === 'logout') continue;
  const twig = read('dev/catalog/view/template/account/' + c + '.twig');
  check(!/<script\b/i.test(twig), 'account/' + c + '.twig sem <script> inline');
  check(!/\son[a-z]+\s*=/i.test(twig), 'account/' + c + '.twig sem atributo on*');
}
const dracTwig = read('dev/admin/view/template/security/dracaunos.twig');
check(dracTwig.includes('text_audit'), 'painel Dracaunos com a secao de auditoria');
check(!/<script\b/i.test(dracTwig), 'dracaunos.twig sem <script> inline');

// 9) 2FA obrigatório no admin (KZ-41): dracaunos_2fa_required=1 bloqueia o painel
const dracModel = read('dev/admin/model/security/dracaunos.php');
check(dracModel.includes('function is2faRequired('), 'model lê dracaunos_2fa_required');
check(dracModel.includes('function save2faRequired('), 'model grava dracaunos_2fa_required');
check(dracModel.includes('function userHas2fa('), 'model sabe se o usuário tem 2FA ativo');
check(startup.includes('is2faRequired()') && startup.includes('userHas2fa('), 'startup/dracaunos enforceia o 2FA obrigatório');
check(startup.includes("'security/dracaunos'"), 'rota do painel Dracaunos liberada para ativar o 2FA');
check(login.includes('is2faRequired()'), 'login redireciona para ativar 2FA quando obrigatório');
const dracCtrl = read('dev/admin/controller/security/dracaunos.php');
check(dracCtrl.includes("case '2fa_required'"), 'painel Dracaunos salva a obrigatoriedade de 2FA');
check(dracCtrl.includes('force_2fa'), 'painel Dracaunos mostra o aviso de 2FA obrigatório');
check(dracTwig.includes('text_force_2fa'), 'twig do painel tem o banner de 2FA obrigatório');
for (const l of langs) {
  check(read('dev/admin/language/' + l + '/security/dracaunos.php').includes("text_force_2fa"), 'idioma ' + l + ' tem text_force_2fa');
}

// 10) KZ-42: exportação CSV e limpeza por retenção no painel
check(dracModel.includes('function exportAuditLogs(') && dracModel.includes('function exportLoginLogs('), 'model exporta auditoria e log de logins');
check(dracModel.includes('function pruneAudit(') && dracModel.includes('function pruneLoginLogs('), 'model tem limpeza por retenção');
check(dracCtrl.includes('function exportAudit(') && dracCtrl.includes('function exportLogins('), 'controller tem rotas de exportação CSV');
check(dracCtrl.includes("case 'prune'"), 'controller tem ação de limpeza (POST+CSRF)');
check(dracCtrl.includes('Content-Disposition: attachment'), 'exportação responde como download CSV');
check(dracTwig.includes('export_audit') && dracTwig.includes('export_logins'), 'twig tem botões/links de exportação');
check(dracTwig.includes('button_prune'), 'twig tem botão de limpeza por retenção');
for (const l of langs) {
  const ls = read('dev/admin/language/' + l + '/security/dracaunos.php');
  check(ls.includes('button_export_csv') && ls.includes('text_pruned'), 'idioma ' + l + ' tem chaves do KZ-42');
}

// 11) Hotfix KZ-43: load->model() devolve void — o model vive no registry
//     (model_<rota>). "= $this->load->model(...)" deixava null e fatal no
//     runtime ("Call to a member function ipAllowed() on null" no login).
{
  const skip = ['node_modules', 'vendor', 'storage', '.git'];
  const offenders = [];
  (function walk(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, e.name);
      if (e.isDirectory()) { if (!skip.includes(e.name)) walk(full); continue; }
      if (!/\.php$/.test(e.name)) continue;
      if (/=\s*\$this->load->model\(/.test(fs.readFileSync(full, 'utf8'))) {
        offenders.push(path.relative(ROOT, full).split(path.sep).join('/'));
      }
    }
  })(path.join(ROOT, 'dev'));
  check(offenders.length === 0, 'nenhum "= $this->load->model(...)" em dev/ (retorno é void): ' + offenders.join(', '));
}

// 12) KZ-44: recuperação de senha do admin (rotas públicas token-gated)
const loginWall = read('dev/admin/controller/startup/login.php');
check(loginWall.includes("'common/forgotten'") && loginWall.includes("'common/reset'"), 'rotas públicas de recuperação liberadas na muralha de login');
const adminForgotten = read('dev/admin/controller/common/forgotten.php');
check(adminForgotten.includes("'admin_password'"), 'token de recuperação do admin usa type admin_password');
check(adminForgotten.includes('RateLimit'), 'forgotten do admin tem rate limit por IP');
check(!/=\s*\$this->load->model\(/.test(adminForgotten), 'forgotten do admin carrega model via registry');
const adminReset = read('dev/admin/controller/common/reset.php');
check(adminReset.includes("'admin_password'") && adminReset.includes('passwordMeetsPolicy'), 'reset do admin valida token e aplica a política de senhas');
check(adminReset.includes('setUserPassword'), 'reset do admin grava a nova senha');
check(dracModel.includes('function getRecoveryByToken(') && dracModel.includes('function setUserPassword('), 'model tem getRecoveryByToken e setUserPassword');
const loginTwig = read('dev/admin/view/template/common/login.twig');
check(loginTwig.includes('{{ forgotten }}'), 'tela de login do admin tem link de recuperação');
for (const l of langs) {
  const lf = read('dev/admin/language/' + l + '/common/forgotten.php');
  check(lf.includes('heading_title') && lf.includes('error_token'), 'idioma ' + l + ' tem common/forgotten completo');
  check(read('dev/admin/language/' + l + '/common/login.php').includes('text_forgotten'), 'idioma ' + l + ' tem text_forgotten no login');
}

// 13) KZ-45: gestão de sessões ativas do admin
check(dracModel.includes('function getAdminSessions(') && dracModel.includes('function destroySession('), 'model lista e encerra sessões do admin');
check(dracModel.includes('user_id\\|i:'), 'model só lista sessões com login (user_id na sessão)');
check(dracCtrl.includes("case 'session_kill'"), 'controller tem ação de encerrar sessão');
check(dracCtrl.includes('$this->session->getId()'), 'controller protege a sessão atual (não mata a própria)');
check(dracTwig.includes('text_sessions') && dracTwig.includes('session_kill'), 'twig tem a seção de sessões com botão de encerrar');
for (const l of langs) {
  check(read('dev/admin/language/' + l + '/security/dracaunos.php').includes('text_sessions'), 'idioma ' + l + ' tem a seção de sessões');
}

// 14) KZ-46: testador de regras de IP + IPv6
check(dracModel.includes('function explainIp('), 'model tem explainIp (testador de IP)');
check(dracModel.includes('inet_pton'), 'matcher de IP usa inet_pton (suporta IPv6/CIDRv6)');
check(dracCtrl.includes("case 'ip_test'"), 'controller tem ação ip_test');
check(dracCtrl.includes('ip_test_result'), 'controller passa o resultado do teste para a view (flash)');
check(dracTwig.includes('button_test_ip') && dracTwig.includes('text_ip_test_deny'), 'twig tem o formulário e os resultados do testador');
for (const l of langs) {
  check(read('dev/admin/language/' + l + '/security/dracaunos.php').includes('button_test_ip'), 'idioma ' + l + ' tem o testador de IP');
}

// 15) KZ-49: página de configurações (retenção) dentro do próprio painel
check(dracModel.includes('function getRetention(') && dracModel.includes('function saveRetention('), 'model lê e salva a retenção configurável');
check(dracCtrl.includes("case 'retention'"), 'controller tem ação retention (POST+CSRF+modify)');
check(dracCtrl.includes('$data[\'retention\']'), 'controller passa a retenção atual para a view');
check(dracTwig.includes('text_settings') && dracTwig.includes('audit_days') && dracTwig.includes('login_log_days'), 'twig tem o card de configurações com os dois campos');
for (const l of langs) {
  check(read('dev/admin/language/' + l + '/security/dracaunos.php').includes('text_settings'), 'idioma ' + l + ' tem a página de configurações');
}

// 16) KZ-50 hotfix: User::getId() não existe (o método é getUserId) — varre dev/ inteiro
const userIdMisuse = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.php') && fs.readFileSync(p, 'utf8').includes('->user->getId()')) userIdMisuse.push(p);
  }
})('dev');
check(userIdMisuse.length === 0, 'nenhum ->user->getId() em dev/ (User só tem getUserId) — ' + (userIdMisuse.join(',') || 'limpo'));

// 17) KZ-51 hotfix: Twig não aceita variável começando com dígito (2fa_required)
const twigDigitVar = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.twig')) {
      const c = fs.readFileSync(p, 'utf8');
      if (/\{\{\s*\d/.test(c) || /\{%\s*(if|elseif)\s+\d/.test(c)) twigDigitVar.push(p);
    }
  }
})('dev');
check(twigDigitVar.length === 0, 'nenhum .twig usa variável começando com dígito ({{ 2fa... }} / {% if 2... %}) — ' + (twigDigitVar.join(',') || 'limpo'));
check(dracTwig.includes('{% if twofa_required %}'), 'toggle 2FA do painel usa twofa_required (variável válida)');

// 18) KZ-52 hotfix: header/footer DEVEM ser {{ header|raw }} — escapado quebra o CSS da página
const escapedShell = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.twig')) {
      const c = fs.readFileSync(p, 'utf8');
      if (/\{\{\s*(header|footer)\s*\}\}/.test(c)) escapedShell.push(p);
    }
  }
})('dev/admin/view/template'); // admin tem autoescape ligado; catálogo não
check(escapedShell.length === 0, 'nenhum .twig do ADMIN usa {{ header }}/{{ footer }} sem |raw (CSS quebra) — ' + (escapedShell.join(',') || 'limpo'));

// 19) KZ-53 hotfix: assets do admin devem usar base admin/ (adminAssetUrl), não site_url cru
const badAssetRefs = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.twig') && /\{\{\s*site_url\s*\}\}view\//.test(fs.readFileSync(p, 'utf8'))) badAssetRefs.push(p + ' (twig)');
    else if (e.name.endsWith('.php') && /site_url'[^)]*\)\s*\.\s*'view\//.test(fs.readFileSync(p, 'utf8'))) badAssetRefs.push(p + ' (php)');
  }
})('dev/admin');
check(badAssetRefs.length === 0, 'admin não usa site_url+view/ para assets (CSS/JS 404) — ' + (badAssetRefs.join(',') || 'limpo'));
check(read('dev/system/engine/controller.php').includes('function adminAssetUrl('), 'base Controller tem adminAssetUrl()');

// 20) KZ-54 hotfix: página que renderiza header/footer precisa do admin.css (layout do shell)
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.name.endsWith('.php')) continue;
    const c = fs.readFileSync(p, 'utf8');
    if (c.includes("load->controller('common/header')") && !c.includes('admin.css')) {
      check(false, 'controller do admin sem admin.css quebra o layout: ' + p);
    }
  }
})('dev/admin/controller');
checks++;

// 21) KZ-55: UI reformulada — sem script inline/on* (CSP) e ações de formulário preservadas
check(!/<script|\son[a-z]+\s*=/i.test(dracTwig), 'dracaunos.twig sem <script> inline nem on* (CSP)');
for (const a of ['password_policy','admin_route','retention','twofa_enable','twofa_disable','2fa_required','session_kill','recovery_handle','ip_test','ip_add','ip_delete','prune']) {
  check(dracTwig.includes('value="' + a + '"'), 'ação preservada no novo layout: ' + a);
}

// 22) KZ-56: dashboard visual — CSP (sem script inline/on*), stats e abas preservando ações
check(!/<script|\son[a-z]+\s*=/i.test(dracTwig), 'dashboard sem <script> inline nem on* (CSP) — abas usam bootstrap.bundle do footer');
check(dracTwig.includes('stats.logins_total') && dracTwig.includes('data-bs-toggle="tab"'), 'dashboard tem cartões de stats e abas');
for (const a of ['password_policy','admin_route','retention','twofa_enable','twofa_disable','2fa_required','session_kill','recovery_handle','ip_test','ip_add','ip_delete','prune']) {
  check(dracTwig.includes('value="' + a + '"'), 'ação preservada no dashboard: ' + a);
}
check(dracCtrl.includes("$data['stats']"), 'controller calcula stats para os cartões');
for (const l of langs) {
  check(read('dev/admin/language/' + l + '/security/dracaunos.php').includes('tab_overview'), 'idioma ' + l + ' tem as abas do dashboard');
}

console.log('PASS  check_dracaunos_security: ' + checks + ' verificacoes (KZ-34/36/38/39/41/42/44/45/46/49/50/51/52/53/54/55/56 + hotfix KZ-43)');
