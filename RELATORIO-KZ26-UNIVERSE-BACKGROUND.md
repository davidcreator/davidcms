# RELATORIO KZ-26 — Cena david-universe como fundo fixo fullscreen atrás do conteúdo

**Data:** 2026-11-17 · **Base:** HEAD `6f03bc1` (KZ-25 aplicado) · **Pacote:** `ReamurCMS-Frente-KZ26-Universe-Background.zip`

## Sintoma (screenshot image-1.png)
Após o KZ-25, a nave renderiza, mas a cena cobre a viewport inteira e fica "à frente" do
conteúdo, com o campo de estrelas uniforme demais — diferente da referência
https://davidalmeida.xyz/threejs/?lang=pt, onde o canvas é um **fundo fixo fullscreen atrás
de todo o conteúdo** e o voo acompanha o scroll da página inteira.

## Causa raiz
A cena `david-universe` é scroll-driven sobre `document.documentElement.scrollHeight`
(voo completo pela página), mas o CSS do tema deixava o slot `hero` como faixa inline
(`.theme-scene--hero{min-height:320px}`) e o `main.du` com `background:var(--du-bg)` sólido.

## Correção (2 trechos de CSS no template `partial/site_header` do tema)
1. `.theme-scene.theme-scene--hero` → `position:fixed;inset:0;z-index:-1;min-height:100vh;margin:0;border:0;border-radius:0;pointer-events:none`
2. `.du` → `background:transparent` (o próprio canvas WebGL já pinta o fundo escuro #121516)

Resultado: canvas atrás de todo o conteúdo (z-index -1 fica acima só do fundo propagado do
root), HUD sticky (z-index 30) e fases com painéis translúcidos por cima — igual à referência.

## Arquivos
- `docs/temas/David-Universe-3D.reamurtheme.json` (CSS corrigido + checksum recalculado)
- `tools/upgrade_kz26_universe_background.sql` (REPLACE no banco: `config` e `published_config` de `rms_design_theme`)
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` (recalculado na mesma frente)

## Como aplicar
```bash
# 1. ZIP sobre a raiz do repo e commit
git commit -m "fix(theme): cena david-universe como fundo fixo fullscreen atras do conteudo (KZ-26)"
# 2. banco (ajuste o prefixo se necessario):
mysql -u USUARIO -p BANCO < tools/upgrade_kz26_universe_background.sql
```
Depois: recarregue a home — a nave/orbes ficam atrás do texto e o scroll voa pelas fases.
