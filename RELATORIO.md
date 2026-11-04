# ReamurCMS — Frente AD · Boot após o DOM, Ferramentas Operacionais

> **"Nenhuma ferramenta responde e não existe erro no console do DevTools."**
> Console limpo não era sinal de saúde: era o sintoma de um `return`
> silencioso. Esta frente reproduz o defeito em Chromium e o corrige.
> **Não é banco de dados — nenhuma migration é necessária.**

| | |
| --- | --- |
| Base | estado pós-AC confirmado pelo usuário (`13b0f7b8475b…` + 12 arquivos da AC) |
| Arquivos | **17** completos (14 substituições + 3 novos) + 4 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Recorte | 2203 arquivos / 31,096,310 bytes |
| Réplica | 2206 arquivos / 31,139,098 bytes (14 alterados + 3 novos + 2189 intactos) |
| Harnesses | boot 22/0 · alcance 5/0 · Chromium defer 251/0 · Chromium head-real 251/0 · layout 90/0 · DOM 226/0 · PROBE 93/0 = **938/0** |
| Validação final | 132 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos HEAD × réplica |
| Sintaxe | 388 PHP 0 erros · 611 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 23/23 · `sha256sum -c` 54/54 |

---

## 1. O diagnóstico

O `header.twig` emitia no **`<head>`, sem `defer`**, todos os scripts de
`addScript()`:

```twig
{% for src in scripts %}<script src="{{ src }}"></script>{% endfor %}
```

O `kaz-theme-editor.js` é uma IIFE que começa com
`var root = document.getElementById('kaz-theme-editor'); if (!root) return;`.
No `<head>` o `<body>` ainda não foi parseado → `root === null` → **`return`
silencioso**. Nenhum listener é registrado e **o console permanece limpo**.

O mesmo `if (!root) return;` existe em `theme-package.js`,
`theme-condition-builder.js`, `theme-scene-editor.js`,
`theme-section-builder.js`, `theme-component-library.js`,
`theme-schedule.js` e `theme-sites.js` (`theme-media.js` já era tolerante).
Ou seja: **os nove módulos do editor de temas estavam desligados** — o que
explica a reprovação original da AA ("nada funciona, nenhuma das
funcionalidades tem resultado para criação de temas"). AB e AC entregaram
estado-sem-JS, tema publicado e layout responsivo, mas não religaram a
inicialização.

### Por que a AC não viu isso

`validacao/ac_site.py` da AC montava o documento de teste com
`<script src="…" defer></script>`. Com `defer` o DOM já existe e o boot
funciona: 251 asserções Chromium passaram — sobre uma ordem de carregamento
que o Admin real **não** produz. A AD passa a testar o `<head>` sem `defer`.

Reprodução medida (`tools/verify-kaz-boot.cjs`, 22/0): com o JS da AC no
`<head>` sem `defer`, `data-kaz-ready` ausente, `__kaz` ausente, clique em aba
não abre painel e **zero erros de console**. Com o JS da AD, boot, abas,
presets, cena e Publicar ativos. Medição de alcance
(`validacao/ad_all_scripts.cjs`, 5/0): 7 módulos saem cedo no `<head>` e
**zero** saem com `defer`.

## 2. A correção

1. **`Document::addScriptDeferred()` / `getScriptsDeferred()`** — novo par de
   métodos; `addScript()` fica **intacto**.
2. **`common/header.php`** publica `scripts_deferred`; **`header.twig`** ganha
   um segundo loop `<script src="{{ src }}" defer></script>`, mantendo o loop
   original sem alteração. Impacto restrito ao controller de temas: os outros
   5 controllers preservam a ordem anterior, sem risco para os 6 templates
   que têm `<script>` inline no corpo.
3. **`design/theme.php`** troca as **9** chamadas e o cache passa a
   `-kaz-theme-editor-ad` (refresh forçado).
4. **`kaz-theme-editor.js`** — defesa em profundidade: todo o corpo vive em
   `boot()`, disparado assim que `#kaz-theme-editor` existe; caso contrário
   espera `DOMContentLoaded` com `load` como fallback e guard de idempotência.
   A decisão é pela **presença do elemento**, não por `readyState` (jsdom
   reporta `loading` mesmo com o DOM montado).

## 3. Banco de dados — por que NÃO entreguei migration

Você autorizou criar o arquivo de atualização se fosse necessário. **Não é.**
Medimos a cadeia: a inclusão do JS em `design/theme.php:200-202` é
**incondicional** — nenhum `setting`, `extension`, permissão ou consulta
condiciona `addScript()`. A cena publicada vive dentro de
`draft_config` / `published_config`, colunas preexistentes; a ausência da
chave `kaz_scene` é tratada por `ThemePackage::normalizeScene(null)` como
estado "não salvo". A AD não cria coluna, não faz `INSERT`, `UPDATE`,
`ALTER TABLE` nem `CREATE TABLE`.

Criar uma migration aqui seria inútil e arriscado. Em vez dela, o pacote traz
`tools/diagnostico-kaztheme-somente-leitura.sql`, **apenas SELECTs** e com
aviso explícito no cabeçalho de que **não é** arquivo de atualização: serve
só se você quiser confirmar por conta própria. Não o aplique como migration.

## 4. Instalação

Extrair os 17 arquivos na raiz preservando os caminhos. Sem migration, sem
comando, sem `npm install`, sem reiniciar serviço. Dar **refresh forçado**
(Ctrl+F5) no Admin por causa do cache `-kaz-theme-editor-ad`.

Confirmação sugerida no DevTools do servidor real:
`document.getElementById('kaz-theme-editor').__kaz.ui()` deve devolver o
objeto de estado (não `undefined`); clicar numa aba deve abrir exatamente um
painel; trocar o preset de tela deve mudar `data-kaz-screen-w`.

## 5. Contratos e limitações

`theme_form.twig` com 3 linhas e 2 `|raw`; 14 painéis com `hidden` no
servidor; 53 `name=`; 3 forms; 418 chaves × 3 idiomas; 14 manifestos
idênticos; 10 `PENDING_DECISION`; 11 hashes congelados; `schema.sql`
intacto; `release_approved:false`. Sem `|raw` novo, `on*`, `innerHTML`,
`eval`, `fetch`, `localStorage`, rota nova ou escrita no GitHub por este
pacote. A cadeia `editar → salvar → Publicar → .rms-scene` da AB e a
responsividade da AC seguem verificadas (PROBE e Chromium).

**Observação sobre o HEAD remoto:** a API do GitHub continuou devolvendo
`13b0f7b8475b95b3777bd3a5fc50f48890ae280e` (`pushed_at`
`2026-11-04T19:22:03Z`) durante esta frente. Trabalhamos, portanto, sobre a
réplica local do estado pós-AC que você confirmou ter aplicado — não
assumimos nenhum commit novo. Se o push estiver em outro branch ou tenha
ocorrido depois, os 17 paths continuam LIVRES nos 14 manifestos.

Chromium headless sobre documento local **não é o seu servidor**: não há
sessão autenticada, banco, PHP nativo nem proxy/CDN. Um cache de proxy
servindo o `kaz-theme-editor.js` antigo reproduziria o sintoma mesmo após a
correção — verifique a resposta **200** do asset com o sufixo `-ad`.

Nenhum gate virou `PASS`: F9-27 segue `BLOCKED_NOT_RUN`, BDREditor/F9-103
pausado, P2/F9-18 não reaberto. CI remoto não verificado aqui.
**CI verde ou health check não significa release aprovada.**
