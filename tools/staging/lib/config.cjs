'use strict';
/*
 * tools/staging — configuração compartilhada do ambiente de testes descartável.
 *
 * Regra de ouro: staging/ é um CLONE de dev/ + um banco CLONE de davidcms.
 * Nada aqui aponta para produção. Toda constante pode ser sobrescrita por
 * variável de ambiente para uso em outra máquina.
 */
const path = require('node:path');
const fs = require('node:fs');

const ROOT = path.resolve(__dirname, '..', '..', '..');
const DEV_DIR = path.join(ROOT, 'dev');
const STAGING_DIR = path.join(ROOT, 'staging');
const REPORTS_DIR = path.join(STAGING_DIR, 'reports');
const BASELINES_DIR = path.join(__dirname, '..', 'baselines');
const TOOLS_E2E_DIR = path.join(ROOT, 'tools', 'e2e');
const TESTS_DIR = path.join(ROOT, 'tests');

const WAMP = process.env.WAMP_ROOT || 'e:/wamp64';

const BIN = {
  php: process.env.STAGING_PHP || `${WAMP}/bin/php/php8.4.15/php.exe`,
  mysql: process.env.STAGING_MYSQL || `${WAMP}/bin/mysql/mysql8.4.7/bin/mysql.exe`,
  mysqldump: process.env.STAGING_MYSQLDUMP || `${WAMP}/bin/mysql/mysql8.4.7/bin/mysqldump.exe`,
};

const DB = {
  host: process.env.STAGING_DB_HOST || 'localhost',
  port: Number(process.env.STAGING_DB_PORT || 3306),
  user: process.env.STAGING_DB_USER || 'root',
  password: process.env.STAGING_DB_PASSWORD || '',
  // Espelho (nunca escrito): é a fonte do clone.
  source: process.env.STAGING_DB_SOURCE || 'davidcms',
  // Descartável: pode ser dropado/recriado à vontade.
  target: process.env.STAGING_DB_TARGET || 'davidcms_staging',
};

const BASE_URL = (process.env.STAGING_BASE_URL || 'http://localhost/davidcms/staging').replace(/\/+$/, '');

const URLS = {
  base: BASE_URL,
  catalog: `${BASE_URL}/catalog/`,
  admin: `${BASE_URL}/admin/`,
  // URL que comprovadamente NÃO existe (para o smoke de 404).
  missing: `${BASE_URL}/catalog/__staging-nao-existe__`,
  // Servidor de desenvolvimento (espelho): usado só para provar independência.
  devCatalog: 'http://localhost/davidcms/dev/catalog/',
};

/*
 * Schema mínimo esperado no clone. Presente em `source` e ausente em `target`
 * = clone incompleto (FAIL). Ausente nos dois = migração pendente (WARN).
 */
const EXPECTED_SCHEMA = {
  tables: {
    rms_user: [],
    rms_user_group: [],
    rms_setting: [],
    rms_seo_url: [],
    rms_page: ['system_key'],
    rms_design_theme: ['draft_config', 'published_config'],
    rms_blog_post_content: [],
    rms_cms_block_pattern: [],
  },
};

/* Colunas JSON de ACL que exigem verificação de integridade (ADR 0005). */
const JSON_ACL_COLUMNS = { rms_user_group: ['access', 'modify'] };

/*
 * Bateria E2E (navegador real). Exige sessão autenticada gravada localmente —
 * sem PLAYWRIGHT_STORAGE_STATE a bateria é NOT_RUN, nunca "aprovada".
 */
const E2E = {
  storageState: process.env.PLAYWRIGHT_STORAGE_STATE || '',
  headless: process.env.HEADLESS !== '0',
  confirmDisposable: process.env.E2E_CONFIRM_DISPOSABLE === '1',
  timeouts: Number(process.env.STAGING_E2E_TIMEOUT_MS || 180000),
  urls: {
    CMS_EDIT_URL: process.env.CMS_EDIT_URL || `${BASE_URL}/admin/index.php?route=blog/blog.form`,
    PUBLIC_PAGE_URL: process.env.PUBLIC_PAGE_URL || `${BASE_URL}/catalog/`,
    ADMIN_SETTINGS_URL: process.env.ADMIN_SETTINGS_URL || `${BASE_URL}/admin/index.php?route=setting/setting`,
    PUBLIC_HOME_URL: process.env.PUBLIC_HOME_URL || `${BASE_URL}/catalog/`,
    PUBLIC_404_URL: process.env.PUBLIC_404_URL || `${BASE_URL}/catalog/__staging-404__`,
  },
  runners: [
    'threejs_native_readonly_playwright.cjs',
    'threejs_native_admin_playwright.cjs',
    'threejs_cms_playwright.cjs',
    'threejs_admin_preview_sandbox_isolated.cjs',
  ],
};

function exists(p) {
  try { return fs.existsSync(p); } catch (_) { return false; }
}

const PATHS = {
  stagingCatalogConfig: path.join(STAGING_DIR, 'catalog', 'config.php'),
  stagingAdminConfig: path.join(STAGING_DIR, 'admin', 'config.php'),
  phpstanConfig: path.join(STAGING_DIR, 'system', 'storage', 'phpstan.neon'),
  phpstanPhar: path.join(STAGING_DIR, 'system', 'storage', 'vendor', 'bin', 'phpstan.phar'),
  staticBaseline: path.join(BASELINES_DIR, 'static-baseline.json'),
};

module.exports = {
  ROOT, DEV_DIR, STAGING_DIR, REPORTS_DIR, BASELINES_DIR,
  TOOLS_E2E_DIR, TESTS_DIR,
  BIN, DB, URLS, EXPECTED_SCHEMA, JSON_ACL_COLUMNS, E2E, PATHS, exists,
};
