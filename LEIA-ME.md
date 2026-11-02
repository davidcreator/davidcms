# Consolidado: leva o main e21eb3859 ao CI verde

O HEAD e21eb3859 ("fix: repair repo health and update checksums") deixou o
run 41518585929 VERMELHO no passo "Integridade dos manifestos" do repo-guards.

Tres problemas reais, todos confirmados no SHA:
  1. Quatro entradas de manifesto apontam hashes de versoes antigas:
       CHECKSUMS-F11-06.txt -> tools/release-readiness-report.sh (3320 vs 3612 bytes)
       CHECKSUMS-F11-13.txt -> tools/repo-health.sh             (2006 vs 2697 bytes)
       CHECKSUMS-F11-14.txt -> tools/release-readiness-report.sh (3320 vs 3612 bytes)
       CHECKSUMS.txt        -> tools/verify-deps-layout.sh      (4413 vs 4983 bytes)
  2. tools/verify-f11-01.sh foi sobrescrito pela versao anterior, sem o
     resolvedor de Python: o exit 49 do alias da Microsoft Store volta no Windows.
  3. As mudancas de ReamurCMS-CI-Instalador-Canonico NAO estao no main:
     php.yml ainda contem `run: npm ci ...` (0 referencias a install-deps.sh) e
     verify-ci-workflow.sh ainda tem 28 checks, sem a secao 5b.

Conteudo (3 arquivos de codigo + 14 manifestos):
  .github/workflows/php.yml     -> node-deps usa bash tools/install-deps.sh
  tools/verify-ci-workflow.sh   -> 31 checks, exige instalador e proibe npm ci direto
  tools/verify-f11-01.sh        -> pyrun/placeholder (versao 958900728)
  14 CHECKSUMS*.txt             -> reemitidos por ponto fixo sobre os arquivos reais
  CHECKSUMS.txt                 -> tools/lib/python-runtime.sh volta a ser auditado

Os demais scripts corrigidos (repo-health.sh, release-readiness-report.sh,
verify-deps-layout.sh, python-runtime.sh) JA ESTAO corretos no main e por isso
nao foram reenviados: apenas seus hashes foram atualizados nos manifestos.

Nenhum contrato, matriz de decisoes, schema.sql, hash de release gate,
release_approved ou codigo do CMS foi alterado. Nenhum SKIP/NOT_RUN virou PASS.
Nenhum browser foi baixado. Nenhuma escrita remota foi feita.

Validado (e com A/B sobre o HEAD real):
  ANTES  verify-checksums exit 1 / 4 FAIL  (reproduz o CI vermelho)
  DEPOIS verify-checksums exit 0 / 0 divergentes
  DEPOIS repo-health 6 PASS / 0 FAIL / 0 SKIP
  repo-health com stub Store: 4 PASS / 0 FAIL / 2 SKIP
  verify-ci-workflow 31 PASS / 0 FAIL; contract-freeze exit 0

Aplicar: bash tools/verify-checksums.sh && bash tools/repo-health.sh
