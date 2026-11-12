# ADR 0083 — KAZEditor-Theme: canvas com a página real do rascunho

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-01, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | O KAZEditor-Theme abria em um board SVG de 960 × 540 (contrato `kaztheme-scene/v1`) que nenhum tema entregue exibe: os pacotes sobrescrevem `common/home` e não incluem `partial/theme-scene-block`. O tema de verdade vivia em 14 módulos de formulário e 25 fontes Twig em `textarea`, e a prévia existente renderiza **uma fonte isolada**, com `header`/`footer` vazios e listas vazias — a prévia de `common/home` de um tema completo saía quase em branco. Quem monta um tema não via o resultado do que editava. |

## Direção escolhida pelo responsável

Caminho **B**: um editor de desenho livre (quadros, auto layout, componentes) que **gera** o tema em Twig e CSS, usável por quem não programa; o código passa a ser um modo avançado. A tela do editor pode ser reorganizada; **os dados salvos do tema continuam compatíveis**.

Esta ADR decide apenas a **primeira etapa**, que é pré-requisito de todas as outras: **ver a página real do rascunho dentro do editor**. Sem uma superfície fiel não há como desenhar sobre ela nem conferir o que o desenho gerou.

## Decisão

1. **A vista de abertura do editor é o módulo "Página"**: a página inteira do rascunho (cabeçalho do tema + rota + rodapé) em três quadros lado a lado — desktop 1440, tablet 768, celular 390 — sobre um canvas com zoom contínuo (5 %–400 %) e deslocamento livre por roda, arrasto, pinça e teclado. O board SVG continua existindo, sem mudança de contrato, como módulo **"Cena decorativa"**.

2. **Render no servidor, nunca no navegador.** Nova ação `design/theme.canvas` (`POST` + CSRF + ACL `access`, somente leitura). O corpo do `POST` é o próprio formulário do tema, validado pelo `buildDraftConfig()` já existente: o canvas mostra o que está na tela **antes de salvar**. Formulário inválido: mostra o último rascunho salvo e avisa. Nada é gravado, nenhuma tabela, coluna, migration ou chave de configuração nova.

3. **`Twig::renderThemePage()`** (aditivo em `system/library/template/twig.php`): monta `partial/site_header` + rota + `common/footer` com **todas** as fontes do tema no loader, sobre os templates padrão do Catalog — a mesma precedência do site publicado. Mantém as barreiras da ADR 0080: allowlist de 25 chaves, limite de 131072 bytes, padrões proibidos (`<script`, `on…=`, `javascript:`, `|raw`, `autoescape`, `srcdoc`, `formaction`), **Twig Sandbox** e cache desligado. A política do sandbox é a de `renderSandboxPreview()` mais os filtros `slice` e `nl2br`, que os templates padrão do Catalog usam. Objetos (inclusive `Twig\Markup`) são removidos do contexto.

4. **Uma fonte quebrada não apaga a página.** Cada fonte do tema é compilada sozinha; a que falha (sintaxe ou política do sandbox) sai do loader, o template padrão ocupa o lugar e o erro volta com template e linha. O editor mostra o aviso acima do canvas.

5. **Dados de amostra em um único lugar:** `Theme\ThemeCanvasSample` (pura: sem banco, sessão, request, rede ou escrita). Listas preenchidas, imagens em data URI SVG determinístico e textos lidos dos arquivos de idioma do Catalog por caminho fixo. As rotas saem de `ThemePackage::TEMPLATE_KEYS` (menos `partial/*` e `common/footer`); não há segunda allowlist. Nenhum dado real do site entra na prévia.

6. **Isolamento no navegador.** O documento devolvido carrega CSP `default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:`. Cada quadro é um `<iframe sandbox="allow-same-origin">` — **sem** `allow-scripts`, `allow-forms`, `allow-popups` ou `allow-top-navigation` — coberto por uma camada que recebe os gestos: a página não é clicável.

7. **Desacoplamento.** `kaz-canvas.js`/`kaz-canvas.css` são arquivos novos. `kaz-theme-editor.js` muda em quatro pontos: registra o módulo, publica `data-kaz-active-module` + evento `kaz:module`, abre em "Página" e restringe os atalhos de cena ao módulo da cena.

## Desvio consciente da ADR 0080

A ADR 0080 pede "iframe sem permissões" (`sandbox=""`). O canvas usa `sandbox="allow-same-origin"`. Motivo: para mostrar a página **inteira** o editor precisa medir a altura do documento, o que é impossível em origem opaca. Sem `allow-scripts` nenhum código do quadro executa, então a mesma origem não dá ao conteúdo nenhuma capacidade nova; a CSP `default-src 'none'` e a rejeição de `<script`/handlers no servidor são barreiras independentes. A prévia antiga (`design/theme.preview` e o comparador) **continua com `sandbox=""`**. A mesma origem também é o que permitirá, na etapa seguinte, apontar um elemento da página e descobrir de qual fonte ele veio.

## Alternativas consideradas

1. **Renderizar no navegador** (Twig em JS ou reconstrução do DOM) — rejeitado: seria uma segunda implementação do renderer, divergente do Catalog e fora do sandbox.
2. **Reaproveitar `renderSandboxPreview()`** — rejeitado: ela carrega só a fonte pedida; os `include` cairiam nos templates padrão e a página mostrada não seria a do tema.
3. **Reaproveitar `renderCatalogPagePreview()`** — rejeitado: não usa sandbox e marca HTML como confiável; serve à prévia de página CMS com tema **publicado**, não a fonte de rascunho ainda não revisada.
4. **`sandbox=""` com altura fixa e rolagem interna** — rejeitado: três quadros com rolagens independentes não mostram a página inteira nem permitem comparar larguras.
5. **Trocar o board SVG pelo canvas novo** — adiado: a cena `kaztheme-scene/v1` é dado salvo; a substituição depende do contrato de cena v2 (etapa de desenho).

## Limites conhecidos

- Cenas Three.js não executam (não há script): aparece o fallback estático.
- Unidades `vh` são medidas com a altura do aparelho antes de o quadro ser esticado; depois disso elas passam a valer sobre a altura do quadro. Os temas entregues não usam `vh`; `theme.css` usa uma vez (`60vh`).
- Os dados são de amostra: contagens e textos reais do site não aparecem.
- Condições, agendamento e sites são resolvidos com o contexto padrão (`desktop`, sem variante). O comparador (módulo Prévia) continua sendo o lugar para simular esses eixos.
- S2 do relatório de análise (`setThemeTemplates()` renderiza o tema **publicado** sem sandbox) **não** é tratado aqui e continua aberto.

## Consequências

- Arquivos: 3 novos de código (`theme_canvas_sample.php`, `kaz-canvas.js`, `kaz-canvas.css`), 1 teste novo (`KazCanvasPageTest`), 7 alterados (controller, `twig.php`, template do editor, `kaz-theme-editor.js`, 3 idiomas).
- 35 chaves de idioma novas (`text_canvas*`) em `en-us`, `pt-br` e `es-eu`.
- Correção incidental: os atalhos de cena (`Backspace`, `Delete`, `R`, `T`…) agiam com qualquer módulo aberto e podiam apagar ou criar camadas sem a cena estar visível.

## Próximas etapas (fora desta ADR)

1. **Apontar e editar:** clicar em um elemento da página seleciona a seção/fonte que o gerou; painel lateral com as propriedades daquele trecho; módulos avançados (sites, agendamento, condições) agrupados em Configurações.
2. **Tokens ao vivo:** cores, tipografia e espaçamentos editados ao lado da página, com resultado imediato.
3. **Contrato de cena v2:** árvore de quadros com auto layout, restrições e componentes, com compilador determinístico para Twig + CSS e ligação declarativa a dados (`projects`, `posts`…).
4. **Ferramentas de desenho** sobre o canvas (seleção múltipla, guias, alinhamento, texto inline, copiar/colar) e modo código como visão avançada do mesmo documento.
