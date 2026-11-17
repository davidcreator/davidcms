# RELATORIO KZ-31 — Sem ruído visual: fallback pontilhado desligado no hero + fase 00 transparente

**Data:** 2026-11-17 · **Pacote:** `ReamurCMS-Frente-KZ31-Sem-Ruido.zip`

## Sintomas (screenshots)
1. Pontos pontilhados por toda a área da cena → é o `.theme-scene__fallback` (CSS) do slot `hero`
   (fixo fullscreen), que continuava visível junto do canvas.
2. Caixa da fase 00 com preenchimento escuro sólido → `.du-hero` tinha `background: ... var(--du-bg)`,
   que escondia a cena atrás do conteúdo (na referência não existe esse preenchimento).

## Correção (CSS do tema, template `partial/site_header`)
- `.theme-scene--hero{background:transparent}` e `.theme-scene--hero .theme-scene__fallback{display:none}`
- `.du-hero` sem o `var(--du-bg)` sólido (só os brilhos radiais translúcidos permanecem)

## Aplicar
```bash
git commit -m "fix(theme): remove fallback pontilhado do hero e preenchimento solido da fase 00 (KZ-31)"
mysql -u USUARIO -p davidcms < tools/upgrade_kz31_overwrite_theme.sql   # sobrescreve config/published/draft
# Ctrl+F5
```
