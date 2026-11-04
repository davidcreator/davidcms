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
