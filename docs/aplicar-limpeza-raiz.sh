#!/usr/bin/env bash
# Aplicacao da limpeza da RAIZ — DRY-RUN por padrao. NUNCA faz push.
# Acha as listas em listas/ (onde voce as subiu), . ou docs/.
# Uso:  bash docs/aplicar-limpeza-raiz.sh            # so confere
#       bash docs/aplicar-limpeza-raiz.sh --apply    # git rm (commit local e seu)
set -uo pipefail
if ! ROOT=$(git rev-parse --show-toplevel 2>/dev/null); then
  echo "ERRO: rode de dentro do repositorio git."; exit 2
fi
cd "$ROOT" || exit 2
APPLY=0; [ "${1:-}" = "--apply" ] && APPLY=1

LISTAS=""
for c in listas . docs; do
  if [ -f "$c/lista-raiz-unificar.txt" ] && [ -f "$c/lista-raiz-remover.txt" ]; then LISTAS="$c"; break; fi
done
if [ -z "$LISTAS" ]; then
  echo "ERRO: listas nao encontradas. Procurei em: listas/, ., docs/"
  echo "Extraia o ZIP dentro do repo ou informe onde estao as 2 listas."
  exit 2
fi
U="$LISTAS/lista-raiz-unificar.txt"; R="$LISTAS/lista-raiz-remover.txt"
echo "== Limpeza da raiz | repo=$ROOT | listas=$LISTAS | modo=$([ $APPLY -eq 1 ] && echo APPLY || echo DRY-RUN) =="
echo

mapfile -t ARRS < <(grep -vE '^[[:space:]]*(#|$)' "$U" "$R" 2>/dev/null | sed 's/^[^:]*://' | sort -u)
echo "  ${#ARRS[@]} paths candidatos"

# --- seguranca: recusa intocaveis ---
PROIB='^(CHECKSUMS.*\.txt|F11-.*-INDICE\.md|README\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE|\.gitignore|\.editorconfig|Pauta-Decisoes-D01-D10\.md)$'
FALHA=0
for p in "${ARRS[@]}"; do
  if [[ "$p" =~ $PROIB ]]; then echo "  RECUSADO (intocavel): $p"; FALHA=1; fi
  # compara o 4o CAMPO do manifesto (CRC SHA size path) — grep -F casaria
  # "INDICE.md" como substring de "F11-04-INDICE.md" (falso positivo known F11-05)
  if awk -v alvo="$p" 'NF>=4 && $4==alvo {f=1} END{exit !f}' CHECKSUMS*.txt 2>/dev/null; then
    echo "  RECUSADO (em manifesto): $p"; FALHA=1
  fi
  if ! git ls-files --error-unmatch -- "$p" >/dev/null 2>&1; then echo "  AUSENTE no repo: $p"; FALHA=1; fi
done
# nenhum alvo de link Markdown
for p in "${ARRS[@]}"; do
  if grep -rqF -- "($p)" --include='*.md' . 2>/dev/null; then echo "  RECUSADO (citado em link): $p"; FALHA=1; fi
done
# --- o FAIL do CHECKSUMS-F11-10.txt nao pode voltar antes do git rm ---
RM=docs/README.md
RMOURO='6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec'
if [ -f "$RM" ]; then
  RMHASH=$(sha256sum "$RM" | cut -d' ' -f1)
  if [ "$RMHASH" != "$RMOURO" ]; then
    echo "  BLOQUEADO: $RM divergente ($RMHASH). Rode: bash docs/CORRIGIR-README.sh"
    echo "             e git config core.autocrlf input (evita reconversao CRLF)."
    exit 1
  fi
  echo "  docs/README.md OK (3213 bytes / 6f0783e2...)"
else
  echo "  BLOQUEADO: $RM ausente."; exit 1
fi
if [ "$FALHA" -ne 0 ]; then echo; echo "ABORTADO: corrija a lista antes de aplicar."; exit 1; fi
echo "  seguranca: 0 intocaveis, 0 em manifestos, 0 linkados, todos presentes no indice"
echo

if [ "$APPLY" -eq 0 ]; then
  echo "Nada foi alterado. Para aplicar: bash docs/aplicar-limpeza-raiz.sh --apply"
  echo "(o commit local e seu; este script nao faz commit e nao faz push)"
  exit 0
fi

git rm -- "${ARRS[@]}"
echo
echo "git rm concluido. Rode os guards e o link check, depois faca o commit LOCAL."
echo "  bash tools/verify-checksums.sh"
echo "  bash tools/repo-health.sh"
echo "  bash tools/verify-ci-workflow.sh"
echo "  bash docs/verificar-congelados.sh"
echo "  bash docs/verificar-links.sh"
echo "Este script NAO faz commit e NAO faz push."
exit 0
