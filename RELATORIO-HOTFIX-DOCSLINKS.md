# Hotfix docs-links — o próprio texto do hotfix reconduziu o link externo no CHANGELOG

**Data:** 2026-11-18 · **Pacote:** `ReamurCMS-Frente-Hotfix-DocsLinks.zip`

O commit anterior (d57301e) corrigiu o link na entrada KZ-25, mas a nova entrada do hotfix
citava `https://davidalmeida.xyz/threejs/` dentro de backticks — e o lychee extrai URLs também
de code spans. Esta frente reescreve essa linha sem o URL e recalcula o CHECKSUMS.

## Aplicar
```bash
git commit -m "fix(docs): remove URL externa do texto do hotfix no CHANGELOG (docs-links)"
```
