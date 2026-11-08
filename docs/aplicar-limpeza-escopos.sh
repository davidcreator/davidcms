#!/usr/bin/env bash
# Limpeza dos ESCOPOS (evidencia/, previa/, tree/, preview/, patch/, reference/,
# diagnostico/, listas/, 1 HTML em entregas/) — DRY-RUN por padrao. NUNCA faz push.
#
#   bash docs/aplicar-limpeza-escopos.sh                 # so confere
#   bash docs/aplicar-limpeza-escopos.sh --nivel2       # inclui os 16 .map (checagem)
#   bash docs/aplicar-limpeza-escopos.sh --apply        # git rm do Nivel A
#   bash docs/aplicar-limpeza-escopos.sh --apply --nivel2
#
# Validacoes: 0 em manifestos, 0 congelados, 0 guards, 0 alvos de link,
# 0 dentro de dev/ (exceto .map com --nivel2), tests/, agents-ia/, .github/.
set -uo pipefail
if ! ROOT=$(git rev-parse --show-toplevel 2>/dev/null); then
  echo "ERRO: rode de dentro do repositorio git."; exit 2
fi
cd "$ROOT" || exit 2
APPLY=0; NIVEL2=0
for a in "$@"; do
  case "$a" in --apply) APPLY=1 ;; --nivel2) NIVEL2=1 ;; *) echo "Opcao desconhecida: $a"; exit 2 ;; esac
done

LISTAS=""
for c in listas . docs; do
  if [ -f "$c/lista-nivel-A-remover.txt" ]; then LISTAS="$c"; break; fi
done
if [ -z "$LISTAS" ]; then
  echo "ERRO: lista-nivel-A-remover.txt nao encontrada (listas/, ., docs/)."
  echo "Extraia o ZIP da analise de escopos na raiz do repo."; exit 2
fi
A="$LISTAS/lista-nivel-A-remover.txt"
A2="$LISTAS/lista-nivel-A2-map.txt"
echo "== Limpeza de escopos | repo=$ROOT | listas=$LISTAS | modo=$([ $APPLY -eq 1 ] && echo APPLY || echo DRY-RUN) | nivel2=$NIVEL2 =="
echo

mapfile -t ARRS < <(cat "$A" "$([ $NIVEL2 -eq 1 ] && [ -f "$A2" ] && echo "$A2")" 2>/dev/null |
                    grep -vE '^[[:space:]]*(#|$)' | sort -u)
echo "  ${#ARRS[@]} paths candidatos"

# --- conjuntos de protecao lidos do proprio repo ---
MAN=()
for m in CHECKSUMS*.txt; do
  [ -f "$m" ] || continue
  while read -r crc sha size path; do
    case "$crc" in ''|\#*) continue ;; esac
    [ -n "${path:-}" ] && MAN+=("$path")
  done < "$m"
done
CONG=()
if [ -f tools/decision-matrix.json ]; then
  while IFS= read -r p; do [ -n "$p" ] && CONG+=("$p"); done < <(
    sed -n '/"frozen_artifacts_sha256"[[:space:]]*:/,/^[[:space:]]*}/p' tools/decision-matrix.json |
    sed -nE 's/^[[:space:]]*"([^"]+)".*/\1/p')
fi
GUARDS=$( { cat .github/workflows/php.yml tools/repo-health.sh 2>/dev/null; } |
          grep -oE 'tools/[A-Za-z0-9_.-]+\.(sh|py|cjs)' | sort -u )

FALHA=0
for p in "${ARRS[@]}"; do
  # 1) top-level proibido
  case "$p" in
    dev/*)
      if [ "$NIVEL2" -eq 1 ] && [[ "$p" == *.map ]]; then :; else
        echo "  RECUSADO (runtime dev/): $p"; FALHA=1; fi ;;
    tests/*|agents-ia/*|'.github/'*|tools/*|docs/*|tema/*|threejs/*|entregas/*)
      if [[ "$p" == 'entregas/BDREditor-UI-Interpretation-Preview.html' ]]; then :; else
        echo "  RECUSADO (escopo protegido): $p"; FALHA=1; fi ;;
  esac
  # 2) manifesto (comparacao exata do 4o campo, nao substring)
  for x in "${MAN[@]}"; do
    if [ "$x" = "$p" ]; then echo "  RECUSADO (em manifesto): $p"; FALHA=1; break; fi
  done
  # 3) congelado
  for x in "${CONG[@]}"; do
    if [ "$x" = "$p" ]; then echo "  RECUSADO (congelado): $p"; FALHA=1; break; fi
  done
  # 4) guard
  if printf '%s\n' "$GUARDS" | grep -qxF -- "$p"; then
    echo "  RECUSADO (guard do CI): $p"; FALHA=1
  fi
  # 5) alvo de link Markdown
  if grep -rqF -- "($p)" --include='*.md' . 2>/dev/null; then
    echo "  RECUSADO (citado em link): $p"; FALHA=1
  fi
  # 6) presente no indice
  if ! git ls-files --error-unmatch -- "$p" >/dev/null 2>&1; then
    echo "  AUSENTE no indice: $p"; FALHA=1
  fi
  # 7) o README nao pode estar vermelho
  if [ -f docs/README.md ]; then
    RH=$(sha256sum docs/README.md | cut -d' ' -f1)
    if [ "$RH" != '6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec' ]; then
      echo "  BLOQUEADO: docs/README.md divergente. Rode: bash docs/CORRIGIR-README.sh"
      echo "             e git config core.autocrlf input"; FALHA=1
    fi
  fi
done
if [ "$FALHA" -ne 0 ]; then echo; echo "ABORTADO: corrija antes de aplicar."; exit 1; fi
echo "  seguranca: 0 manifestos / 0 congelados / 0 guards / 0 links / 0 runtime / README OK"
echo
if [ "$APPLY" -eq 0 ]; then
  echo "Nada foi alterado. Para aplicar: bash docs/aplicar-limpeza-escopos.sh --apply"
  echo "(commit local e seu; este script nao faz commit e nao faz push)"
  exit 0
fi

git rm -r -q -- "${ARRS[@]}"
echo "git rm concluido: ${#ARRS[@]} arquivos."
echo
echo "Rode agora:"
echo "  bash tools/verify-checksums.sh     # 142 OK / 0 FAIL"
echo "  bash tools/repo-health.sh"
echo "  bash tools/verify-ci-workflow.sh"
echo "  bash docs/verificar-links.sh"
echo "Nivel B (docs/ + tools/) NAO foi tocado — decisao separada."
echo "Este script NAO faz commit e NAO faz push."
exit 0
