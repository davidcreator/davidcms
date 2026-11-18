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
check(/^1\.[2-9]\./.test(drac.version), 'dracaunos-security na versao 1.x com Fase B aplicada (atual: ' + drac.version + ')');

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

console.log('PASS  check_dracaunos_security: ' + checks + ' verificacoes (KZ-34/36/38/39/41)');
