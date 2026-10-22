# ADR 0045 — F8-01: Shell visual admin (sidebar+topbar), dashboard com contadores reais e três bugs latentes corrigidos

**Status:** aceito
**Data:** 2026-10-22
**Contexto:** Fase 8 (visual admin, escopo incremental: chrome + login + dashboard primeiro, telas internas migram em lotes). HEAD remoto de partida `d1de5efb3cb3`.

## Decisão de arquitetura

1. **Shell admin** com sidebar fixa colapsável + topbar fina (logo ReamurCMS, toggle dark/light via `data-bs-theme` com anti-flash inline, menu de idiomas, dropdown do usuário). Mobile: sidebar vira Offcanvas do Bootstrap.
2. **CSS entregue no mesmo caminho** (`admin/view/css/admin.css`) — zero alterações nos ~15 controllers que já fazem `addStyle(admin.css)`. Ordem de carga: `bootstrap.css` → `bootstrap-icons.css` (1.11.3, vendida local com woff2/woff) → `reamurcms.css` (tokens/tipografia do usuário) → `admin.css` (shell, por último para overrides). Compat preservada: classes legadas `.panel`/`table`/`field`/`grid`/`muted` usadas pelas telas internas ainda não migradas (F8-02+).
3. **Menu 100% ACL-driven** (server-side, `hasPermission('access', rota)`), item ativo marcado server-side, grupos sem controllers existentes **não aparecem** (nada de placeholder morto: sem customer/design/extension/report/api/mail).
4. **Dashboard com contadores reais ACL-gated** (padrão ADR 0043: guard `SHOW TABLES` antes de contar; webhooks falhos 24h = `http_code >= 400 OR http_code = 0`), quick actions modify-gated, listas "últimos 5 posts/mensagens". Model novo `admin/model/common/dashboard.php`.
5. **Anti-CDN**: nenhum `<link href="http…` / `<script src="http…` no HTML renderizado. Fontes do `reamurcms.css` (Google Fonts via `@import`) permanecem — são do CSS do usuário, regra só vale para o DOM.
6. **Login fora do chrome** (`body.admin-shell--auth`, sem sidebar/topbar).
7. Guards `index.html` criados nos diretórios novos (`admin/view/css/fonts`, `admin/model/common`, `admin/view/template/common`) e no pendente `image/`.

## Bugs latentes reais encontrados e corrigidos (fora do "visual")

### I. Idiom JSON ineficaz no MariaDB 11.8.6 (revoke/restore de grants)
`JSON_REMOVE(doc, JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(doc,'$.k'),'one','v')))` **não remove nada silenciosamente**: `JSON_SEARCH` **com path** retorna path **relativo** (`"$[15]"`), inválido contra o documento inteiro no `JSON_REMOVE` (que precisa de path absoluto). Idiom correto agora usado na bateria e recomendado para qualquer manutenção futura:

```sql
JSON_SEARCH(permission,'one','blog/blog',NULL,'$.access')           -- → "$.access[N]"
JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','blog/blog',NULL,'$.access')))
```
Camadas de OTL relacionados já tratadas nesta fase e anteriores:
- Bash: `'$[15]'` dentro de `mysql -e "…"` vira `'15'` (aritmética obsoleta `$[expr]`) → usar arquivo SQL ou JSON_SEARCH interno. 
- `JSON_REMOVE` com paths múltiplos pode devolver NULL → nunca remover access+modify no mesmo statement; CASE por chave com guard `IS NOT NULL`.
- Prova funcional do menu ACL (teste nuclear com grupo Editor): sistema estável; as falhas eram só do idiom.

### II. Sessão DB suicida (`session_expire` nunca semeado)
`system/library/session/db.php` gravava `expire = now + (int)$config->get('session_expire')`. Como o wizard **nunca semeou** `session_expire` em `rms_setting` (só outras config_*), o valor era null → 0 → **sessão morria no mesmo segundo da gravação**. Request espetado no mesmo segundo passava; qualquer sequência com >1s dava 302 em cascata para o login. Perpetente desde F2 — nunca flagrado porque as baterias antigas são rápidas/determinísticas.
**Fix:** (a) fallback defensivo no adapter (`?: 3600`); (b) seed idempotente no `upgrade_f8_01_permission_canonical.sql` (`INSERT IGNORE … 'session_expire','3600'` — sem `store_id`, colunas reais: `code,key,value,serialized`).

### III. Schema/seed da bateria × schema real (blogs/mensagens no lab)
Colunas reais divergem do plano inicial: `blog_post(post_id,image,status,date_published,date_added,date_modified)` com `date_added/date_modified` NOT NULL sem default; `blog_post_description` tem `meta_title,meta_description` e **não** tem `tag`; `seo_url` **não** tem `store_id`. Seeds da bateria reescritos (INSERT … SET amigável a erro 1364/1136/1054; `mysql -e` multi-statement **aborta tudo** no primeiro erro — um INSERT quebrado matava o bloco de mensagens silenciosamente).

## Correção na própria bateria (contrato, não código)
- **f701 check 10** exigia `grep -qF 'blog\\/blog'` (literal com 2 barras) no router. O HEAD `d1de5efb3cb3` traz `\/` (1 barra) — ambos são regex-PHP equivalentes (`'\/'` em single-quoted PHP casa `/`). Check tornado tolerante: `grep -qE 'blog\\\\?/blog'`. Código do router **não foi alterado**.
- **f406**: strings legadas do indicador de cache (`Cache: %s · TTL %ss`, `… "%s" — fallback active`) e chaves `text_manage_*`/`text_modules` são **contrato de bateria** — reintroduzidas nos três idiomas do novo dashboard language. O twig F8 já renderizava o bloco; só o formato dos textos mudou para o contrato legado.

## Verificação (lab :8090, MariaDB 11.8.6, prefixo `rms_`, banco recriado do zero a cada run)
- `tools/e2e/e2e_f801.sh`: **10/10** (shell renderiza, assets 200, menu ACL-driven com revoke/restore reais, active-state, cards com contadores que batem SQL, quick-actions modify-gated, login isolado, anti-CDN, i18n ×3, guards+lint+rotas 200).
- Regressão completa: **F2 (7 suítes, 14–17 checks), F3 (5), F4 (8), F5 (3), F7 (3) — todas verdes**, inclusive f406 (cache) e f701 (router/blog) após os ajustes de contrato.

## Consequências
- Telas internas pendentes de migração visual (F8-02 blog, F8-03 user/setting, F8-04 sale/marketing) convivem com o shell via classes de compat — nenhum controller interno foi tocado, exceto `blog/blog.php` e `blog/import.php` (método `chrome()` que não montava header/footer; corrigido, era latente F7).
- Qualquer novo grant/revoke deve usar o idiom JSON_SEARCH com path completo (`NULL,'$.access'`).
- Novo índice de asset versionado: `bootstrap-icons.css` + woff2/woff locais (não tocar em CDN).
