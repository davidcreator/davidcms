# RELATORIO KZ-33 — JOW-payments e Dracaunos-Security elaboradas (cadastro completo)

**Data:** 2026-11-17 · **Base:** HEAD `453da06` · **Pacote:** `ReamurCMS-Frente-KZ33-Ferramentas-Novas.zip`

## O que foi feito
1. **Banco** (`tools/upgrade_kz33_novas_ferramentas.sql`, idempotente):
   - `rms_tool`: 2 linhas (vendor `JOW` / `Dracaunos`, url placeholder, `status=1` → publicadas)
   - `rms_tool_description`: nome + descrição nos 3 idiomas (1=en-us, 2=pt-br, 3=es-eu)
2. **Repo**: `internal-tools.json` com as notas das duas ferramentas (versão 1.0.0, 2026-11-17) —
   aparece na seção de ferramentas internas da página Ferramentas.
3. CHANGELOG + CHECKSUMS recalculado.

## Aplicar
```bash
git commit -m "feat(tools): elabora JOW-payments e Dracaunos-Security (cadastro 3 idiomas, publicadas) (KZ-33)"
mysql -u USUARIO -p davidcms < tools/upgrade_kz33_novas_ferramentas.sql
```
Depois: Admin → Ferramentas (as duas aparecem publicadas com descrição por idioma) e a home pública
na seção ferramentas (tema David Universe 3D, cena tools-3d).
