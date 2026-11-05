# Frente AL — Aparência e ergonomia visual do KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `914722751cea` (AJ aplicada e auditada).

## Pedido atendido

1. **Barras de rolagem conformes ao tema** — em `image-7.png` as quatro
   barras nativas cinzas eram parte do "atropelo" visual. Agora todas as
   áreas roláveis do editor usam `scrollbar-color` (Firefox) e
   `::-webkit-scrollbar-*` (Chromium/Safari) com `color-mix()` sobre as cores
   efetivas do tema. O JS lê `data-kaz-primary` / `data-kaz-surface` do root
   (já fornecidos pelo controller a partir da config do tema) e **atualiza ao
   vivo** quando os campos de cor são editados. Se o tema publicado expuser
   `--rms-color-primary`, `--rms-color-surface` e `--rms-radius`, esses tokens
   também são honrados. `forced-colors` continua respeitado.
2. **Ajustar o tamanho de todos os elementos** — um seletor `Elementos` com
   quatro níveis escala, em conjunto, fontes, altura de botões, ícones, abas,
   gaps e paddings do chrome. O canvas não escala: o zoom da cena permanece
   independente. O nível `normal` reproduz **exatamente** o CSS anterior.
3. **Expandir / recolher ferramentas** — botões `Ícones`, `Abas`, `Status` e
   `Recolher tudo` na screenbar, com `aria-pressed`, `role="status"` e estado
   em `data-kaz-fold`. A screenbar nunca é recolhida, então sempre há como
   reabrir. Recolher é apenas visual: nenhum `input[name]` sai do formulário.

## Arquitetura

Camada **aditiva**. O controller ganha duas linhas que registram os novos
assets com `?v=...-kaz-theme-editor-aj-al`; as duas linhas originais com
`-aj` permanecem intactas. Consequência: o painel de aceitação da AJ continua
lendo `-aj`, e todos os harnesses A–AJ (1.321 asserções) seguem medindo o
mesmo código. Nenhum arquivo `dev/` da AJ foi modificado.

| Arquivo | Papel |
| --- | --- |
| `dev/admin/controller/design/theme.php` | +2 assets aditivos |
| `dev/admin/view/css/kaz-theme-editor-ergonomia.css` | scrollbars, escala, fold, controles |
| `dev/admin/view/js/kaz-theme-editor-ergonomia.js` | cria controles, lê cores, aplica estado |
| `docs/` (16 arquivos) | seção AL acrescentada, sem reescrita |
| `tools/` | PROBE + verificador Chromium |

Sem `innerHTML`, `eval`, `fetch`, `localStorage`, `|raw` novo, `name=` novo,
chave de tradução, migration, coluna, rota Admin, iframe, `<script>` na prévia
ou alteração de CSP/sandbox. `window.__kazErgo()` existe só como diagnóstico
opcional — **não é necessário abrir o console** para usar a ferramenta.

## Como verificar no Admin

Abra o editor e observe a screenbar: o seletor `Elementos` e os quatro botões
de ferramentas aparecem ao lado de `Layout`. Mude para `amplo` — todos os
controles crescem juntos. Clique em `Recolher tudo` — toolbar, abas e barra de
status somem e a área do canvas aumenta. Clique em `Expandir tudo` para
voltar. Troque a cor `Primária` no módulo de cores: a barra de rolagem muda
com ela.

```bash
php tools/kaz-theme-ergonomia-probe.php
node tools/verify-kaz-ergonomia.cjs
```

## Limites

Preferências **não persistem** entre recarregamentos (estado local
descartável, sem armazenamento por contrato). A escala cobre o chrome da
ferramenta, não o conteúdo do tema publicado. Isto é apresentação: não prova
sessão Admin autenticada, MySQL, deploy, CI remoto nem a aprovação visual do
usuário desde `image-7.png`. `release_approved` continua `false`, F9-27
`BLOCKED_NOT_RUN`. **CI verde, PROBE e Chromium headless não significam
release aprovada.** Nenhuma escrita nossa foi feita no GitHub.

## Frente AM — Ajuste direto na tela (2026-11-05)

**Causa medida em uso real (`image-8.png`):** em desktop o editor abria com
zoom **100%**, porque o `fitZoom()` da AC so era chamado no boot em
`max-width: 980px`. Em 1490×728 o board 960×540 ficava cortado
(`scrollWidth 1032` vs `clientWidth 666`) e as camadas caiam **fora da area
clicavel** — o arraste e o redimensionamento existentes funcionavam, mas
nao havia como alcanca-los. Ajustando manualmente, medimos x 40→192,
y 40→120 e w 560→688, h 220→280.

| O que | Como |
| --- | --- |
| Auto-ajuste universal | `kaz-theme-editor-ajuste.js` chama o botao publico `Ajustar à tela` da AC em **qualquer** viewport, no boot, apos fontes e em `resize`; reduz um passo de zoom ate o board caber de verdade (o `fitZoom` nao contabiliza `margin-top` do frame nem o padding do viewport). |
| Screenbar em 1 linha | A AL inseriu 413 px de controles e, acima de 1280 px, a barra quebrava em 2 linhas (68 px medidos). `flex-wrap: nowrap` + rolagem horizontal devolvem a altura ao canvas sem esconder os controles (eles ficam no inicio). |
| Divisorias redimensionaveis | 2 alcas com `role="separator"`, `aria-valuenow`, arraste por pointer events, teclado (setas) e duplo clique para restaurar. Sem `data-kaz-cols` **nenhuma** largura e sobrescrita: densidade e colapso das frentes AA/AJ continuam intactos. |
| X/Y/W/H ao vivo | `MutationObserver` no SVG acompanha o retangulo selecionado durante o arraste; antes os campos do inspector so atualizavam no `mouseup`. |

Camada **aditiva**: marcador `-aj-am`, dois arquivos novos e duas linhas no
controller. CSS, JS, Twig e paineis de AJ/AL **nao foram alterados**; os
marcadores `-aj` (4 usos) e `-al` permanecem intactos, entao os PROBEs e
harnesses A–AL seguem medindo exatamente o mesmo codigo.

Sem escrita dinamica de marcacao, execucao dinamica, rede, armazenamento,
migracao, coluna, rota Admin, traducao nova, `|raw` novo, `name=` novo ou
alteracao de CSP/sandbox. `release_approved` permanece `false`.
