'use strict';
/*
 * F11-01 / ADR 0081 — resolucao canonica das dependencias Node.
 *
 * As dependencias de desenvolvimento (Playwright) vivem em
 *   dev/system/storage/node_modules/
 * ao lado do vendor do Composer, e sao ignoradas pelo Git. Os runners em
 * tools/e2e/ NAO ficam dentro dessa arvore, entao um `require('playwright')`
 * simples nao encontra o pacote. createRequire() ancorado no package.json do
 * storage resolve de forma explicita: sem NODE_PATH, sem symlink, sem copiar
 * node_modules para a raiz do repositorio e sem alterar o escopo do sistema.
 *
 * Uso nos runners:
 *   const { loadPlaywright } = require('./lib/load-playwright.cjs');
 *   const { chromium } = loadPlaywright();
 */
const path = require('node:path');
const { createRequire } = require('node:module');

// tools/e2e/lib/ -> tools/e2e/ -> tools/ -> <raiz do repositorio>
const STORAGE_DIR = path.resolve(__dirname, '..', '..', '..', 'dev', 'system', 'storage');
const storageRequire = createRequire(path.join(STORAGE_DIR, 'package.json'));

function loadPlaywright() {
  try {
    return storageRequire('playwright');
  } catch (error) {
    throw new Error(
      'Playwright ausente em dev/system/storage/node_modules/. ' +
      'Execute: npm ci --prefix dev/system/storage'
    );
  }
}

module.exports = { loadPlaywright, storageRequire, STORAGE_DIR };
