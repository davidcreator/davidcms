# ADR 0039 — Build de produção runtime-only (`prod/`) e instalação sem wizard

| Campo | Valor |
|---|---|
| Status | Aceito |
| Data | 2026-10-21 |
| Entrega | F6-04 (Fase 6 · hardening e cutover) |
| Prova | `tools/e2e/e2e_f604.sh` **10/10** + suíte completa **26/301/0** |

## Contexto

O modelo de deploy já decidido (verbalizado pelo dono do projeto): `dev/`
é o ambiente de desenvolvimento completo — agentes (`agents-ia/`),
documentação (`docs/`), ferramentas (`tools/`), testes (`dev/tests/`) e o
próprio wizard (`dev/install/`) vivem lá. **Produção é outra coisa**: uma
build enxuta em `prod/` contendo **somente o runtime do sistema**, gerada
a partir de `dev/`. Apenas o conteúdo de `prod/` sobe para
davidalmeida.xyz — nada de planejamento, agentes, documentos ou testes
fica online.

O F6-03 (mapa 301 + runbook) ainda pressupunha publicar a pasta `dev/`
inteira. Esta ADR formaliza o modelo definitivo e corrige o runbook.

## Decisão

1. **`tools/build_prod.sh`** gera/atualiza `prod/` a partir de `dev/` com
   `rsync -a --delete --delete-excluded`:
   - **Excluídos da build** (nunca sobem): `install/` (o wizard **não** vai
     ao ar), `tests/`, os `config.php` reais de `catalog`/`admin`,
     `install.lock`, dev-flags de `system/storage/`
     (`phpstan.neon`, `phpstan-baseline.neon`, `phpstan-bootstrap.php`,
     `composer.json`, `composer.lock`) e o **conteúdo volátil** de
     `cache/`, `logs/`, `session/`, `temp/` (regravando o guard
     `index.html` de cada pasta).
   - `--delete-excluded` é obrigatório: `--delete` sozinho protege arquivos
     do destino que passaram a cair em `--exclude` (não os remove) — causou
     `prod/tests/` remanescente na primeira rodada.
   - Terminologia "presenças obrigatórias × ausências proibidas":
     veredito automático ao fim da build (falha se `install/`, `tests/`,
     config real, phpstan, composer ou `logs/error.log` existirem; se
     faltar `index.php`, `.htaccess`, framework ou `vendor/autoload`).

2. **Configs de produção por EXEMPLO, não por commit.** Como `config.php`
   real nunca vai ao git nem recebe valores de produção em dev, a build
   gera **`catalog/config.php.EXEMPLO`** e **`admin/config.php.EXEMPLO`**
   partindo do template único do wizard (`install/config.php.tpl`): sed
   `dev/{{APP_DIR}}` → `catalog/`·`admin/`, `dev/system/` → `system/`,
   `dev/extension/` → `extension/`. Caminhos são **relativos à pasta
   servida** (zero menção a `prod/` — o usuário pode subir o conteúdo em
   qualquer pasta/domínio sem reeditar). No servidor: copiar/remover o
   `.EXEMPLO`, preencher os `{{...}}` reais.

3. **⚠ `HTTP_SERVER` termina com `/catalog/` (ou `/admin/`) — sempre.**
   O router calcula a rota como `REQUEST_URI − path(site_url)`. Em dev o
   site_url é `…/davidcms/dev/catalog/`; a build precisa manter o padrão
   (`https://dominio/catalog/`), não a raiz — senão o segmento `catalog`
   permanece no path e toda rota limpa vira 404 de app. Capturado pela
   f604 check 3 (home 404 com `?route=` 200) — achado real de pré-produção.

4. **Instalação de produção é por SQL, sem wizard.** Com `install/` fora
   do ar, o banco nasce importando, **nesta ordem** (espelhada na f604 e
   no runbook §4):
   1. `install/sql/schema.sql` (estrutura + seeds de tabelas);
   2. **Seed "wizard-less"** equivalente ao `createAdministrator()` do
      step_3: `rms_user_group` (`Administrator`, permission JSON com as 11
      rotas seed) + `rms_user` (`password_hash()` bcrypt) — **antes** dos
      upgrades;
   3. todos os `upgrade_*.sql` em ordem lexicográfica (idempotentes).
   Razão da ordem: os upgrades de grant (`upgrade_f2_8..f5_02`) fazem
   `UPDATE rms_user_group … WHERE name='Administrator'` via
   `JSON_SET` — sem o grupo já semeado, **caem no vazio** e o painel abre
   com "Routes with access: 0" sem a seção de módulos (sintoma capturado
   na f604 check 4 com a dash em en-us, 2196 bytes).

5. **`prod/` e os zips de build não se versionam** (`.gitignore`):
   artefato derivado — a fonte da verdade é `dev/` + o script.

6. **Suite:** `e2e_f604.sh` faz deploy simulado da **build**
   (`/var/www/html/prodtest` servido em `/prodtest/…`), banco fresco via
   SQL na ordem acima, login admin com usuário criado por SQL, e prova:
   runtime presente/ausências dev-only; EXEMPLOs portáteis; home/jornadas/
   SEO 200; **mapa 301 portátil** (`portfolio/jwa`→301→`catalog/jwa`,
   `tools/open?id=1`→`fluxa` — CMSBASE resolvendo em prefixo novo);
   POST notify MP sem 30x; `error_display` desligado na cópia
   da build (mecanismo f601); zip de upload íntegro (`unzip -t`) sem
   arquivos dev-only, com `vendor/autoload.php`, sem zip-aninhado
   (assert por `awk` — o cabeçalho `Archive: ….zip` do `unzip -l`
   casa `\`.zip$` cegamente).

## Consequências

- Superfície exposta mínima: sem wizard, sem testes, sem phpstan/composer
  flags, sem configs com credencial real, sem logs antigos.
- O cutover passa a ser: `build_prod.sh` → (zip opcional para upload) →
  extrair no destino → SQL na ordem §4 → renomear/preencher os 2
  EXEMPLOs → gravar storage → validar (runbook §5).
- `.htaccess` do ADR 0038 segue intacto e portável (CMSBASE) — f604 prova
  que ele funciona em qualquer prefixo sem edição.
- `dev/` permanece o ambiente de trabalho; o repo não carrega prod/.
- Rebuilds são reprodutíveis e baratos (rsync incremental) — publicar uma
  correção = rodar `tools/build_prod.sh` e subir o diff.

## Provas

- `bash tools/e2e/e2e_f604.sh` → **10/10** (inclui build real, deploy
  simulado servido pelo Apache, banco sem wizard, admin com 15 grants,
  301s, notify, erro seguro e zip íntegro).
- Suíte completa (incluindo f604): **26 baterias / 301 asserts / 0 fails**.
