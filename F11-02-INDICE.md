# F11-02 — Verificação de dependências (Composer + npm)

**Pacote de verificação — 2026-11-02 · `main` @ `31b15e06` · arquivos completos.**

⚠️ Este é um pacote **de auditoria**, distinto do ZIP de aplicação F11-01. Ele não altera o CI, não remove arquivos e não escreve no GitHub.

| Pergunta | Resposta |
| --- | --- |
| Dá para instalar as dependências Node em `dev/system/storage/` no mesmo padrão do Composer? | **Sim — já está configurado.** `npm ci --prefix dev/system/storage` → `dev/system/storage/node_modules/` |
| Por que `node_modules/` ainda aparece na raiz do projeto? | São **182 arquivos legados ainda versionados no índice**; o `.gitignore` não remove o que já está rastreado. Correção: `git rm -r --cached node_modules` |
| `vendor/` e `node_modules/` vão para o GitHub? | **Não.** 0 blobs em ambos no destino canônico; regras `**/system/storage/vendor/`, `**/system/storage/node_modules/`, `/node_modules/` confirmadas por `git check-ignore` |

📄 Relatório completo com evidências, provas locais e limitações: `docs/F11-02-Verificacao-Dependencias.md`
🔐 Integridade: `CHECKSUMS.txt` (CRC-32 + SHA-256 por arquivo) e `ReamurCMS-F11-02-Verificacao-Deps.zip.sha256` (fora do ZIP).

```bash
bash tools/verify-deps-layout.sh    # read-only; exit 1 se houver deps versionadas
unzip -t ReamurCMS-F11-02-Verificacao-Deps.zip
sha256sum -c ReamurCMS-F11-02-Verificacao-Deps.zip.sha256
```

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-02-Verificacao-Dependencias.md
├── tools/
│   └── verify-deps-layout.sh
├── CHECKSUMS.txt
└── F11-02-INDICE.md
```

**Total: 4 arquivos** (incluindo `CHECKSUMS.txt`).

## Comandos de referência

```bash
# Instalação (mesmo padrão do Composer)
composer install --working-dir=dev/system/storage   # → dev/system/storage/vendor/
npm ci --prefix dev/system/storage                  # → dev/system/storage/node_modules/

# Remover o legado da raiz do índice (executar manualmente, com autorização)
git rm -r --cached node_modules
git commit -m "chore(deps): remover node_modules da raiz (ADR 0081)"
git push
```

> Nenhum comando Git remoto, push, migration, banco, staging ou deploy foi executado por esta entrega. PHP, Composer, PHPStan e browser não estavam disponíveis no ambiente de verificação.
