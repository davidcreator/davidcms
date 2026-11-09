# Kit de patches — F11-17 / F11-18 / F11-19 (ADRs 0084 e 0085)

Correções de **instalação, versionamento de schema, CI com banco real e UI**, validadas em execução: PHP 8.4 + MariaDB 11.8 na colação **padrão do servidor** (`utf8mb4_uca1400_ai_ci`), que é justamente o cenário que quebrava o projeto.

Aplicáveis sobre `main` no HEAD `bfc9e26`. Os 10 patches foram testados com `git apply` em clone limpo e o `db-smoke` roda **41 PASS / 0 FAIL** no resultado final (contra **28 falhas** no código original).

---

## Como aplicar

```bash
git clone https://github.com/davidcreator/davidcms.git && cd davidcms
git checkout -b fix/f11-17-instalacao-confiavel

for p in <kit>/patches/*.patch; do git apply "$p"; done

git add -A    # a remoção do install.lock só sai do índice no commit
git commit -m "fix(install): instalacao confiavel, schema versionado e smoke com banco real (ADRs 0084/0085)"
```

Para reverter: `git reset --hard origin/main`.

Depois de aplicar, confirme com:

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASS=changeme DB_NAME=rms_smoke \
  bash tools/e2e/db-smoke.sh      # espera: 41 PASS / 0 FAIL
bash tools/verify-ci-workflow.sh  # espera: 37 PASS / 0 FAIL
```

---

## O que cada patch faz

| Patch | Arquivos | Efeito |
|---|---|---|
| `01-remover-install-lock-do-git` | `dev/system/storage/install.lock` (remoção), `.gitignore` | Clone novo deixa de receber `403 Installation locked` |
| `02-schema-collate-e-tabela-de-migracoes` | `schema.sql`, `schema-complete.sql` | `COLLATE=utf8mb4_unicode_ci` na temporária (1 linha) + tabela `rms_schema_migration` |
| `03-instalador-schema-completo-pdo-e-acl` | `install/model/install/install.php`, `install/controller/install/step_3.php` | ① importa `schema-complete.sql`; ② caminho **PDO** corrigido; ③ baseline de migrações gravado; ④ ACL do grupo 1 derivada do disco (27 rotas) |
| `04-front-controllers-config-por-dir` | `catalog/index.php`, `admin/index.php`, `install/index.php` | `config.php` carregado por `__DIR__` — para de depender do diretório de trabalho |
| `05-ui-admin-login-e-skins` | `admin/view/css/admin.css` | Botão **Login** estilizado; cards de skin em coluna (fim da sobreposição) |
| `06-ui-portfolio-badge` | `catalog/view/css/theme.css` | Badge "Featured" não é mais cortado pela borda do card |
| `07-git-lfs-midia` | `.gitattributes` (novo) | `webp/jpg/png/glb/woff2/…` via Git LFS |
| `08-runner-de-migracoes` | `system/library/migration.php`, `system/library/sql/splitter.php`, `install/sql/migrations.json`, `tools/migrate.php` (todos novos) | **F11-18**: estado do schema deixa de ser adivinhação |
| `09-db-smoke-no-ci` | `tools/e2e/db-smoke.sh`, `tools/e2e/lib/php-router.php` (novos), `.github/workflows/php.yml`, `tools/verify-ci-workflow.sh` | **F11-19**: job `db-smoke` com MariaDB 11.8 real + guarda do próprio job |
| `10-adrs-0084-0085` | `agents-ia/decisoes/0084-…md`, `0085-…md` (novos) | Registro das decisões, contexto, evidências e consequências |

---

## Comandos novos que você ganha

```bash
php tools/migrate.php status            # em que estado o schema está
php tools/migrate.php run               # aplica migrações pendentes
php tools/migrate.php run --dry-run     # mostra o que faria
php tools/migrate.php baseline          # adota um banco criado antes do F11-18
php tools/migrate.php status --config=/caminho/config.php

bash tools/e2e/db-smoke.sh              # instala em banco real e navega no site+painel
php -S 127.0.0.1:8080 -t dev tools/e2e/lib/php-router.php   # dev local sem Apache
```

### Criar uma migração nova (fluxo a partir de agora)

1. criar `dev/install/sql/upgrade_fXX_YY_nome.sql`;
2. acrescentar no **fim** de `migrations.json` com `"in_schema_complete": false`;
3. `php tools/migrate.php run` — grava versão + checksum + data;
4. quando o SQL for dobrado no `schema-complete.sql`, virar a flag para `true`.

---

## Evidências (medidas, não estimadas)

| Verificação | Antes | Depois |
|---|---|---|
| Wizard em clone novo | `403 Installation locked` | `200` passo 1 |
| Import (mysqli), MariaDB 11.8 `uca1400` | aborta no statement 33 | 44 tabelas, 0 erro |
| Import (PDO), mesmo banco | erro 2014 → 4 tabelas | 44 tabelas, 0 erro |
| `rms_project` / `rms_setting` / `rms_page` | 0 / 0 / 0 | 10 / 37 / 12 |
| `cms/page.edit\|1` | `404` (`Table 'rms_custom_field' doesn't exist`) | `200`, 92 KB, editor no DOM |
| Admin em `setting/*`, `design/theme`, `blog/*`, `localization/*` | `302 → error/permission` | `200` |
| Rotas públicas (`/portfolio`, `/blog`, `/sitemap.xml`…) | `302` (config não encontrado) | `200` |
| `tools/migrate.php status` | não existia | 29 `contained`, 0 pendente |
| Migração nova aplicada | manual, sem registro | `applied` + checksum + idempotente |
| Banco legado sem baseline | — | aviso + `baseline` explícito |
| Botão Login (CSS computado) | sem fundo/padding | `#0d6efd`, `11px 18px` |
| Cards de skin | `inline-flex` em linha, texto vazando | `flex` em coluna |
| Badge "Featured" | cortado pela borda | dentro do card |
| **`db-smoke.sh`** | **28 FAIL** | **41 PASS / 0 FAIL** |
| Guarda `verify-ci-workflow.sh` | 31 PASS | **37 PASS / 0 FAIL** |

---

## O que **não** está resolvido por este kit

| Item | Por quê | Sugestão |
|---|---|---|
| Mídia real do portfólio/clientes | os arquivos não estão no repositório (0 imagens de conteúdo) | carregar via LFS, que já está configurado |
| Colisão de chaves de `language` (25 arquivos definem `heading_title`) | é refatoração de arquitetura, não bug pontual | ADR própria: namespacear por rota |
| `schema.sql` ainda existe (legado) | remover exige migrar referências em docs/tools | apagar depois de um ciclo estável |
| Fidelidade visual no CI | smoke é HTTP, não navegador | Playwright segue manual por custo de execução |
| `catalogAssetBase()` duplicado em 9 controllers | refatoração de núcleo | unificar em `library/theme` |
