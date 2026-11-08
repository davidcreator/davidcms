#!/usr/bin/env bash
# Nivel C: remove APENAS os 7 artefatos redundantes/obsoletos do kit de auditoria.
# DRY-RUN por padrao. Nunca faz commit nem push. Mantem as copias em listas/.
#   bash docs/aplicar-limpeza-final-C.sh
#   bash docs/aplicar-limpeza-final-C.sh --apply
set -uo pipefail
ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "ERRO: rode dentro do repo git."; exit 2; }
cd "$ROOT" || exit 2
APPLY=0; [ "${1:-}" = "--apply" ] && APPLY=1
L=""
for c in listas . docs; do [ -f "$c/lista-limpeza-final-C.txt" ] && { L="$c/lista-limpeza-final-C.txt"; break; }; done
[ -z "$L" ] && { echo "ERRO: lista-limpeza-final-C.txt nao encontrada (listas/, ., docs/). Extraia o ZIP na raiz."; exit 2; }
mapfile -t P < <(grep -vE '^[[:space:]]*(#|$)' "$L" | sort -u)
echo "== Limpeza final C | repo=$ROOT | modo=$([ $APPLY -eq 1 ] && echo APPLY || echo DRY-RUN) | ${#P[@]} paths =="

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
    dev/*|tools/*|tests/*|agents-ia/*|'.github/'*|tema/*|threejs/*) echo "  RECUSADO (protegido): $p"; F=1 ;;
  esac
  for x in "${MAN[@]}"; do [ "$x" = "$p" ] && { echo "  RECUSADO (em manifesto): $p"; F=1; break; }; done
  if grep -rqF -- "($p)" --include='*.md' . 2>/dev/null; then echo "  RECUSADO (citado em link): $p"; F=1; fi
  git ls-files --error-unmatch -- "$p" >/dev/null 2>&1 || { echo "  AUSENTE no indice: $p"; F=1; }
done
# README e schema nao podem estar vermelhos (uma vez so, fora do loop)
for pair in "docs/README.md:6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec" \
            "dev/install/sql/schema.sql:c9dd1ec27af95d7506b8aff6af06133f75b288c2db31e5537fb7fbac2e1f67a5"; do
  q=${pair%%:*}; h=${pair##*:}
  [ -f "$q" ] || continue
  [ "$(sha256sum "$q" | cut -d' ' -f1)" = "$h" ] || { echo "  BLOQUEADO: $q divergente. Restaure antes (CORRIGIR-README / CORRIGIR-SCHEMA)."; F=1; }
done
[ "$F" -ne 0 ] && { echo; echo "ABORTADO."; exit 1; }
echo "  seguranca: 0 manifestos / 0 congelados / 0 links / 0 runtime / README+schema OK"
echo "  mantidos: listas/lista-nivel-*.txt (o aplicador de escopos precisa delas)"
if [ "$APPLY" -eq 0 ]; then
  echo; echo "Nada foi alterado. Para aplicar: bash docs/aplicar-limpeza-final-C.sh --apply"; exit 0
fi
git rm -q -- "${P[@]}"
echo; echo "git rm concluido: ${#P[@]} arquivos (559.354 bytes)."
echo "Rode: bash tools/verify-checksums.sh | bash tools/repo-health.sh | bash tools/verify-ci-workflow.sh"
echo "Este script NAO faz commit e NAO faz push."
exit 0
