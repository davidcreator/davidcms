# RELATORIO KZ-30 — Sincronização completa do tema David Universe 3D (banco + código)

**Data:** 2026-11-17 · **Pacote:** `ReamurCMS-Frente-KZ30-Sync-Completo.zip`

## Por que os patches anteriores nao seguraram
O runtime le `published_config` do tema ativo (`Model\Common\Theme::getThemeMap('published')`),
e o conteudo instalado no banco divergia do JSON do repo — os REPLACE por trecho nao casavam.

## O que este pacote entrega
1. `tools/upgrade_kz30_overwrite_theme.sql` — sobrescreve `config`, `published_config` E
   `draft_config` do tema `david_universe_3d` (status=1) com o config corrigido do repo:
   cena `david-universe` na home, slot `hero` fixo fullscreen (`z-index:-1`) atrás do conteúdo,
   `body{background:transparent}`, paths SVG sadios, checksum do pacote recalculado.
2. `dev/system/library/three/runtime/three/scenes/david-universe.js` — KZ-29: `scene.background`
   opaco (#121516) para o fallback CSS não vazar pelo canvas (renderer nasce com alpha:true).
3. `docs/temas/David-Universe-3D.reamurtheme.json` — mesma fonte do SQL.
4. `docs/CHANGELOG.md` + `CHECKSUMS.txt` recalculado.

## Aplicar
```bash
# 1. código
git commit -m "fix(three): sincroniza tema David Universe 3D — cena opaca, hero fixo atras do conteudo (KZ-30)"
# 2. banco (coluna inteira, sem REPLACE frágil):
mysql -u USUARIO -p davidcms < tools/upgrade_kz30_overwrite_theme.sql
# 3. Ctrl+F5 na home
```

## Verificação esperada
- Console sem erros de `<path>` e sem erro de WebGL/importmap
- `.theme-scene--hero` fixo fullscreen atrás do conteúdo; nave/orbes/estrelas visíveis;
  scroll faz a câmera voar pelas fases (como em davidalmeida.xyz/threejs/)
