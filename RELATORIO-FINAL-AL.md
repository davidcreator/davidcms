# Frente AL — Aparência e ergonomia visual · ReamurCMS KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `914722751cea9540d42c6230cdf72d89de655a64`
(AJ aplicada; auditoria 21/21 arquivos AJ byte a byte; tree 3250 / 2666).

## Escopo: somente a parte visual da ferramenta

Tres pedidos, tres entregas, nenhuma alteracao de logica de tema:

| Pedido | Entrega |
| --- | --- |
| Barras de rolagem conformes ao tema | `scrollbar-color` + `::-webkit-scrollbar-*` em todos os containers roláveis; `color-mix()` sobre `--kaz-al-primary` / `--kaz-al-surface`, lidos de `data-kaz-primary` / `data-kaz-surface` (config efetiva do tema) e **atualizados ao vivo** ao editar os campos de cor. `--rms-color-*` / `--rms-radius` também honrados. `forced-colors` preservado. |
| Ajustar o tamanho de todos os elementos | Seletor `Elementos` com 4 níveis (compacto .86 / normal 1 / confortável 1.14 / amplo 1.30). `--kaz-ui` escala **em conjunto** fontes, altura de botões, ícones, abas, gaps e paddings do chrome. O canvas não escala; o zoom da cena é recalculado pelo botão público `Ajustar à tela` da AC. |
| Expandir / recolher ferramentas | Botões `Ícones`, `Abas`, `Status` e `Recolher tudo` / `Expandir tudo` no **início** da screenbar, com `aria-pressed` e `role="status"`. A screenbar nunca é recolhida, então a reabertura está sempre disponível. Recolher é só visual: nenhum `input[name]` sai do formulário. |

### Arquitetura aditiva

O controller ganha **duas linhas** registrando `kaz-theme-editor-ergonomia.css`
e `.js` com o marcador `-aj-al`; as duas linhas originais com `-aj`
permanecem intactas. **O CSS, o JS, o Twig e o painel de aceitação da AJ não
foram alterados.** Consequência: o painel AJ continua lendo `-aj` e todos os
harnesses A–AJ seguem medindo exatamente o mesmo código.

Com `normal` cada `calc()` resolve no valor original do CSS. As regras
responsivas da AC (36/38/28 px) foram replicadas com a mesma escala, e o
padding do viewport é compensado por `1/var(--kaz-ui)` para que o canvas
ajustado por `fitZoom()` continue cabendo — medido: **0 rolagens aninhadas**
em `normal`, `amplo`, `recolhido` e 390 px. Os controles foram movidos para o
início da screenbar porque, no fim, ficavam escondidos pela rolagem
horizontal em 900 px.

DOM puro (`createElement`, `textContent`, `setAttribute`): sem escrita
dinâmica de marcação, execução dinâmica, rede, armazenamento, migração,
coluna, rota Admin, tradução nova, `|raw` novo, `name=` novo ou alteração de
CSP/sandbox. Preferências são estado local descartável, como a densidade da
AA. `window.__kazErgo()` é diagnóstico opcional — **não é preciso abrir o
console**.

| Item | Resultado |
| --- | --- |
| HEAD / tree | `914722751cea` · 3250 / 2666 · 21/21 AJ byte a byte |
| Pacote | 22 arquivos (5 novos) · réplica 2227 → 2232 (17 alterados + 5 novos + 2210 intactos) |
| Guards | 4/4 idênticos · 14 manifestos intocados |
| Sintaxe | 396 PHP / 620 JS-CJS, 0 erros |
| PROBE AL | 100/0 |
| Chromium AL | 56/0 (8 cenários, incluindo 390 px, tema trocado e cor ao vivo) |
| Barras de rolagem | provadas por `getComputedStyle` do motor (`scrollbar-color` e cor do `::-webkit-scrollbar-thumb`) mudando com o tema e ao vivo; o Chromium headless não desenha barras em capturas, então nenhuma imagem as mostra |
| Regressões A–AJ | 1477/0 asserções acumuladas |
| Build | idempotente 5/5 · CRC 32/32 · `sha256sum -c` 61/61 |
| ZIP | 32 entradas · `.sha256` externo |

## Instalação e verificação

Copiar `dev/` e `docs/` sobre a instalação; `tools/` e `evidencia/` são só
validação. **Limpe o cache de CDN/proxy** (os novos assets usam `-aj-al`; o
editor principal continua `-aj`). Abra o editor: no início da barra de tela
aparecem `ELEMENTOS` e os quatro botões. Mude para `amplo`, clique em
`Recolher tudo`, troque a cor `Primária` — as três mudanças são imediatas e
visíveis, sem console.

```bash
php tools/kaz-theme-ergonomia-probe.php
node tools/verify-kaz-ergonomia.cjs
```

## Limites

Preferências **não persistem** entre recarregamentos (sem armazenamento por
contrato). A escala cobre o chrome da ferramenta, não o conteúdo do tema
publicado. Isto é apresentação: não prova sessão Admin autenticada, MySQL,
deploy, CI remoto nem a aprovação visual do usuário desde `image-7.png`.
O painel AJ verde com `-aj` no servidor real foi confirmado nesta sessão,
mas as capturas desktop/390 px do protocolo de aceitação ainda não foram
fornecidas. `release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`,
BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde, PROBE e
Chromium headless não significam release aprovada.** Nenhuma escrita foi
feita no GitHub; CI remoto não foi verificado.
