# Análise completa — ReamurCMS (`davidcreator/davidcms`)

**Projeto:** ReamurCMS — CMS PHP 8 / MVCL que vai substituir o CMS atual de [davidalmeida.xyz](https://davidalmeida.xyz)
**Repositório:** <https://github.com/davidcreator/davidcms> (branch `main`, HEAD `bfc9e26`, tag `v1.0.0`)
**Data da análise:** 09/11/2026
**Escopo:** leitura integral do repositório + **instalação real** em ambiente descartável (MariaDB 11.8 + PHP 8.4 + Apache-equivalente), testes de runtime no site e no painel, testes de segurança e capturas de tela.

> ⚠️ **Nada do projeto foi copiado para o workspace.** O clone vive em `/tmp/davidcms` (descartável, fora do snapshot). Aqui ficaram apenas este relatório e as capturas de tela (≈400 KB). Podemos trabalhar direto contra o GitHub quando você decidir.

---

## 1. Veredito rápido

| Dimensão | Nota | Comentário em uma linha |
|---|---|---|
| Arquitetura / organização | **9/10** | MVCL sólido, namespaces, autoloader, Registry/Loader/Proxy/Event bem separados |
| Qualidade do código PHP | **8/10** | PHP 8 estrito, `php -l` limpo nos 380 arquivos, sem segredo hardcoded |
| Front-end público (tema) | **7/10** | Design novo e coerente, Three.js funcionando; faltam as imagens reais |
| Painel administrativo | **7/10** | Dashboard e editor BDRE existem e funcionam, com defeitos de UI pontuais |
| **Instalação / banco de dados** | **2/10** | 🔴 **Não instala em MariaDB moderno e instala incompleto** |
| **Testes automatizados** | **1/10** | 🔴 **Zero testes PHPUnit; `composer test` quebra** |
| CI/CD | **6/10** | 6 jobs verdes, porém **nenhum executa o sistema** |
| Documentação | **5/10** | Muita (54 mil linhas), porém inchada, duplicada e defasada em pontos-chave |
| Governança | **6/10** | Rigorosa, mas **congela justamente os arquivos que precisam ser corrigidos** |

**Resumo honesto:** o código é melhor do que a média de um CMS caseiro — está bem acima do "esqueleto" que a documentação às vezes sugere. O problema não é escrever features: é que **o sistema nunca foi instalado de verdade em um banco MySQL**. Por isso três defeitos de instalação/schema passaram batido por 11 fases, 83 ADRs e 418 commits, e o CI fica verde sem detectá-los.

---

## 2. Como esta análise foi feita (reprodutível)

```bash
git clone https://github.com/davidcreator/davidcms.git          # 78 MB, 2.698 arquivos
composer install --working-dir=dev/system/storage               # OK (Twig 3.30, PHPUnit 10.5…)
php -l em todos os 380 arquivos PHP                             # 0 erros
mariadb 11.8  +  php -S (docroot dev/)                          # wizard + site + painel
composer analyse (PHPStan nível 2)                              # 0 erros em 99 arquivos
bash tools/verify-*.sh                                          # 3/3 guardas PASS
```

Depois do wizard: login no painel, 25 telas do admin percorridas, formulário público de contato com CSRF, rate-limit, lockout de login, tentativa de path traversal, tentativa de SQLi, e capturas com Chromium headless.

*(As duas correções necessárias para conseguir instalar foram aplicadas **só no clone temporário** — nenhum arquivo do repositório foi alterado.)*

---

## 3. O projeto em números

| Métrica | Valor |
|---|---|
| Arquivos versionados | 2.698 |
| Linhas em `dev/` (PHP+Twig+JS+CSS) | ~397.000 |
| Arquivos PHP | 380 (catalog 18 controllers · admin 34 · install 5 steps · núcleo 99 analisados) |
| Tabelas no schema completo | 43 (prefixo `rms_`) |
| Arquivos SQL | 39 (1 `schema.sql`, 1 `schema-complete.sql`, **29 `upgrade_*`**, 5 `rollback_*`) |
| ADRs | **83** (`agents-ia/decisoes/0001…0083`) |
| Documentação | **498 arquivos `.md`** · 247 em `docs/` · ~54.000 linhas |
| Commits | **418** entre 12/10/2026 e 08/11/2026 (1 autor, 1 branch) |
| Scripts de teste/E2E | 109 em `tools/e2e/` — todos *shell/cjs* de fumaça |
| **Testes PHPUnit** | **0** |
| Jobs de CI | 6 (composer, php-lint, docs-links, static-analysis, node-deps, repo-guards) |

---

## 4. O que está realmente bom (não mexer)

1. **Núcleo MVCL bem resolvido.** `Autoloader` (namespace→snake_case), `Loader` com eventos before/after, `Proxy` de lazy-loading de models, `Action` com gramática de rota (`grupo/modulo.metodo|arg`) e `Router` que cai na rota de erro. É OpenCart repensado com `strict_types` e `final class` — e funciona.
2. **Sem vazamento de segredo.** Nenhuma chave de API/senha no código; `config.php` é gerado e está no `.gitignore`; `vendor/` e `node_modules/` corretamente fora do Git (ADR 0081).
3. **Segurança de base sólida** (testado de fato, ver §7): CSRF por token de uso único, rate-limit por IP com escopo, bloqueio de login por 15 min, `password_hash`/`password_verify` com rehash, cookies `HttpOnly`+`SameSite`, headers `X-Content-Type-Options`/`X-Frame-Options`/`Referrer-Policy`/`Permissions-Policy`/CSP (report-only), allowlist no servidor de assets 3D (path traversal bloqueado), upload com validação de conteúdo.
4. **Multi-idioma real.** `pt-br`/`en-us`/`es-eu` em todas as aplicações, negociação por `?language=`, `hreflang` + `canonical` no HTML público.
5. **SEO de verdade.** `/portfolio`, `/blog`, `/contato`, `/sitemap.xml` com URLs limpas, OpenGraph, `.htaccess` com mapa 301 cirúrgico do site antigo (inclusive `tools/open?id=N` → keyword).
6. **O tema novo ficou bom.** Hero com cena Three.js renderizando (`canvas` + WebGL ativos), header limpo, tipografia consistente (captura 01/02/03).
7. **O editor BDRE funciona** quando o banco está completo: 92 KB de tela, histórico de revisões, edição por idioma, campos customizados, aviso de conflito por *fingerprint*.

---

## 5. 🔴 Bloqueadores (P0) — impedem instalação e cutover

### P0-1 · `install.lock` está versionado no Git → o instalador nasce bloqueado
`dev/system/storage/install.lock` (conteúdo: `installed 2026-10-19T22:41:06+00:00`) está **no índice do Git**. Em um clone novo, o `dev/install/index.php` responde **403 "Installation locked"** antes de qualquer coisa.

**Correção:**
```bash
git rm --cached dev/system/storage/install.lock
echo "dev/system/storage/install.lock" >> .gitignore   # (ou **/system/storage/install.lock)
```

### P0-2 · O schema não importa em MariaDB ≥ 11.4 (colação `uca1400`)
**Evidência:** no passo 3 do wizard, após 28–32 tabelas criadas, a requisição morre e o painel de erro devolve 404. `system/storage/logs/error.log`:

```
Illegal mix of collations (utf8mb4_uca1400_ai_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT) for operation '='
```

**Causa raiz exata:** `dev/install/sql/schema.sql:390` cria a temporária `tmp_f10_catalog_page` com `DEFAULT CHARSET=utf8mb4` **sem `COLLATE`**. No MariaDB ≥ 11.4 (padrão do Debian 13 / Ubuntu 25.04+ / muitos hosts atuais), "sem COLLATE" = `utf8mb4_uca1400_ai_ci`, que é **incompatível** com as colunas `utf8mb4_unicode_ci` do resto do schema. Quebra no **statement 33 de 39** — exatamente o `INSERT ... SELECT ... CASE ... WHERE l.code IN ('en-us',…)`.

**Correção (1 linha, validada por mim):**
```sql
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
Feito isso, o import passou a rodar inteiro. *Alternativa/complemento:* fixar a colação da conexão no driver (`SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci`).

> Isto está em `schema.sql` **e** em `schema-complete.sql`. Sem corrigir, o ReamurCMS simplesmente não instala em host moderno.

### P0-3 · O instalador importa o schema **incompleto** (32 de 43 tabelas)
`dev/install/model/install/install.php::importSchema()` lê **`sql/schema.sql`** (32 tabelas, sem conteúdo). O repositório já tem **`schema-complete.sql` (43 tabelas + seed real)** — e ele não é usado por ninguém (só aparece em docs).

**Consequência medida por mim, numa instalação "bem-sucedida":** o sistema sobe, mas:
- `rms_blog_post*` (3), `rms_custom_field*` (4), `rms_import_contact`, `rms_mp_seller`, `rms_mp_payment` **não existem**;
- **o editor de páginas — o coração do produto — abre 404**: `Table 'reamur.rms_custom_field' doesn't exist` em `cms/page.edit`;
- `custom_field/definition` → 404; blog, marketplace e importação sem tabelas;
- `rms_setting` fica **vazia** (0 linhas) → nome do site vazio, `og:site_name` vazio, cache/TTL no default;
- portfólio, depoimentos, clientes e ferramentas ficam **zerados** (o seed `upgrade_f3_01_…` nunca é aplicado).

**Prova da correção:** aplicando `schema-complete.sql` (com o COLLATE do P0-2) numa base limpa → **43 tabelas, 10 projetos, 3 depoimentos, 5 logos, 4 ferramentas, 37 settings, 12 páginas, acentuação correta**. E o editor passa a abrir com 200 e 92 KB.

**Correção:**
```php
// dev/install/model/install/install.php::importSchema()
$sql = (string)file_get_contents(dirname(__DIR__, 2) . '/sql/schema-complete.sql');
```

### P0-4 · Não existe controle de versão de schema / migrations
- `rms_db_schema_version` é referenciada em código mas **não existe** em nenhum schema.
- As 29 migrações `upgrade_*.sql` são **arquivos manuais**: ninguém as aplica automaticamente (nem o wizard, nem o painel, nem comando).
- Aplicando as 29 em ordem sobre uma instalação do wizard, **2 falham por duplicidade** (`upgrade_f10_01`, `upgrade_f10_03`) — porque o `schema.sql` já contém parte do F10. Ou seja: **o caminho "schema + upgrades" e o caminho "schema-complete" divergiram** e não há como saber em que estado um banco está.
- Os `rollback_*.sql` cobrem apenas 5 das 29 migrações.

**Recomendação:** adotar um runner mínimo (tabela `rms_schema_migration(version, applied_at, checksum)` + comando no painel/CLI que aplica `upgrade_*` pendentes, idempotentes e na ordem). Isso, sozinho, elimina a classe de bug P0-3 para sempre.

### P0-5 · Zero testes automatizados
- `composer test` → **falha**: `Could not read XML from file "../../tests/phpunit.xml.dist"` (o arquivo não existe; `dev/tests/README.md` promete `unit/` e `integration/`, ambos ausentes).
- Os 109 scripts em `tools/e2e/` são *smoke tests* de shell/`cjs` — nenhum exercita o núcleo nem o banco.
- O workflow de CI **não tem job de teste**: roda `php -l`, PHPStan nível 2 (só `engine`/`library`/`helper`, 99 de 380 arquivos), checagem de links e guardas de governança.

**Consequência direta:** os defeitos P0-2, P0-3 e P0-4 são invisíveis para o CI. O pipeline fica verde com um produto que não instala.

**Mínimo recomendado:** `phpunit.xml.dist` + 10–15 testes (Registry, Action/Router, Loader, slug/SEO, `RateLimit`) e um job `db-smoke` que sobe MariaDB 11.8 no runner, roda o wizard por HTTP e falha se o login/editor não responderem 200. Esse job sozinho teria pegado os três P0.

---

## 6. 🟠 Riscos altos (P1)

### P1-1 · Colisão global de chaves de `language` → títulos errados na tela
O `Language` acumula **um único array `$_`**. Existem **25 arquivos** em `admin/language/en-us/` definindo `heading_title`, e todos escrevem na mesma chave. Resultado real observado: ao abrir **Content Pages** (`cms/page.edit`), o `<h1>` mostrou **“Blog · Edit post”** — `heading_title` e `text_edit` vieram de `blog/blog.php`, carregado depois. É cosmético agora, mas é uma armadilha permanente (qualquer nova tela pode “roubar” o rótulo de outra).
**Sugestão:** namespacear por arquivo (`$_['cms_page']['heading_title']`) ou guardar por rota no `Language`.

### P1-2 · Mídia de conteúdo não existe no repositório
`dev/catalog/view/image/` tem **6 imagens** no total (todas de sistema). `project/`, `client_logo/`, `testimonial/`, `tool/`, `category/` estão **vazias**. Resultado: o portfólio renderiza as iniciais (J, S, F, C…) em vez dos trabalhos reais (captura 03), e o mesmo vale para logos de clientes e depoimentos. Se o deploy sai assim, o site vai ao ar sem portfólio visual.
**Sugestão:** definir a política de mídia (versionar `webp` otimizados com `git-lfs`/pasta `assets` **ou** um passo `tools/sync-media.sh` de origem externa) e documentá-la no runbook de cutover.

### P1-3 · O administrador recém-instalado não consegue administrar
O wizard semeia grants mínimos (13 rotas). Com isso, **`setting/setting`, `setting/site_info`, `setting/marketplace`, `design/theme`, `blog/*` e `localization/language` respondem 302 → `error/permission`** para o admin que acabou de criar. Não há tela inicial para conceder essas permissões (o próprio `user_group` está liberado, mas exige editar JSON de permissões na mão). Ou seja: o dono do site não consegue trocar o nome do site, o tema nem gerenciar o blog sem editar o banco.
**Sugestão:** o grupo Administrator nasce com `access`/`modify` em **todas** as rotas (o “mínimo” é bom para grupos auxiliares, não para o grupo 1).

### P1-4 · Deploy depende de Apache `.htaccess` (e não há equivalente Nginx)
URLs limpas (`/portfolio`, `/blog`), o mapa 301 e o runtime Three.js (`/three-runtime/...`) dependem de regras em `dev/catalog/.htaccess`. Em Nginx nada disso funciona e o `common/three.asset` responde 404 — o README fala em “Apache **ou** Nginx” sem entregar a config Nginx. Vale decidir: Apache (como está) e escrever o `server{}` equivalentes, ou mover as regras para o `startup/router` (que já faz parsing de `REQUEST_URI`).

### P1-5 · Detalhes de SEO/produção
- `robots.txt` existe somente em `dev/robots.txt` e `dev/admin/robots.txt`; se o docroot apontar para `dev/catalog/` (como no README), `/robots.txt` e `/favicon.ico` dão **404** (confirmei ambos). O `sitemap.xml` dinâmico funciona.
- `X-Powered-By: PHP/8.4.27` exposto (catalog e admin). `expose_php=Off` resolve.
- CSP está em **`Content-Security-Policy-Report-Only`** — não protege nada em produção; e permite `'unsafe-inline'`/`'unsafe-eval'` em `script-src`.

### P1-6 · Documentação desatualizada em pontos que confundem
- O **README** ainda lista as Fases 1–6 como `[ ]` (não feitas), quando o código já tem F1→F11 implementadas e 83 ADRs. Quem entra no repositório lê “núcleo não portado”.
- `docs/ESTADO-GOVERNANCA.md` afirma “`dev/` no HEAD: **0**”, o que é falso hoje (o diretório está completo) — o documento é de um HEAD antigo e continua sendo citado como fonte de verdade.
- **Duplicatas:** `docs/BDREditor — complete project reference and closure.md` **e** `docs/BDREditor-Complete-Reference-and-Closure.md`; duas auditorias de limpeza de GitHub com nomes quase iguais; 15 `F11-XX-INDICE.md` + 14 `CHECKSUMS*.txt` na raiz.
- **Dízima de governança:** 54 mil linhas de markdown para 397 mil de código, com 20 arquivos de processo na raiz. O custo é real: a documentação de processo já consumiu mais atenção que a validação do produto (ver P0).

---

## 7. 🟡 Médios (P2) e defeitos de interface

| # | Achado | Evidência |
|---|---|---|
| P2-1 | **Tela “Panel Appearance” com cards sobrepostos** — os textos dos 6 skins vazam por cima dos cards vizinhos (grid sem largura mínima / falta `overflow`). | captura 07 |
| P2-2 | **Botão “Login” sem estilo** na tela de login do admin (vira texto solto sob os campos). | captura 04 |
| P2-3 | **Badge “Featured” cortado** no rodapé dos cards do portfólio (posicionamento inconsistente entre cards). | captura 03 |
| P2-4 | `cms/block_pattern` não tem `index()` → qualquer acesso direto gera “Method not callable” no log. Baixo impacto (é AJAX-only), mas polui o log. | `error.log` |
| P2-5 | PHPStan **nível 2** só no núcleo (99 de 380 arquivos) e PHPStan 1.x (aviso de 480 dias / 65 versões atrás). Controllers, models e views ficam fora da análise. | `phpstan.neon` + `composer analyse` |
| P2-6 | `eval()` em `template/tpl.php:213` e `template/phtml.php:187` (engines legados). Hoje o padrão é Twig com `autoescape: html`, mas os adaptadores legados continuam disponíveis por configuração. | grep |
| P2-7 | Verificação legada `md5`/`sha1(salt+…)` em `library/cart/user.php` e `cart/customer.php` — só para contas antigas, com rehash para `password_hash`, mas mantém caminho fraco se o `customer` legado for migrado. | grep |
| P2-8 | Importação manual de SQL por CLI precisa de `--default-character-set=utf8mb4` (sem isso os acentos viram `RogÃ©rio`). O wizard não sofre (usa `mysqli::set_charset`), mas o runbook de migração deve mandar a flag. | reproduzido |

---

## 8. Segurança — o que eu ataquei e o resultado

| Teste | Resultado |
|---|---|
| POST de contato **sem** CSRF | ✅ rejeitado (token de uso único) |
| 8 POSTs válidos em sequência (rate-limit `contact`) | ✅ 4 gravaram, 4 bloqueios; `rms_rate_limit.attempts` incrementa |
| 6 tentativas de senha errada no login | ✅ contador sobe e a conta **trava 15 min** (“Too many login attempts”) |
| Path traversal em `common/three.asset` (`../../config.php`) | ✅ 404, allowlist por extensão |
| `' OR 1=1 --` em URL pública / `../../../etc/passwd` | ✅ 404, rota sanitizada |
| SQLi por concatenação nos controllers | ✅ nenhuma query com `$_GET/$_POST` cru; 292 usos de `escape/quote` |
| Segredos no código | ✅ nenhum encontrado |
| Cabeçalhos e cookie de sessão | ✅ `HttpOnly`, `SameSite=Lax`, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` (faltam HSTS e CSP ativa) |
| Acesso ao admin sem sessão | ✅ 302 → `/login` |

**Conclusão de segurança:** a base é boa e defensiva. O que falta é endurecimento de produção (CSP enforcing, `expose_php=Off`, HSTS no host) e revisão dos engines legados.

---

## 9. Repositório × produção (estado real do cutover)

| | Produção hoje (`davidalmeida.xyz`) | Repositório |
|---|---|---|
| Stack | PHP **8.3** na **Hostinger**, CMS próprio simples (`/catalog`, `/admin/login`, instalador com `.env`/PDO) | ReamurCMS, PHP 8.2+, Twig 3 |
| `/` | 302 → `/catalog` | home nova renderizada (captura 01) |
| `/install/` | responde **200** com “Sistema já instalado… instalador bloqueado” | wizard completo (3 passos) |
| Marcadores ReamurCMS no HTML público | **0** | — |

**Leitura:** o ReamurCMS **ainda não está no ar**; o site em produção é o CMS antigo. Isso é ótimo — significa que dá para corrigir os P0 sem pressa de produção. E note: o site antigo já implementou corretamente o bloqueio do instalador (o novo, ironicamente, nasce bloqueado pelo arquivo versionado).

---

## 10. Plano de ação sugerido

### Onda 1 — “fazer instalar” (1–2 dias, sem feature nova)
1. **Descongelar o schema** com uma ADR (hoje `schema.sql`/`schema-complete.sql` estão marcados como *intocáveis* em `docs/ESTADO-GOVERNANCA.md` e `docs/RELATORIO-GERAL.md` — é justamente o que impede corrigir o P0-2). Sugestão: `ADR 0084 — schema como artefato versionado e migrável`.
2. `COLLATE utf8mb4_unicode_ci` na temporária de `schema.sql` **e** `schema-complete.sql` (P0-2).
3. `importSchema()` passa a usar `schema-complete.sql` (P0-3) — e apagar/arquivar `schema.sql` para não haver duas verdades.
4. `git rm --cached dev/system/storage/install.lock` + `.gitignore` (P0-1).
5. Grupo Administrator nasce com todas as rotas (P1-3).
6. Rodar o wizard em MariaDB 11.8 e 10.6 + MySQL 8 e registrar a evidência.

### Onda 2 — “não voltar a quebrar” (3–5 dias)
7. `phpunit.xml.dist` + suíte mínima de núcleo (P0-5).
8. Job de CI **`db-smoke`**: sobe MariaDB 11.8, roda o wizard via HTTP e exige 200 em `/`, `/portfolio`, login, `common/dashboard` e `cms/page.edit|1`.
9. Runner de migrações + `rms_schema_migration` (P0-4), com checksum por arquivo.
10. Namespacear chaves de `language` (P1-1).

### Onda 3 — “deixar apresentável para o cutover”
11. Política de mídia + carga das imagens reais do portfólio/clientes (P1-2).
12. Corrigir os três defeitos de UI (P2-1 a P2-3) e o botão de login.
13. CSP enforcing, `expose_php=Off`, HSTS; `robots.txt` e `favicon.ico` na raiz pública (P1-5).
14. Config Nginx **ou** decisão explícita “Apache only” + runbook (P1-4).
15. Atualizar README (roadmap real F1→F11), podar duplicatas e reduzir a raiz a `README/CONTRIBUTING/SECURITY/CHANGELOG` (P1-6).

---

## 11. Decisões que preciso de você antes de escrever código

1. **Onde vamos trabalhar?** (a) eu preparo os patches aqui e você aplica/PR no GitHub, (b) abrimos issues/PRs direto no repositório via conector do GitHub, ou (c) você me dá um branch de trabalho.
2. **Podemos descongelar o schema?** Sem isso, o P0-2/P0-3 não têm correção legítima pela própria governança do projeto.
3. **`schema-complete.sql` é a fonte da verdade** (e o `schema.sql` morre), ou preferimos manter os dois e gerar o completo a partir das migrações?
4. **Escopo do próximo ciclo:** só “instalação + testes” (Onda 1 e 2), ou já entramos na parte visual/editor que você está atualizando?
5. **Mídia:** você quer as imagens versionadas no repositório (com LFS) ou sincronizadas por script no deploy?

---

## Anexo A — Capturas (em `capturas/`)

| Arquivo | O que mostra |
|---|---|
| `01-site-home-desktop.jpg` | Home nova, hero com cena Three.js ativa |
| `02-site-home-mobile.jpg` | Home em 390×844 |
| `03-site-portfolio.jpg` | Portfólio com 10 projetos do seed — e as imagens ausentes |
| `04-admin-login.jpg` | Login do painel (botão sem estilo; depois aparece o aviso de lockout) |
| `05-admin-dashboard.jpg` | Dashboard real com contadores, menu em 3 idiomas, tema escuro |
| `06-admin-editor.jpg` | Editor de páginas (BDRE) aberto — só funciona **após** aplicar as migrações |
| `07-admin-appearance.jpg` | “Panel Appearance” com os cards sobrepostos |

## Anexo B — Comandos que reproduzem os achados

```bash
# P0-1
git ls-files dev/system/storage/install.lock      # aparece → instalador nasce 403

# P0-2 / P0-3 (import isolado, sem wizard)
php -r '$m=new mysqli("127.0.0.1","root","","teste",3306);$m->set_charset("utf8mb4");
 $s=preg_replace("/^\s*--[^\n]*$/m","",file_get_contents("dev/install/sql/schema.sql"));
 foreach(array_filter(array_map("trim",explode(";\n",$s))) as $q){ try{$m->query($q);}catch(Throwable $e){echo $e->getMessage(),"\n";} }'
# → Illegal mix of collations ... (statement 33)

# P0-5
composer --working-dir=dev/system/storage test    # Could not read XML ... tests/phpunit.xml.dist

# P2 / P1-3
curl -b cookie.txt 'http://host/admin/index.php?route=setting/setting' -o /dev/null -w '%{http_code} %{redirect_url}\n'
# → 302 .../error/permission
```

---

## Anexo C — Correções aplicadas no kit `patches/` (F11-17 / ADR 0084)

Depois desta análise, as correções abaixo foram **implementadas e validadas** (clone limpo + instalação real, drivers `mysqli` e `pdo`, MariaDB 11.8 na colação padrão `uca1400`).

| # | Achado original | Correção entregue |
|---|---|---|
| P0-1 | `install.lock` versionado | Removido do índice + `.gitignore` |
| P0-2 | `Illegal mix of collations` no statement 33 | `COLLATE=utf8mb4_unicode_ci` na temporária (1 linha por arquivo) |
| P0-3 | Wizard importava 32 de 43 tabelas | `importSchema()` usa `schema-complete.sql` |
| **novo** | **Caminho PDO do importador abortava (erro 2014, 4 tabelas)** | `MYSQL_ATTR_USE_BUFFERED_QUERY` + `prepare/execute/closeCursor()` |
| **novo** | **`explode(";\n")` quebrava com `PREPARE…EXECUTE…DEALLOCATE` na mesma linha e com `;` seguido de comentário** | `splitStatements()` ciente de aspas e comentários (o `;` do HTML do seed, tipo `&nbsp;`, deixou de ser risco) |
| P1-3 | Admin recém-instalado com 13 rotas | Grupo Administrator deriva as **27 rotas** do disco (módulos futuros entram sozinhos) |
| P2-1 | Cards de skin sobrepostos | `flex-direction: column` + correção de especificidade |
| P2-2 | Botão "Login" sem estilo | Regras próprias para `.auth-card` (o card não vive dentro de `.admin-content`) |
| P2-3 | Badge "Featured" cortado | Card do portfólio em coluna flex com `margin-top: auto` no badge |
| — | Mídia sem política | `.gitattributes` com Git LFS para `webp/jpg/png/glb/woff2/…` |

**Resultado medido:** instalação completa (43 tabelas, 10 projetos, 37 settings, 12 páginas, admin com 27 rotas), editor BDRE abrindo em 200, telas antes bloqueadas em 200, zero linhas no `error.log`, e os três defeitos de UI corrigidos (medidos via CSSOM e confirmados nas capturas 08–11).

### Achados novos desta rodada (para as próximas fases)

| # | Achado | Detalhe |
|---|---|---|
| N-1 | Duplicação de `catalogAssetBase()` | **9 controllers** do admin repetem o mesmo helper que assume `admin/` irmão de `catalog/`. Funciona no layout da Hostinger, mas quebra se um dia o catálogo for servido na raiz do domínio. Candidato a um único helper no núcleo (`library/theme`). |
| N-2 | `js-thumb.js`/`threejs-thumb.js` depende da topologia de pastas | Em sandbox com docroots separados a URL vira `/catalog/three-runtime/…` e dá 404. É consequência de N-1, não um bug isolado. |
| N-3 | Colisão de chaves de `language` (P1-1) **continua latente** | Depois da correção de ACL o `<h1>` passou a mostrar o título certo por coincidência de ordem de carga. A causa (array global `$_`) permanece. |

---

## Anexo D — F11-18 / F11-19: blindagem (ADRs 0084 e 0085)

Segunda rodada, executada nesta mesma sessão: o que impede a instalação de voltar a quebrar em silêncio.

### Entregue

| Item | O que resolve |
|---|---|
| `rms_schema_migration` + `tools/migrate.php` | Estado do schema deixa de ser adivinhação: `status`, `run`, `--dry-run` e `baseline` para bancos antigos. Checksum SHA-256 por migração detecta arquivo alterado após aplicado |
| `install/sql/migrations.json` | Ordem das 29 migrações por fase + flag `in_schema_complete` (o que já está dobrado no schema completo) |
| `system/library/sql/splitter.php` | **Um** divisor de SQL (aspas e comentários) usado pelo instalador e pelo runner — antes havia duas cópias, uma delas `explode(";\n")` |
| `tools/e2e/db-smoke.sh` | Instala em banco real e navega: 44 tabelas, seed, 6 rotas públicas, 8 telas do painel com login real, editor BDRE, path traversal, migrações e `error.log` |
| `tools/e2e/lib/php-router.php` | Espelha o `.htaccess` no `php -S` — dá para desenvolver/testar sem Apache |
| Job `db-smoke` no CI | MariaDB **11.8** como serviço, colação padrão do servidor de propósito (é a regressão) |
| Seção 6 em `tools/verify-ci-workflow.sh` | O contrato do CI passa a exigir o próprio smoke |
| Front controllers com `__DIR__` | App deixa de depender do diretório de trabalho para achar o `config.php` |

### Prova de valor

| Cenário | `db-smoke.sh` |
|---|---|
| Código original (`bfc9e26`) | **28 FAIL** |
| Com os 10 patches | **41 PASS / 0 FAIL** |

Os 28 "FAIL" do código original são exatamente os defeitos do §5 deste relatório chegando até o CI pela primeira vez.

### Estado final do projeto

- Instala do zero, nos dois drivers, em MariaDB 11.8 com a colação padrão.
- Editor de páginas, blog, custom fields, tema e configurações acessíveis ao admin recém-criado.
- Regressão de instalação/schema/ACL detectada em minutos no PR.
- Nenhuma linha no `error.log` durante todo o fluxo.

