#!/usr/bin/env bash
# Equivalente SOMENTE-LEITURA de `lychee --offline` para links Markdown RELATIVOS.
# SEM lychee, SEM Python, SEM Node. Nao substitui o job docs-links do CI.
# Uso (Git Bash, dentro do repo):  bash docs/verificar-links.sh
set -uo pipefail
if ROOT=$(git rev-parse --show-toplevel 2>/dev/null); then :
else ROOT="$(cd "$(dirname "$0")/.." && pwd)"; fi
cd "$ROOT" || exit 1

norm() {  # normaliza dir/../arquivo sem realpath
  local p="$1" seg out=() IFS='/' parts
  read -ra parts <<< "$p"
  for seg in "${parts[@]}"; do
    case "$seg" in
      ''|'.') ;;
      '..') [ ${#out[@]} -gt 0 ] && unset 'out[${#out[@]}-1]' ;;
      *) out+=("$seg") ;;
    esac
  done
  IFS=/; printf '%s' "${out[*]}"
}

TOTAL=0; BAD=0; DIRS=0
BADFILE=/tmp/bo_links_bad.$$
: > "$BADFILE"

while IFS= read -r -d '' f; do
  dir=$(dirname "${f#./}")
  while IFS= read -r link; do
    case "$link" in
      http://*|https://*|mailto:*|'#'*|data:*|'<'http*) continue ;;
    esac
    t=${link%%#*}
    t=$(printf '%s' "$t" | sed -E 's/^<//; s/>$//; s/[[:space:]]+["'"'"'].*$//')
    [ -z "$t" ] && continue
    TOTAL=$((TOTAL+1))
    alvo=$(norm "$dir/$t")
    if [ -d "$alvo" ]; then DIRS=$((DIRS+1)); continue; fi   # lychee aceita link p/ diretorio
    if [ ! -f "$alvo" ]; then
      printf '  QUEBRADO  %s -> %s  (resolvido: %s)\n' "${f#./}" "$t" "$alvo" >> "$BADFILE"
      BAD=$((BAD+1))
    fi
  done < <(grep -oE '\[[^]]*\]\([^)]*\)' "$f" 2>/dev/null | sed -E 's/^.*\]\(([^)]*)\)$/\1/')
# Exclusoes ESPELHADAS no workflow (.github/workflows/php.yml): o CI usa
# -not -path '*/storage/vendor/*' -not -path '*/storage/node_modules/*'
# e --exclude 'dev/system/storage/node_modules' --exclude 'node_modules'.
# Sem isso, READMEs de dependencias vendorizadas geram dezenas de falsos positivos.
done < <(find . -type f -name '*.md' \
          -not -path '*/.git/*' \
          -not -path '*/storage/vendor/*' \
          -not -path '*/storage/node_modules/*' \
          -not -path '*/node_modules/*' \
          -not -path './staging/*' \
          -not -path '*/vendor/*' \
          -print0)

echo "== links Markdown relativos (offline, sem lychee) =="
echo "   $TOTAL links internos | $DIRS para diretorios | $BAD quebrados"
if [ "$BAD" -gt 0 ]; then cat "$BADFILE"; fi
rm -f "$BADFILE"
echo "   TRIAGEM, nao veredito: se o checkout estiver incompleto (sem vendor, JSON,"
echo "   imagens), arquivos ausentes aparecem aqui como falsos positivos."
echo "   Links para DIRETORIOS e 'mailto:' sem esquema sao aceitos pelo lychee oficial."
echo "   Excluidos (iguais ao CI): .git/, storage/vendor/, storage/node_modules/,"
echo "   node_modules/, vendor/, staging/ (staging e local, nao existe no remoto)."
echo "   O que importa para a limpeza: NENHUM link deve apontar para os paths"
echo "   de listas/lista-nivel-A-remover.txt nem lista-nivel-B-compilar.txt."
echo "   No CI, o job docs-links (lychee-action@v2, v0.15.0) continua sendo a verdade."
exit 0
