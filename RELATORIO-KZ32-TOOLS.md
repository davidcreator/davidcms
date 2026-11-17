# RELATORIO KZ-32 — Versões das ferramentas internas + 2 ferramentas novas

**Data:** 2026-11-17 · **Base:** HEAD `453da06` · **Pacote:** `ReamurCMS-Frente-KZ32-Tools-Versoes.zip`

## Fonte de verdade
`dev/admin/model/tool/internal-tools.json` (Frente AY) — a página **Ferramentas** do admin lê esse
arquivo (sem banco). Versão, `updated_at` e até 5 notas por ferramenta são exibidos no `tool_list.twig`.

## Mudanças
| Ferramenta | Antes | Depois | Justificativa |
| --- | --- | --- | --- |
| BDREditor-Content | 1.0.0 (2026-11-06) | **1.0.1** (2026-11-17) | F918-06 P1 resolvido (block_html como Twig\Markup), regressões cobertas, CI 7/7 verde |
| KAZEditor-Theme | 1.0.0 (2026-11-06) | **2.0.0** (2026-11-17) | KZ-23/KZ-24 (tema em atributos + quebra de linha), KZ-25..KZ-31 (David Universe 3D / ThreeJS nativo fullscreen), fixes de ruído visual e bugs reais no controller |
| JOW-payments | — | **1.0.0** (2026-11-17) | ferramenta nova (id `jow-payments`, vendor JOW) |
| Dracaunos-Security | — | **1.0.0** (2026-11-17) | ferramenta nova (id `dracaunos-security`, vendor Dracaunos) |

Validado contra as regras do controller: ids `^[a-z0-9][a-z0-9-]{0,63}$`, nome ≤128, versão ≤32,
data `YYYY-MM-DD`, ≤5 notas de ≤255 chars. `internal-tools.json` não está no CHECKSUMS.txt;
só o CHANGELOG entrou no manifesto (recalculado).

## Aplicar
```bash
git commit -m "chore(tools): versoes 1.0.1/2.0.0 e novas ferramentas JOW-payments e Dracaunos-Security (KZ-32)"
```
Só arquivo do repo — sem banco. Depois abra Admin → Ferramentas para conferir.
