# KAZTheme-Editor — Boot e Ferramentas (Frente AD)

Diagnóstico e correção do sintoma **"nenhuma ferramenta responde e não existe
erro no console do DevTools"**, relatado após a aplicação da Frente AC.

## 1. O sintoma e o que ele significa

Um erro no console indicaria exceção, 404 de asset ou bloqueio de CSP.
**Console limpo + ferramentas inertes** indica que o código nunca chegou a
registrar seus listeners. Há duas formas de isso acontecer sem erro: o
`<script>` não está no HTML, ou o script executa e **sai cedo por um `return`
condicional**. Aqui foi a segunda.

## 2. Cadeia causal medida

| Etapa | Arquivo | Fato |
| --- | --- | --- |
| 1 | `design/theme.php:200-202` | registra CSS e JS via `addScript()`; **sem condição de BD** |
| 2 | `Document::addScript()` | guarda o href em `$scripts` |
| 3 | `common/header.php:33` | `$data['scripts'] = getScripts()` |
| 4 | `common/header.twig:45-47` | `{% for src in scripts %}<script src="{{ src }}"></script>{% endfor %}` no **`<head>`**, sem `defer` |
| 5 | `kaz-theme-editor.js:71-72` | `var root = document.getElementById('kaz-theme-editor'); if (!root) return;` |
| 6 | navegador | `<body>` ainda não parseado → `root === null` → saída silenciosa |

`footer.twig:29-30` já usava `defer` em `admin.js` e no Bootstrap. A
inconsistência entre head e footer era a armadilha.

## 3. Alcance: nove módulos mortos

Todos os scripts que o controller de temas registra fazem `getElementById`
no início de uma IIFE imediata:

| Script | Linha do `if (!root) return;` |
| --- | --- |
| `theme-package.js` | 7 (`!input \|\| !area`) |
| `theme-condition-builder.js` | IIFE imediata |
| `theme-scene-editor.js` | 7 |
| `theme-section-builder.js` | 8 |
| `theme-component-library.js` | 7 |
| `kaz-theme-editor.js` | 72 |
| `theme-schedule.js` | 7 |
| `theme-media.js` | 8 (condição combinada) |
| `theme-sites.js` | 9 |

Isso é coerente com a reprovação histórica da AA: *"nada funciona, nenhuma
das funcionalidades presentes tem resultado para criação de temas"*. As
frentes AB e AC entregaram estado-sem-JS utilizável, tema publicado e
layout responsivo, mas **não** religaram a inicialização.

## 4. Por que a validação anterior não pegou

`validacao/ac_site.py` (Frente AC) montava o documento de teste com
`<script src="…" defer></script>`. Com `defer`, o DOM já existe e o boot
funciona. Chromium mediu 251/0 — corretamente, porém sobre uma ordem de
carregamento que o Admin não produz. Lição: um harness que usa atalhos de
montagem pode ser verde enquanto a página real está inerte. A AD passa a
testar explicitamente o **`<head>` sem `defer`**.

## 5. A correção

Ver seção "Frente AD" em `RELATORIO.md` e nos demais documentos. Resumo:
`addScriptDeferred()` + segundo loop `defer` no `header.twig` (impacto
restrito ao controller de temas), 9 chamadas migradas, cache
`-kaz-theme-editor-ad` e `boot()` resiliente no JS principal.

**Sem migration.** `kaz_scene` vive dentro de `draft_config`/`published_config`
já existentes; a ausência é tratada por `normalizeScene(null)`. O controller
não consulta `setting`, `extension` nem permissão para decidir incluir o JS.

## 6. Como confirmar no servidor

1. Extrair o pacote na raiz e dar **refresh forçado** (Ctrl+F5) no Admin.
2. DevTools → Network: `kaz-theme-editor.js?v=…-kaz-theme-editor-ad` deve
   vir **200**.
3. DevTools → Console: digitar
   `document.getElementById('kaz-theme-editor').__kaz.ui()` — deve devolver o
   objeto de estado, não `undefined`.
4. Clicar numa aba de módulo, trocar o preset de tela e em **Salvar**; o
   status muda e o formulário é submetido.
5. Se `__kaz` continuar `undefined`, verificar se um proxy/CDN serve o JS
   antigo em cache e se o `header.twig` ativo contém o loop
   `scripts_deferred`.

---

## Frente AE — Aderência ao `main`, fail-loud e diagnóstico em produção

Pela primeira vez foi possível ler o repositório **real** em vez de uma réplica
local: a API devolvia sempre `13b0f7b…` por cache até que uma consulta com
`per_page=5` e `nocache` novo revelou dois commits.

| Commit | Data (UTC) | Mensagem |
| --- | --- | --- |
| `50d4844f66d9…` | 2026-11-04 19:46:39 | `fix(admin/theme-editor): correct browser layout issues from AB release` (AC) |
| `56fd0c5d40fb…` | 2026-11-04 20:06:31 | `fix(admin/theme-editor): fix silent theme editor boot failure` (AD) |

`pushed_at = 2026-11-04T20:06:35Z`; árvore `3194` entradas / `2611` blobs /
`truncated:false`. Auditoria baixando o conteúdo cru de `56fd0c5d40fb`:
**19/19 arquivos entregues pelas frentes AC e AD são byte a byte idênticos ao
`main`** — incluindo `theme.php` (70.109), `kaz-theme-editor.js` (47.669),
`header.twig` (6.089), `document.php` (2.476) e o CSS (34.897). Os 405 paths
presentes no `main` e ausentes no recorte local são 227 `index.html` e 178
binários (fontes, imagens, sourcemaps) que o download filtra; **nenhum
arquivo de migration novo**. `schema.sql` segue intacto.

### O que ainda faltava

Desde a frente A, a confirmação recebida era sempre "aplicado com sucesso,
github atualizado" — nunca "as ferramentas agora respondem no meu servidor".
E o sintoma que levou quatro frentes para ser diagnosticado era justamente o
**silêncio**: ferramentas inertes com console limpo. A AD corrigiu a causa,
mas não deixou nenhum sinal visível caso o problema volte (Twig compilado em
cache, CDN servindo JS antigo, CSP, asset 404).

### Correções da AE

1. **Fail-loud** — `bootDone` passa a ser marcado **depois** de confirmar o
   root, permitindo que `DOMContentLoaded` e `load` tentem novamente. Se,
   passados 500 ms, o boot não ocorreu e a página é de fato a do editor, um
   aviso `role="alert"` é inserido com `createElement` + `textContent`,
   explicando o que verificar. Sem `innerHTML`, rede, `eval` ou exceção.
2. **`data-kaz-health="ad+ae"`** no root, para o diagnóstico saber qual
   versão do JS está viva.
3. **Cache** `-kaz-theme-editor-ad` → **`-kaz-theme-editor-ae`** (refresh).
4. **`tools/kaz-theme-editor-health.js`** (novo) — snippet **somente leitura**
   para colar no console do DevTools na página de temas. Devolve um JSON com
   veredito (`OK` / `FALHA_ASSET_OU_DEFER` / `FALHA_BOOT` /
   `OK_COM_RESSALVAS`), assets e versões, `data-kaz-ready`, `__kaz`, os 9
   módulos, medidas de layout, CSRF/form e o aviso fail-loud. Use
   `copy(__kazHealth())` para copiar.
5. **`tools/verify-kaz-health.cjs`** (novo) prova em Chromium que o health
   distingue o estado quebrado do corrigido, e que o aviso aparece **só**
   quando o boot falha.

### Banco de dados

Sem migration, novamente. A auditoria do `main` confirmou que não foi
adicionado nenhum `.sql` além do diagnóstico somente-leitura da AD. A cadeia
de publicação continua usando `draft_config`/`published_config` existentes.

### Limite

Chromium headless local não é o servidor de produção. A AE entrega o
**instrumento** para medir o servidor real; o veredito que importa é o que
`__kazHealth()` devolver no Admin dele. CI verde ou health check local não
significa release aprovada.
