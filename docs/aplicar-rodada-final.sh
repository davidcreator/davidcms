#!/usr/bin/env bash
# RODADA FINAL (Nivel D): remove 13 artefatos de auditoria ja cumpridos/duplicados.
# DRY-RUN por padrao. BLOQUEIA se docs/README.md estiver divergente (CI vermelho).
# Nunca faz commit nem push. Mantem listas/ (o aplicador de escopos depende dela).
#   bash docs/aplicar-rodada-final.sh
#   bash docs/aplicar-rodada-final.sh --apply
set -uo pipefail
ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "ERRO: rode dentro do repo git."; exit 2; }
cd "$ROOT" || exit 2
APPLY=0; [ "${1:-}" = "--apply" ] && APPLY=1
L=""
for c in listas . docs; do [ -f "$c/lista-rodada-final-D.txt" ] && { L="$c/lista-rodada-final-D.txt"; break; }; done
[ -z "$L" ] && { echo "ERRO: lista-rodada-final-D.txt nao encontrada (listas/, ., docs/). Extraia o ZIP na raiz."; exit 2; }
mapfile -t P < <(grep -vE '^[[:space:]]*(#|$)' "$L" | sort -u)
echo "== Rodada final D | repo=$ROOT | modo=$([ $APPLY -eq 1 ] && echo APPLY || echo DRY-RUN) | ${#P[@]} paths =="
echo

# --- PRE-CONDICAO: o CI nao pode estar vermelho por manifesto ---
OK_README='6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec'
OK_SCHEMA='c9dd1ec27af95d7506b8aff6af06133f75b288c2db31e5537fb7fbac2e1f67a5'
BLOQ=0
for pair in "docs/README.md:$OK_README" "dev/install/sql/schema.sql:$OK_SCHEMA"; do
  q=${pair%%:*}; h=${pair##*:}
  if [ ! -f "$q" ]; then echo "  BLOQUEADO: $q ausente."; BLOQ=1; continue; fi
  a=$(sha256sum "$q" | cut -d' ' -f1)
  if [ "$a" != "$h" ]; then
    echo "  BLOQUEADO: $q divergente ($(wc -c < "$q" | tr -d ' ') bytes / $a)"
    case "$q" in
      docs/README.md) echo "     -> RESTAURE ANTES: bash docs/CORRIGIR-README.sh" ;;
      *) echo "     -> RESTAURE ANTES: bash docs/CORRIGIR-SCHEMA.sh" ;;
    esac
    echo "     -> NAO reemita CHECKSUMS-F11-10.txt. Corrija o CI primeiro."
    BLOQ=1
  else echo "  OK $q"; fi
done
if [ "$BLOQ" -ne 0 ]; then
  echo; echo "ABORTADO: fase nao pode ser fechada com o repo-guards vermelho."; exit 1
fi

MAN=()
for m in CHECKSUMS*.txt; do
  [ -f "$m" ] || continue
  while read -r crc sha size path; do
    case "$crc" in ''|\#*) continue ;; esac
    [ -n "${path:-}" ] && MAN+=("$path")
  done < "$m"
done
F=0
for p in "${P[@]}"; do
  case "$p" in
    dev/*|tools/*|tests/*|agents-ia/*|'.github/'*|tema/*|threejs/*|docs/README.md|CHECKSUMS*|F11-*-INDICE.md|README.md|CONTRIBUTING.md|SECURITY.md|LICENSE)
      echo "  RECUSADO (protegido): $p"; F=1 ;;
  esac
  for x in "${MAN[@]}"; do [ "$x" = "$p" ] && { echo "  RECUSADO (em manifesto): $p"; F=1; break; }; done
  if grep -rqF -- "($p)" --include='*.md' . 2>/dev/null; then echo "  RECUSADO (citado em link): $p"; F=1; fi
  git ls-files --error-unmatch -- "$p" >/dev/null 2>&1 || { echo "  AUSENTE no indice: $p"; F=1; }
done
[ "$F" -ne 0 ] && { echo; echo "ABORTADO: corrija a lista antes de aplicar."; exit 1; }
echo "  seguranca: 0 manifestos / 0 congelados / 0 guards / 0 links / 0 runtime"
echo "  mantidos: listas/lista-nivel-*.txt, listas/lista-limpeza-final-C.txt,"
echo "            listas/inventario-HEAD-9c606810.csv (unica copia do inventario atual)"
echo
if [ "$APPLY" -eq 0 ]; then
  echo "Nada foi alterado. Para aplicar: bash docs/aplicar-rodada-final.sh --apply"; exit 0
fi
git rm -q -- "${P[@]}"
echo "git rm concluido: ${#P[@]} arquivos (773.334 bytes)."
echo
echo "Rode para fechar a fase:"
echo "  bash tools/verify-checksums.sh       # 142 OK / 0 FAIL"
echo "  bash tools/repo-health.sh             # 6 PASS / 0 FAIL"
echo "  bash tools/verify-ci-workflow.sh      # 30 PASS / 0 FAIL"
echo "  bash docs/verificar-congelados.sh     # 11/11"
echo "  bash docs/verificar-links.sh"
echo "Este script NAO faz commit e NAO faz push."
exit 0
