# RELATORIO KZ-28 — Cena david-universe visível: body transparente (canvas fixo z-index:-1)

**Data:** 2026-11-17 · **Base:** HEAD `6396355` (KZ-26 commitado) · **Pacote:** `ReamurCMS-Frente-KZ28-Body-Transparent.zip`

## Sintoma
Depois do KZ-26 o texto aparecia, mas o canvas 3D sumiu: um elemento `position:fixed; z-index:-1`
pinta ABAIXO do fundo em fluxo do `body` — e o `theme.css` do sistema define
`body{background:var(--bg)}` opaco (linha 20), que cobria o canvas.

## Correção
O `<style>` do tema (injetado por `partial/site_header`, depois do CSS do sistema) agora inclui
`body{background:transparent}`. O próprio canvas WebGL da cena já pinta o fundo escuro (#121516),
então a página continua escura e a cena fica atrás de todo o conteúdo — como na referência
https://davidalmeida.xyz/threejs/?lang=pt (canvas fixo fullscreen + fases por cima).

## Arquivos
- `docs/temas/David-Universe-3D.reamurtheme.json` (body transparente + hero fixo; checksum recalculado)
- `tools/upgrade_kz28_body_transparent.sql` (idempotente: cobre o caso de o KZ-26 nao ter aplicado)
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` recalculado

## Aplicar
```bash
git commit -m "fix(theme): body transparente para a cena david-universe aparecer atras do conteudo (KZ-28)"
mysql -u USUARIO -p BANCO < tools/upgrade_kz28_body_transparent.sql
```
Depois recarregue com Ctrl+F5 (cache de CSS). Se ainda nao renderizar, mande: existe `<canvas>`
dentro de `.theme-scene` no F12 e há erro de WebGL/importmap no console?
