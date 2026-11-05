# Frente AM — Ajuste direto na tela do KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `5fb5eb2f553d` (AL aplicada, 22/22).

## O que estava impedindo

`image-8.png` mostrava os controles da AL no lugar e as barras de rolagem
azuis do tema, mas o usuario relatou: *"ainda nao consigo mexer para ajustar
coisas na tela"*. Medimos no Chromium em 1490×728:

| Sintoma | Medicao |
| --- | --- |
| Zoom inicial | **100%** (deveria caber) |
| Board cortado | `scrollWidth 1032` vs `clientWidth 666`; `scrollHeight 648` vs `clientHeight 289` |
| Camadas inalcancaveis | alvo do clique em `x=1050, y=806`, fora da janela de 1490×728 |
| Screenbar | **68 px** (2 linhas) em vez de ~38 px |
| Apos `Ajustar à tela` manual | zoom 40%, ainda `scrollHeight 324` vs `clientHeight 289` |

A causa esta no proprio codigo da AC:

```js
if (window.matchMedia('(max-width: 980px)').matches && ...) fitZoom();
```

O auto-ajuste so existia **no mobile**. Em desktop o zoom ficava em `1`, o
board 960×540 nao cabia e as camadas ficavam fora da area clicavel. O arraste
e o resize **ja funcionavam**: depois do ajuste manual medimos
`x 40→192, y 40→120` e `w 560→688, h 220→280`. O problema nao era a
interacao, era nao conseguir alcanca-la.

## Entrega

1. **Auto-ajuste universal** — o companion chama o botao publico
   `Ajustar à tela` no boot (duas passagens: layout e fontes), em `resize` e
   apos redimensionar colunas. Como `fitZoom()` ignora `margin-top` do frame
   e o padding do viewport, reduzimos um passo de zoom ate `overflow <= 4px`
   (maximo 3). Resultado medido: zoom 100% → 25%, `overflow 0`.
2. **Screenbar em 1 linha no desktop** — `flex-wrap: nowrap` +
   `overflow-x: auto` com altura maxima escalada por `--kaz-ui`. Os controles
   continuam no inicio da barra, visiveis sem rolagem.
3. **Divisorias redimensionaveis** — duas alcas entre as 3 colunas, com
   `role="separator"`, `aria-valuenow`, pointer events (mouse, touch, caneta),
   setas do teclado e duplo clique para restaurar. Limites 160–420 px.
   Enquanto o usuario nao arrasta, `data-kaz-cols` nao existe e **nenhuma**
   regra de largura e sobrescrita: densidade (`compact/normal/wide`) e
   colapso (`left/right`) das frentes AA/AJ seguem valendo.
4. **X/Y/W/H ao vivo** — `MutationObserver` no SVG atualiza os campos do
   inspector durante o arraste. Antes eles so mudavam no `mouseup`.

## Arquitetura

Camada aditiva com marcador proprio `-aj-am`. O controller ganha 2 linhas;
CSS, JS, Twig e os paineis de aceitacao/liberacao de AJ/AL nao foram
alterados. Os 4 usos de `$kazAssetAJ` e o marcador `-al` permanecem
intactos, preservando os PROBEs das frentes anteriores.

| Arquivo | Papel |
| --- | --- |
| `dev/admin/controller/design/theme.php` | +2 assets aditivos |
| `dev/admin/view/css/kaz-theme-editor-ajuste.css` | 1 linha, divisorias, alcas |
| `dev/admin/view/js/kaz-theme-editor-ajuste.js` | auto-fit, alcas, inspector ao vivo |
| `docs/` (18 arquivos) | secao AM, sem reescrita |
| `tools/` | PROBE + verificador Chromium |

Sem escrita dinamica de marcacao, execucao dinamica, rede, armazenamento,
migracao, coluna, rota Admin, traducao nova, `|raw` novo, `name=` novo ou
alteracao de CSP. `window.__kazAjuste()` e diagnostico opcional — **nao e
necessario abrir o console** para mexer na tela.

## Como verificar no Admin

Abra o editor em desktop: o board deve **caber inteiro** sem rolagem, com as
camadas alcancaveis. Clique e arraste uma camada — X/Y mudam no inspector
**enquanto** voce arrasta. Arraste a alca azul no canto para redimensionar —
W/H acompanham. Arraste a divisoria entre as colunas para dar mais espaco ao
canvas; duplo clique restaura. Reduza a janela: o zoom se reajusta sozinho.

```bash
php tools/kaz-theme-ajuste-probe.php
node tools/verify-kaz-ajuste.cjs
```

## Limites

Larguras das colunas **nao persistem** entre recarregamentos (sem
armazenamento por contrato). O auto-ajuste usa o botao publico da AC: se o
tema escolhido for maior que 3840×2160, o zoom minimo de 25% pode ainda
exigir rolagem — isso e esperado. Isto e interacao/apresentacao: nao prova
sessao Admin autenticada, MySQL, deploy, CI remoto nem a aprovacao visual
do usuario desde `image-7.png`. `release_approved` continua `false`, F9-27
`BLOCKED_NOT_RUN`. **CI verde, PROBE e Chromium headless não significam
release aprovada.** Nenhuma escrita nossa foi feita no GitHub.
