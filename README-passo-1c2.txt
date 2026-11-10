Passo 1C.2 — Limpeza de docs/ historicos, dev/tests/ e scripts obsoletos em tools/e2e/
======================================================================================

O que este script faz (sem depender de python3):
1. Remove 256 arquivos obsoletos (~2,25 MB):
   - 175 arquivos historicos/logs de fase em docs/ (incluindo docs/FRENTES-INTEGRAL.md
     e docs/temas/preview*.*), mantendo todos os guias ativos, schemas de tema,
     contratos congelados (decision-matrix.json), referencias do CHECKSUMS.txt,
     evidencias (decision-evidence.json) e todos os links internos de Markdown.
   - 71 scripts obsoletos das Fases 2 a 9 em tools/e2e/ (48 .sh, 6 _contracts.php,
     1 e2e_f805_browser.js, 6 _dom.cjs, 8 checkers F8 e2e_f8*.cjs, e2e_f910.cjs e
     check_preview_import.cjs que apontava para preview/index.html ja removido),
     mantendo os 6 runners Playwright/Three.js, os 6 check_*.cjs restantes,
     os 13 e2e_bdre_*_static.cjs e os 22 checkers F9 ativos.
   - 10 arquivos da Fase 2 em dev/tests/.
2. Atualiza tools/checkers-inventory.json via Node.js removendo as 8 entradas da F8.
3. Executa todos os guardas locais (deps-layout, checksums, ci-workflow, verify-f11-01,
   repo-health) com validacao de sintaxe em .sh, .js e .cjs.

Como executar (no Git Bash, na raiz do repositorio, ex.: /e/wamp64/www/davidcms):
  unzip -o passo-1c2-limpeza-docs-e2e.zip
  bash passo-1c2-limpeza-docs-e2e.sh

Depois de validar o "git status":
  git add -A
  git commit -m "chore: remove obsolete F2-F9 e2e scripts, dev/tests, and historical docs (Step 1C.2)"
  git push origin main
