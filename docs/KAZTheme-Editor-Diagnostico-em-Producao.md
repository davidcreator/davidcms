# KAZTheme-Editor — Diagnóstico em Produção (Frente AE)

Este documento é para **você**, no seu servidor. Responde à pergunta que as
frentes A–AD não conseguiam responder: *"o editor está realmente vivo aqui?"*.

## Por que isto existe

Você relatou: **"nenhuma ferramenta responde e não existe erro no console do
DevTools"**. A causa foi um `return` silencioso no boot (Frente AD), já
corrigido. Mas, se o problema voltar por cache de Twig, CDN, CSP ou asset
404, o sintoma seria outra vez o silêncio. A Frente AE faz duas coisas:

- **Fail-loud:** se o JS não inicializar, aparece um aviso vermelho na própria
  página, dizendo o que verificar. Console limpo deixa de ser sinônimo de
  "está tudo bem".
- **Health check:** um snippet de leitura que você executa no console e que
  devolve um JSON com o veredito.

## Passo a passo

1. Aplique o pacote AE (2 arquivos de código + ferramentas + docs), extraia na
   raiz e dê **Ctrl+F5** no Admin — o cache do asset mudou para
   `-kaz-theme-editor-ae`.
2. Abra a página de edição de tema (`Admin → Design → Temas → editar`).
3. Abra o DevTools (F12) → aba **Console**.
4. Abra `tools/kaz-theme-editor-health.js`, copie todo o conteúdo, cole no
   console e pressione Enter. O relatório é impresso na hora.
5. Para me enviar: digite `copy(__kazHealth())` e cole o JSON na resposta.

## Como ler o veredito

| `veredito` | Significado | O que checar |
| --- | --- | --- |
| `OK` | boot, API, 9 módulos, CSS e CSRF presentes | nada — pode seguir para a próxima frente |
| `FALHA_ASSET_OU_DEFER` | JS ausente ou sem `defer` | `header.twig` ativo contém o loop `scripts_deferred`? O `theme.php` usa `addScriptDeferred`? Há Twig compilado em cache? |
| `FALHA_BOOT` | asset carregou, mas `__kaz` não existe | Network: `kaz-theme-editor.js?v=…-kaz-theme-editor-ae` é **200** e não é HTML de erro? Há CDN servindo versão antiga? CSP bloqueia? |
| `OK_COM_RESSALVAS` | funciona, mas há ressalvas em `problemas[]` | ler a lista (ex.: CSRF ausente, overflow horizontal) |

O campo `problemas[]` usa prefixos estáveis: `SEM_ROOT`, `SEM_ASSET_JS`,
`SEM_ASSET_CSS`, `ASSET_ANTIGO`, `SEM_DEFER`, `SEM_BOOT`, `SEM_API`,
`AVISO_VISIVEL`, `SEM_CSRF`, `SEM_FORM`, `LAYOUT_CENTRO_ESTREITO`,
`OVERFLOW_HORIZONTAL`.

## O que o JSON contém

- `assets.js[].version` — deve terminar em `-kaz-theme-editor-ae`.
- `boot.ready` — deve ser `"1"`; `boot.api` deve ser `"object"`.
- `boot.layers` — número de camadas da cena (≥6 no tema de exemplo).
- `modulos` — os 9 módulos, cada um com `root`, `asset`, `version` e `defer`.
- `layout` — medidas reais de `.kaz-shell`, `.kaz-center`, stage, viewport,
  `scrollsAninhados` e `overflowHorizontal`.
- `publicacao` — presença de CSRF, `#kaz-theme-form` e do bloco Publicar.

## Segurança

O snippet é **somente leitura**: não faz requisição, não escreve no DOM, não
usa `innerHTML`, `eval`, `localStorage` nem `document.cookie`. Não faz parte
do runtime do CMS — se não for executado, nada muda. O fail-loud do editor,
por sua vez, usa apenas `createElement` + `textContent`.

## E se o veredito for `OK`?

Aí sim sabemos que a AD funcionou em produção, e a próxima frente pode voltar
a avançar no objetivo declarado: **fazer o editor produzir config, CSS e
templates reais que o catálogo renderiza**, com prova ponta a ponta no seu
servidor — em vez de empilhar funcionalidade sobre um editor que não inicializa.
