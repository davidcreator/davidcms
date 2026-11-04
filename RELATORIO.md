# ReamurCMS — Frente AE · Aderência ao `main`, Fail-loud e Diagnóstico

> **Pela primeira vez validamos o repositório real, não uma réplica.**
> E, antes de empilhar mais funcionalidade, entregamos o instrumento que
> responde à pergunta que ficou em aberto desde a frente A: *o editor está
> realmente vivo no seu servidor?* **Sem migration.**

| | |
| --- | --- |
| Base | `main` @ `56fd0c5d40fb…` (AD) + `50d4844f66d9…` (AC), `pushed_at=2026-11-04T20:06:35Z` |
| Árvore real | 3.194 entradas / 2.611 blobs / `truncated:false` |
| **Aderência** | **19/19 arquivos AC+AD byte a byte idênticos ao `main`**, 0 divergentes, 0 ausentes |
| Arquivos | **16** completos (12 substituições + 4 novos) + 5 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Réplica | 2206 → 2210 arquivos (12 alterados + 4 novos + 2194 intactos) |
| Harnesses | health 26/0 · boot 22/0 · Chromium defer 251/0 · Chromium head-real 251/0 · layout 90/0 · DOM 226/0 · PROBE 93/0 · alcance 5/0 = **964/0** |
| Validação final | 151 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos |
| Sintaxe | 388 PHP 0 erros · 613 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 23/23 · `sha256sum -c` 55/55 |

---

## 1. O que mudou nesta frente

As frentes AC e AD foram escritas sobre **réplicas locais** porque a API
`commits/main` devolvia sempre `13b0f7b8475b…`. Uma consulta com
`per_page=5` e `nocache` novo revelou a cadeia completa:

| Commit | Data (UTC) | Mensagem | Frente |
| --- | --- | --- | --- |
| `56fd0c5d40fb…` | 20:06:31 | `fix(admin/theme-editor): fix silent theme editor boot failure` | AD |
| `50d4844f66d9…` | 19:46:39 | `fix(admin/theme-editor): correct browser layout issues from AB release` | AC |
| `13b0f7b8475b…` | 19:21:59 | `feat: add published theme scene and fix admin UI` | AB |

Baixamos o conteúdo cru de `56fd0c5d40fb` e comparamos com os ZIPs entregues:
**19/19 IGUAL** — `theme.php` (70.109), `kaz-theme-editor.js` (47.669),
`header.twig` (6.089), `document.php` (2.476), CSS (34.897), as ferramentas e
os 10 documentos. Os 405 paths presentes no `main` e ausentes no recorte são
227 `index.html` + 178 binários (fontes, imagens, sourcemaps) que o download
filtra. **Nenhum arquivo de migration novo; `schema.sql` intacto.** Detalhes
em `docs/RELATORIO-ADERENCIA-main.md`.

## 2. Por que não é "mais uma funcionalidade"

Desde a frente A, a confirmação recebida foi sempre *"aplicado com sucesso,
github atualizado"* — nunca *"as ferramentas agora respondem no meu
servidor"*. E o sintoma que custou quatro frentes de diagnóstico era o
**silêncio**: ferramentas inertes com console limpo. A AD corrigiu a causa
(`<script>` no `<head>` sem `defer` → `return` silencioso), mas não deixou
nenhum sinal caso o problema volte por Twig compilado em cache, CDN servindo
JS antigo, CSP ou asset 404.

Entregar nova funcionalidade agora seria empilhá-la sobre um editor cujo
funcionamento em produção ainda não foi confirmado. A AE fecha esse ciclo.

## 3. As três entregas

1. **Fail-loud** — `bootDone` passa a ser marcado **depois** de confirmar o
   root (assim `DOMContentLoaded` e `load` podem tentar de novo). Se, após
   500 ms, o boot não ocorreu e a página é a do editor, um aviso
   `role="alert"` é inserido com `createElement` + `textContent`, dizendo
   exatamente o que verificar. Sem marcação interpolada, rede, execução
   dinâmica de código ou exceção. `evidencia/AE_fail_loud.png` mostra o
   resultado; `AE_antes_silencioso.png` mostra o estado anterior.
2. **`data-kaz-health="ad+ae"`** no root, para o diagnóstico identificar a
   versão viva do JS. Cache `-kaz-theme-editor-ad` → **`-kaz-theme-editor-ae`**.
3. **`tools/kaz-theme-editor-health.js`** — snippet **somente leitura** para
   colar no console do DevTools na página de temas. Devolve um JSON com
   veredito (`OK` / `FALHA_ASSET` / `FALHA_BOOT` / `OK_COM_RESSALVAS`),
   assets e versões, `data-kaz-ready`, `__kaz`, os 9 módulos, medidas reais
   de layout, CSRF/form e o aviso fail-loud. `copy(__kazHealth())` copia o
   relatório. Instruções em
   `docs/KAZTheme-Editor-Diagnostico-em-Producao.md`.

**`tools/verify-kaz-health.cjs`** prova, em Chromium e em 4 documentos, que o
health distingue o estado quebrado (`FALHA_BOOT`, `__kaz` ausente, `SEM_DEFER`)
do corrigido (`OK`), que a AE inicializa **mesmo sem `defer`** (nesse caso
`OK_COM_RESSALVAS`, não falha) e que o aviso aparece **somente** quando o boot
falha. 26/0.

## 4. Banco de dados

**Nenhuma migration.** A auditoria do `main` confirmou que não foi adicionado
nenhum `.sql` além do diagnóstico somente-leitura da AD. `kaz_scene` segue
dentro de `draft_config`/`published_config`, colunas preexistentes, com
ausência tratada por `ThemePackage::normalizeScene(null)`.

## 5. Instalação e como usar

Extrair os 16 arquivos na raiz, sem comando nem migration. **Ctrl+F5** no
Admin (cache `-kaz-theme-editor-ae`). Depois, na página de edição de tema:
abrir DevTools → Console → colar `tools/kaz-theme-editor-health.js` →
`copy(__kazHealth())` → enviar o JSON.

| Veredito | Significado |
| --- | --- |
| `OK` | boot, API, 9 módulos, CSS e CSRF presentes |
| `FALHA_ASSET` | `kaz-theme-editor.js` não referenciado |
| `FALHA_BOOT` | asset carregou, mas `__kaz` não existe — checar HTTP 200, CDN, CSP |
| `OK_COM_RESSALVAS` | funciona; ler `problemas[]` (ex.: script sem `defer`) |

## 6. Contratos e limitações

`theme_form.twig` com 3 linhas e 2 `|raw`; 14 painéis com `hidden`; 53
`name=`; 418 chaves × 3 idiomas; 14 manifestos idênticos; 10
`PENDING_DECISION`; 11 hashes congelados; `schema.sql` intacto;
`release_approved:false`. Sem `|raw` novo, `on*`, marcação interpolada,
execução dinâmica de código, `fetch`, `localStorage`, rota nova ou escrita no
GitHub por este pacote.

**O limite continua sendo o mais importante:** Chromium headless local não é
o seu servidor e a aderência ao repositório não prova execução correta em
produção. O veredito que importa é o que `__kazHealth()` devolver no Admin
real. Só com ele confirmado faz sentido avançar para a próxima frente
funcional — retomando o objetivo declarado de fazer o editor produzir
**config, CSS e templates reais que o catálogo renderiza**, com prova ponta
a ponta no seu servidor.

Nenhum gate virou `PASS`: F9-27 segue `BLOCKED_NOT_RUN`, BDREditor/F9-103
pausado, P2/F9-18 não reaberto. CI remoto não verificado aqui.
**CI verde ou health check local não significa release aprovada.**
