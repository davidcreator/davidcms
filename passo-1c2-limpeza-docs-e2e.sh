#!/usr/bin/env bash
set -euo pipefail

if [ ! -d .git ] || [ ! -f tools/verify-checksums.sh ]; then
  echo "ERRO: execute este script na RAIZ do repositorio davidcms." >&2
  exit 1
fi

echo "== Passo 1C.2: Limpeza de docs/ historicos, dev/tests/ e scripts obsoletos em tools/e2e/ =="

FILES_TO_REMOVE=(
  'tools/e2e/check_preview_import.cjs'
  'tools/e2e/e2e_f217.sh'
  'tools/e2e/e2e_f301.sh'
  'tools/e2e/e2e_f302.sh'
  'tools/e2e/e2e_f303.sh'
  'tools/e2e/e2e_f304.sh'
  'tools/e2e/e2e_f305.sh'
  'tools/e2e/e2e_f401.sh'
  'tools/e2e/e2e_f402.sh'
  'tools/e2e/e2e_f403.sh'
  'tools/e2e/e2e_f404.sh'
  'tools/e2e/e2e_f405.sh'
  'tools/e2e/e2e_f406.sh'
  'tools/e2e/e2e_f604.sh'
  'tools/e2e/e2e_f606.sh'
  'tools/e2e/e2e_f701.sh'
  'tools/e2e/e2e_f702.sh'
  'tools/e2e/e2e_f703.sh'
  'tools/e2e/e2e_f805.cjs'
  'tools/e2e/e2e_f805.sh'
  'tools/e2e/e2e_f805_a01_blog_media.sh'
  'tools/e2e/e2e_f805_a01_blog_media_dom.cjs'
  'tools/e2e/e2e_f805_a02_ckeditor.sh'
  'tools/e2e/e2e_f805_a02_ckeditor_dom.cjs'
  'tools/e2e/e2e_f805_blog_media.sh'
  'tools/e2e/e2e_f805_browser.js'
  'tools/e2e/e2e_f806.sh'
  'tools/e2e/e2e_f806_contracts.php'
  'tools/e2e/e2e_f806_dom.cjs'
  'tools/e2e/e2e_f806_http.cjs'
  'tools/e2e/e2e_f807.sh'
  'tools/e2e/e2e_f807_contracts.php'
  'tools/e2e/e2e_f807_dom.cjs'
  'tools/e2e/e2e_f807_http.cjs'
  'tools/e2e/e2e_f808.sh'
  'tools/e2e/e2e_f808_contracts.php'
  'tools/e2e/e2e_f808_dom.cjs'
  'tools/e2e/e2e_f808_http.cjs'
  'tools/e2e/e2e_f809.sh'
  'tools/e2e/e2e_f809_contracts.php'
  'tools/e2e/e2e_f809_dom.cjs'
  'tools/e2e/e2e_f809_http.cjs'
  'tools/e2e/e2e_f810.sh'
  'tools/e2e/e2e_f810_contracts.php'
  'tools/e2e/e2e_f810_http.cjs'
  'tools/e2e/e2e_f811.sh'
  'tools/e2e/e2e_f811_contracts.php'
  'tools/e2e/e2e_f811_http.cjs'
  'tools/e2e/e2e_f812.cjs'
  'tools/e2e/e2e_f812.sh'
  'tools/e2e/e2e_f8v2_appearance.sh'
  'tools/e2e/e2e_f901.sh'
  'tools/e2e/e2e_f902.sh'
  'tools/e2e/e2e_f903.sh'
  'tools/e2e/e2e_f904.sh'
  'tools/e2e/e2e_f905.sh'
  'tools/e2e/e2e_f906.sh'
  'tools/e2e/e2e_f907.sh'
  'tools/e2e/e2e_f908.sh'
  'tools/e2e/e2e_f909.sh'
  'tools/e2e/e2e_f910.cjs'
  'tools/e2e/e2e_f910.sh'
  'tools/e2e/e2e_f911.sh'
  'tools/e2e/e2e_f912.sh'
  'tools/e2e/e2e_f913.sh'
  'tools/e2e/e2e_f914.sh'
  'tools/e2e/e2e_f915.sh'
  'tools/e2e/e2e_f916.sh'
  'tools/e2e/e2e_f917.sh'
  'tools/e2e/e2e_f919_static.sh'
  'tools/e2e/e2e_f920_static.sh'
  'dev/tests/README.md'
  'dev/tests/e2e/e2e_f212.sh'
  'dev/tests/e2e/e2e_f213.sh'
  'dev/tests/e2e/e2e_f214.sh'
  'dev/tests/e2e/e2e_f215.sh'
  'dev/tests/e2e/e2e_f216.sh'
  'dev/tests/e2e/e2e_regressao.sh'
  'dev/tests/e2e/index.html'
  'dev/tests/e2e/setup_sandbox.sh'
  'dev/tests/index.html'
  'docs/ANALISE-ESCOPOS-GITHUB.md'
  'docs/ANALISE-POS-LIMPEZA-ESCOPOS.md'
  'docs/ANALISE-RAIZ-GITHUB.md'
  'docs/Analise-KAZEditor-Theme-Importacao.md'
  'docs/Analise-Pos-Limpeza-GitHub-2026-11-05.md'
  'docs/Auditoria-Limpeza-GitHub-2026-11-05.md'
  'docs/BDREditor — complete project reference and closure.md'
  'docs/BDREditor-Accessible-Details.md'
  'docs/BDREditor-Audio-Scope.md'
  'docs/BDREditor-Audio.md'
  'docs/BDREditor-Block-Copy-Paste-Scope.md'
  'docs/BDREditor-Block-Copy-Paste.md'
  'docs/BDREditor-Blog-Catalog-Preview.md'
  'docs/BDREditor-CMS-Page-Integration.md'
  'docs/BDREditor-Editorial-Callout.md'
  'docs/BDREditor-Elementor-Style-Workbench.md'
  'docs/BDREditor-File-Download-Scope.md'
  'docs/BDREditor-File-Download.md'
  'docs/BDREditor-Hierarchical-Navigation-Scope.md'
  'docs/BDREditor-Hierarchical-Navigation.md'
  'docs/BDREditor-Hierarchical-Reordering.md'
  'docs/BDREditor-Immersive-Editing.md'
  'docs/BDREditor-Keyboard-Editing-Scope.md'
  'docs/BDREditor-Keyboard-Editing.md'
  'docs/BDREditor-Modern-Interface.md'
  'docs/BDREditor-Outline-Keyboard-Navigation-Scope.md'
  'docs/BDREditor-Outline-Keyboard-Navigation.md'
  'docs/BDREditor-Pages-Gap-Audit.md'
  'docs/BDREditor-Preformatted-Code.md'
  'docs/BDREditor-Table-Base-Hashes.json'
  'docs/BDREditor-Table-Contract.json'
  'docs/BDREditor-Table-Verification.json'
  'docs/BDREditor-Table.md'
  'docs/BDREditor-UI-Interpretation.md'
  'docs/BDREditor-Unsaved-Changes-Warning.md'
  'docs/BDREditor-Vertical-Spacer.md'
  'docs/COMANDOS-POWERSHELL.md'
  'docs/CORRECAO-MAPA-DE-SITES.md'
  'docs/CORRIGIR-4-ERROS.md'
  'docs/CORRIGIR-AGORA.md'
  'docs/CORRIGIR-README.ps1'
  'docs/CORRIGIR-README.sh'
  'docs/CORRIGIR-SCHEMA.ps1'
  'docs/CORRIGIR-SCHEMA.sh'
  'docs/Checklist-Veredito-Visual-AP.md'
  'docs/DIAGNOSTICO-3D.md'
  'docs/DIAGNOSTICO-BO.md'
  'docs/DIAGNOSTICO-D09.md'
  'docs/Diagnostico-CI-Vermelho-accb6257.md'
  'docs/ESTADO-GOVERNANCA.md'
  'docs/Extrato-Contratos-HEAD-179269fe.md'
  'docs/F11-06-Fechamento-e-Radar.md'
  'docs/F11-11-Decisoes-Contratos.md'
  'docs/F11-12-Docs-Links-Offline.md'
  'docs/F11-13-Fechamento-Fase-11.md'
  'docs/F11-14-Aplicacao-Pendentes.md'
  'docs/F11-15-Evidencia-Tecnica.md'
  'docs/F11-16-Auditoria-Contratos-Congelados.md'
  'docs/F9-02-Contrato-Campos-Personalizados.json'
  'docs/F9-03-Auditoria-Banco-DavidAlmeida.md'
  'docs/F9-04-Plano-DryRun-DavidAlmeida.md'
  'docs/F9-05-Modelo-Definicoes-Campos.md'
  'docs/F9-06-UI-Admin-Campos-Personalizados.md'
  'docs/F9-07-Valores-Custom-Fields-CMS-Page.md'
  'docs/F9-08-Exibicao-Publica-Campos-CMS-Page.md'
  'docs/F9-09-Navegacao-Campos-Personalizados.md'
  'docs/F9-10-Dicionario-e-Preflight-Integrado.md'
  'docs/F9-100-Visual-Consistency-Audit.md'
  'docs/F9-101-Find-Replace-Safety-Preview.md'
  'docs/F9-102-Internal-Editorial-Notes.md'
  'docs/F9-12-Migration-e-Persistencia-Blog-Blocos.md'
  'docs/F9-16-Editor-Admin-Blog-Blocos.md'
  'docs/F9-17-Checklist-E2E-Blog-Blocos.md'
  'docs/F9-18-Source-Hashes.json'
  'docs/F9-19-Correcoes-Readiness-Blog-Blocks.md'
  'docs/F9-20-Source-Hashes.json'
  'docs/F9-20-Verificacao-Estatica-Remediacao.md'
  'docs/F9-21-Contrato-Rich-Text-v2.json'
  'docs/F9-21-Roadmap-Editor-Avancado.md'
  'docs/F9-22-Base-Hashes.json'
  'docs/F9-22-Contrato-Rich-Text-v2.json'
  'docs/F9-22-Rich-Text-Inline.md'
  'docs/F9-23-Base-Hashes.json'
  'docs/F9-23-Block-UX.md'
  'docs/F9-24-Base-Hashes.json'
  'docs/F9-24-Contrato-Media-Blocks.json'
  'docs/F9-24-Media-Blocks.md'
  'docs/F9-25-Base-Hashes.json'
  'docs/F9-25-Editor-Editorial.md'
  'docs/F9-26-Acessibilidade-Localizacao-Performance.md'
  'docs/F9-26-Base-Hashes.json'
  'docs/F9-29-Portabilidade-Documento-BDRE.md'
  'docs/F9-30-Historico-Local-BDRE.md'
  'docs/F9-32-Protecao-Edicao-Concorrente-BDRE.md'
  'docs/F9-33-Comparacao-Revisoes-BDRE.md'
  'docs/F9-34-Diff-Textual-Revisoes-BDRE.md'
  'docs/F9-35-Preflight-Statico-Release-BDRE.md'
  'docs/F9-36-Busca-e-Substituicao-BDRE.md'
  'docs/F9-37-Acessibilidade-Atalhos-BDRE.md'
  'docs/F9-38-Busca-Atravessa-Formatacao-BDRE.md'
  'docs/F9-39-Palavras-Inteiras-BDRE.md'
  'docs/F9-40-Normalizacao-Unicode-BDRE.md'
  'docs/F9-41-Teclado-Localizar-Substituir-BDRE.md'
  'docs/F9-42-Estatisticas-Documento-BDRE.md'
  'docs/F9-43-Personalizar-Blocos-BDRE.md'
  'docs/F9-44-Funcoes-Blocos-e-Faixa.md'
  'docs/F9-45-Faixa-Office-Blocos-Tooltips.md'
  'docs/F9-46-Guias-Faixa-BDREditor.md'
  'docs/F9-48-Barra-Lateral-de-Ferramentas-BDREditor.md'
  'docs/F9-48-Hotfix-Rotas-e-Strings.md'
  'docs/F9-49-Workspace-Office-Modelos-e-Acesso-Rapido.md'
  'docs/F9-50-Workspace-Office-Interface-Completa.md'
  'docs/F9-51-BDREditor-Area-de-Edicao-em-Largura-Total.md'
  'docs/F9-52-BDREditor-Workspace-Fullscreen.md'
  'docs/F9-53-Bloco-Paragrafo-Inspector-e-Acoes.md'
  'docs/F9-54-Bloco-Heading-Inspetor-e-Acoes.md'
  'docs/F9-55-Bloco-List-Inspetor-e-Toolbar.md'
  'docs/F9-56-Bloco-Quote-Inspetor-e-Toolbar.md'
  'docs/F9-57-BDREditor-Bateria-Completa-e-Toolbar.md'
  'docs/F9-58-Bloco-Button-Inspetor-e-Toolbar.md'
  'docs/F9-59-Bloco-Callout-Inspetor-e-Toolbar.md'
  'docs/F9-60-Bloco-More-Informations-e-Preview.md'
  'docs/F9-61-Hotfix-Preview-safeActionUrl.md'
  'docs/F9-62-Bloco-Preformatted-Code-Inspetor-e-Toolbar.md'
  'docs/F9-63-Hotfix-Preformatted-Code-runs.md'
  'docs/F9-64-Bloco-Table-Inspetor-e-Toolbar.md'
  'docs/F9-65-Bloco-Image-Inspetor-e-Toolbar.md'
  'docs/F9-66-Hotfix-Biblioteca-Midia-CMS-Pages.md'
  'docs/F9-67-Video.md'
  'docs/F9-68-Cover.md'
  'docs/F9-69-Download.md'
  'docs/F9-70-Audio.md'
  'docs/F9-72-Separator.md'
  'docs/F9-73-Group.md'
  'docs/F9-74-Columns.md'
  'docs/F9-76-Content-Models.md'
  'docs/F9-77-Acessibilidade.md'
  'docs/F9-78-Link-Review.md'
  'docs/F9-79-Readability-Review.md'
  'docs/F9-80-Pre-Publication-Checklist.md'
  'docs/F9-81-Revision-Comparison.md'
  'docs/F9-82-Markdown-Export.md'
  'docs/F9-83-Markdown-Import.md'
  'docs/F9-84-Table-of-Contents.md'
  'docs/F9-85-Internal-Anchor-Review.md'
  'docs/F9-86-Internal-Anchor-Repair.md'
  'docs/F9-87-Pages-Fullscreen-Create-Workflow.md'
  'docs/F9-88-Pages-Quick-Edit-and-Fullscreen-Editor.md'
  'docs/F9-89-Single-Ribbon-and-Editor-Brand.md'
  'docs/F9-90-Fullscreen-Expand-Collapse-Icons.md'
  'docs/F9-91-Command-Palette.md'
  'docs/F9-92-Multi-Block-Selection-and-Grouping.md'
  'docs/F9-93-Duplicate-Selection-as-Group.md'
  'docs/F9-94-Move-Selection-as-a-Unit.md'
  'docs/F9-95-Ungroup-Block.md'
  'docs/F9-96-Block-Locks.md'
  'docs/F9-97-Copy-Apply-Style.md'
  'docs/F9-98-Style-Presets-Library.md'
  'docs/F9-99-Style-Preset-Transfer.md'
  'docs/FRENTES-INTEGRAL.md'
  'docs/Health-Admin-Real-Instrucoes.md'
  'docs/INDICE-BO.md'
  'docs/KAZ-BDREditor-Padrao-Visual.md'
  'docs/KAZ-BDREditor-Tema-Total.md'
  'docs/MEDIA_LIBRARY_PHASE_3.md'
  'docs/PROXIMO-PASSO.md'
  'docs/RELATORIO-ADERENCIA-main.md'
  'docs/RELATORIO-BO.md'
  'docs/Registro-CI-Remoto-HEAD-bfe9eec7.md'
  'docs/Registro-Visual-AP.md'
  'docs/Roteiro-Testes-Manuais-F6.md'
  'docs/THREEJS_ADMIN_PREVIEW_HELP.md'
  'docs/temas/preview-desktop.png'
  'docs/temas/preview-mobile.png'
  'docs/temas/preview.html'
)

REMOVED=0
BATCH=()
flush_batch() {
  [ "${#BATCH[@]}" -eq 0 ] && return 0
  git rm -f --ignore-unmatch "${BATCH[@]}" >/dev/null 2>&1 || true
  for f in "${BATCH[@]}"; do
    if [ -e "$f" ]; then rm -f "$f"; fi
  done
  BATCH=()
}

for f in "${FILES_TO_REMOVE[@]}"; do
  if [ -e "$f" ] || git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    REMOVED=$((REMOVED + 1))
  fi
  BATCH+=("$f")
  if [ "${#BATCH[@]}" -ge 40 ]; then
    flush_batch
  fi
done
flush_batch

rm -rf dev/tests 2>/dev/null || true
echo "-> $REMOVED arquivo(s) removido(s) (docs/, dev/tests/, tools/e2e/)."

if ! command -v node >/dev/null 2>&1; then
  echo "ERRO: node nao encontrado no PATH para atualizar tools/checkers-inventory.json." >&2
  exit 1
fi

echo "-> Atualizando tools/checkers-inventory.json (removendo os 8 checkers F8 obsoletos)..."
node - <<'NODE'
const fs = require('node:fs');
const invPath = 'tools/checkers-inventory.json';
const removedF8 = new Set([
  'e2e_f805.cjs', 'e2e_f806_http.cjs', 'e2e_f807_http.cjs', 'e2e_f808_http.cjs',
  'e2e_f809_http.cjs', 'e2e_f810_http.cjs', 'e2e_f811_http.cjs', 'e2e_f812.cjs'
]);
const inv = JSON.parse(fs.readFileSync(invPath, 'utf8'));
const prevFindings = Array.isArray(inv.findings) ? inv.findings : [];
const keptFindings = prevFindings.filter(x => !removedF8.has(x.file));
const removedCount = prevFindings.length - keptFindings.length;
if (removedCount > 0) {
  inv.findings = keptFindings;
  inv.nonzero = keptFindings.length;
  inv.total_checkers = Math.max(keptFindings.length, 45);
  const counts = {};
  for (const item of keptFindings) {
    counts[item.category] = (counts[item.category] || 0) + 1;
  }
  inv.counts = counts;
  if (inv.by_file && typeof inv.by_file === 'object') {
    for (const k of removedF8) delete inv.by_file[k];
  }
  if (Array.isArray(inv.f11_09_unresolved)) {
    inv.f11_09_unresolved = inv.f11_09_unresolved.filter(f => !removedF8.has(f));
  }
  fs.writeFileSync(invPath, JSON.stringify(inv, null, 2) + '\n', 'utf8');
  console.log(`   checkers-inventory.json atualizado: ${keptFindings.length} findings ativos (${removedCount} da F8 removidos).`);
} else {
  console.log('   checkers-inventory.json ja estava atualizado.');
}
NODE

echo ""
echo "== Validando guardas locais e sintaxe =="
GUARD_ERR=0
bash tools/verify-deps-layout.sh || GUARD_ERR=1
bash tools/verify-checksums.sh || GUARD_ERR=1
bash tools/verify-ci-workflow.sh || GUARD_ERR=1
SKIP_NPM=1 bash tools/verify-f11-01.sh || GUARD_ERR=1
SKIP_NPM=1 bash tools/repo-health.sh || GUARD_ERR=1

echo ""
if [ "$GUARD_ERR" -eq 0 ]; then
  echo "== Passo 1C.2 concluido e TODOS os guardas locais PASSARAM! =="
else
  echo "AVISO: algum guarda retornou erro acima. Revise antes de fazer commit." >&2
  exit 1
fi
echo "Proximos comandos sugeridos:"
echo "  git status -s"
echo "  git add -A"
echo "  git commit -m \"chore: remove obsolete F2-F9 e2e scripts, dev/tests, and historical docs (Step 1C.2)\""
echo "  git push origin main"
