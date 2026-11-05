#!/usr/bin/env bash
# ============================================================================
# limpar-github.sh — limpeza do repositorio ReamurCMS
# Auditoria: /home/user/Auditoria-Limpeza-GitHub-2026-11-05.md (2026-11-05)
# HEAD auditado: 5593de67253a018db488592f70699e51cbc0a952
#
#   bash limpar-github.sh                      # DRY-RUN (padrao, nao muda nada)
#   bash limpar-github.sh --aplicar            # Nivel 1 (96 arquivos / 3,7 MB)
#   bash limpar-github.sh --aplicar --nivel2   # + Nivel 2 (46 arquivos / 5,0 MB; mantem tests/)
#   bash limpar-github.sh --aplicar --commit   # + commit local (NUNCA faz push)
#   bash limpar-github.sh --help
#
# Execute NA RAIZ do clone do repositorio. Nao commita nem pusha por padrao.
# Nao reemite manifestos, nao altera hashes, nao toca em dev/ (runtime).
# ============================================================================
set -euo pipefail

HEAD_AUDITADO='5593de67253a018db488592f70699e51cbc0a952'
APLICAR=0; NIVEL2=0; COMMIT=0

for a in "$@"; do
  case "$a" in
    --aplicar) APLICAR=1 ;;
    --nivel2)  NIVEL2=1 ;;
    --commit)  COMMIT=1 ;;
    --help|-h) sed -n '2,18p' "$0"; exit 0 ;;
    *) echo "Opcao desconhecida: $a (use --help)"; exit 2 ;;
  esac
done

VERDE='\033[0;32m'; AMARELO='\033[0;33m'; VERMELHO='\033[0;31m'; FIM='\033[0m'
info(){ printf '%b\n' "$*"; }
die(){ printf '%bERRO:%b %s\n' "$VERMELHO" "$FIM" "$*" >&2; exit 1; }
aviso(){ printf '%bAVISO:%b %s\n' "$AMARELO" "$FIM" "$*" >&2; }

# ---------------------------------------------------------------- 0. seguranca
[ -d .git ]            || die 'nao e a raiz de um repositorio git (cd para o clone)'
[ -f CHECKSUMS.txt ]   || die 'CHECKSUMS.txt ausente — provavelmente nao e a raiz do ReamurCMS'
[ -f tools/decision-matrix.json ] || die 'tools/decision-matrix.json ausente'

HEAD_ATUAL=$(git rev-parse HEAD)
if [ "$HEAD_ATUAL" != "$HEAD_AUDITADO" ]; then
  aviso "HEAD atual $HEAD_ATUAL difere do auditado $HEAD_AUDITADO."
  aviso 'Confira se a arvore ainda corresponde a auditoria antes de aplicar.'
fi
if [ -n "$(git status --porcelain)" ]; then
  if [ "$APLICAR" -eq 1 ]; then
    die 'worktree nao esta limpo. Commite ou descarte suas alteracoes primeiro.'
  fi
  aviso 'worktree nao esta limpo (ignorado no dry-run; exigido ao aplicar).'
fi

# ---------------------------------------------------------------- 1. lista
# git ls-files (e nao glob do shell) garante que so arquivos VERSIONADOS entram.
PATHSPECS=(
  'evidencia/'
  'RELATORIO-FINAL-*.md'
  'INDICE-AH.md' 'INDICE-AI.md' 'INDICE-AJ.md' 'INDICE-AL.md' 'INDICE-AM.md'
  'docs/KAZTheme-Editor*.md'
  'tools/kaz-theme-*' 'tools/verify-kaz-*'
)
if [ "$NIVEL2" -eq 1 ]; then
  # tests/ fica DE FORA: o CI faz `find tools tests -type f ...` com
  # set -euo pipefail (php.yml:198). Sao apenas 80 KB; nao vale o risco.
  PATHSPECS+=( '*.map' 'tree/' 'reference/' 'preview/' 'entregas/' )
fi

mapfile -d '' -t REMOVER < <(git ls-files -z -- "${PATHSPECS[@]}")
N=${#REMOVER[@]}
ESPERADO=96; [ "$NIVEL2" -eq 1 ] && ESPERADO=142
[ "$N" -gt 0 ] || die 'nenhum arquivo encontrado — nada a fazer; reaudite o repo'
if [ "$N" -ne "$ESPERADO" ]; then
  aviso "encontrados $N arquivos, esperados $ESPERADO no estado auditado."
  aviso 'Pode haver arquivos novos/removidos desde a auditoria. Revise a lista:'
  printf '   %s\n' "${REMOVER[@]}" > /tmp/limpar-lista.log
  head -20 /tmp/limpar-lista.log
  die 'contagem divergente — abortando por seguranca (fail-closed)'
fi

# ---------------------------------------------------------------- 2. contratos
# Nenhum arquivo a remover pode estar citado nos 14 manifestos de checksum.
if grep -F -q -f <(printf '%s\n' "${REMOVER[@]}") CHECKSUMS*.txt 2>/dev/null; then
  die 'algum arquivo a remover esta citado em CHECKSUMS*.txt — abortando'
fi
# Nem entre os 11 artefatos congelados da decision-matrix.
if command -v python3 >/dev/null 2>&1; then
  python3 - "${REMOVER[@]}" <<'PY' || die 'arquivo congelado na lista de remocao'
import json,sys
m=json.load(open('tools/decision-matrix.json'))
f=set(m['frozen_artifacts_sha256'])
contra=f.intersection(sys.argv[1:])
if contra:
    print('  congelados:',*sorted(contra)); raise SystemExit(1)
print('  11 artefatos congelados intactos')
PY
else
  aviso 'python3 ausente — nao foi possivel checar decision-matrix.json'
fi
# Nada de runtime em dev/. Unica excecao: os 16 sourcemaps *.map do Nivel 2
# (Bootstrap/jQuery vendorizados) — nao sao codigo executado nem referenciados
# pelos guards; removidos apenas com --nivel2 explicito.
MAPS=0
for p in "${REMOVER[@]}"; do
  case "$p" in
    dev/*)
      if [ "$NIVEL2" -eq 1 ] && [ "${p##*.}" = 'map' ]; then
        MAPS=$((MAPS+1))
      else
        die "runtime em dev/ na lista: $p"
      fi ;;
  esac
done
[ "$MAPS" -eq 0 ] || [ "$MAPS" -eq 16 ] || die "esperados 16 sourcemaps, encontrados $MAPS"
info "${VERDE}OK:${FIM} $N arquivos, 0 em manifestos, 0 congelados, 0 de runtime em dev/$(
  [ "$MAPS" -eq 0 ] || printf ' (%d sourcemaps permitidos no Nivel 2)' "$MAPS")"

# ---------------------------------------------------------------- 3. links do CI
# O job docs-links roda `lychee --offline './**/*.md'`. Estes 3 .md ficam e
# citam arquivos que saem; as 6 linhas precisam sair no mesmo commit.
MDS=('INDICE.md' 'RELATORIO.md' 'docs/RELATORIO-ADERENCIA-main.md')
# Match ancorado ao campo de caminho (ultimo do manifesto): o antigo -F casava
# 'INDICE.md' como substring de 'F11-04-INDICE.md' e abortava por falso positivo.
MDS_RE=$(printf '%s\n' "${MDS[@]}" | sed 's/\./\\./g' | paste -sd'|' -)
if grep -E -q "(^|[[:space:]])(${MDS_RE})$" CHECKSUMS*.txt 2>/dev/null; then
  die 'um dos 3 .md a editar esta em manifesto — abortando'
fi
for f in "${MDS[@]}"; do [ -f "$f" ] || die "ausente: $f"; done

# sed -E: padrao -> numero de linhas que DEVEM ser removidas
edita(){ # arquivo  regex  esperado
  local f="$1" re="$2" esp="$3" antes depois n
  antes=$(wc -l < "$f")
  if [ "$APLICAR" -eq 1 ]; then
    # -i.bak funciona no GNU sed e no BSD sed; '#' como delimitador evita
    # conflito com as barras dos caminhos dentro de $re
    sed -E -i.limpeza-bak "\#$re#d" "$f"
    depois=$(wc -l < "$f")
    n=$((antes-depois))
    [ "$n" -eq "$esp" ] || die "$f: removeu $n linhas, esperadas $esp (backup: $f.limpeza-bak)"
    rm -f "$f.limpeza-bak"
    info "   editado $f (-$n linhas)"
  else
    n=$(grep -Ec "$re" "$f" || true)
    info "   editaria $f (-$n linhas, esperadas $esp)"
    [ "$n" -eq "$esp" ] || aviso "$f: $n linhas casam, esperadas $esp"
  fi
}

info ''
info '== Pre-requisito: 6 linhas que quebrariam o docs-links =='
edita 'INDICE.md' 'docs/KAZTheme-Editor|evidencia/' 3
edita 'RELATORIO.md' 'evidencia/' 1
edita 'docs/RELATORIO-ADERENCIA-main.md' 'docs/KAZTheme-Editor' 2

# ---------------------------------------------------------------- 4. resumo
info ''
BYTES=0
for p in "${REMOVER[@]}"; do
  [ -f "$p" ] && BYTES=$((BYTES + $(wc -c < "$p")))
done
info "== Remocao: $N arquivos / $BYTES bytes (~$((BYTES/1024/1024)) MB) =="
if [ "$APLICAR" -eq 0 ]; then
  info "${AMARELO}DRY-RUN — nada foi alterado.${FIM} Amostra:"
  printf '   %s\n' "${REMOVER[@]}" > /tmp/limpar-lista.log
  head -12 /tmp/limpar-lista.log
  info '   ...'
  info ''
  info 'Para aplicar:  bash limpar-github.sh --aplicar'
  info 'Para incluir .map e snapshots (mantendo tests/):  bash limpar-github.sh --aplicar --nivel2'
  exit 0
fi

git rm -r -q -- "${REMOVER[@]}"
info "${VERDE}OK:${FIM} git rm aplicado ($N arquivos). Nenhum commit/push ainda."

# ---------------------------------------------------------------- 5. guards
info ''
info '== Guardas pos-limpeza (os 4 devem dar rc=0) =='
FALHA=0
rodar(){ # comando...
  local nome="$1"; shift
  local rc=0
  "$@" >/tmp/limpar-guard.log 2>&1 || rc=$?
  if [ "$rc" -eq 0 ]; then
    info "   ${VERDE}rc=0${FIM}  $nome  ::  $(tail -1 /tmp/limpar-guard.log | cut -c1-100)"
  else
    info "   ${VERMELHO}rc=$rc${FIM}  $nome  ::  $(tail -3 /tmp/limpar-guard.log | tr '\n' ' ' | cut -c1-160)"
    FALHA=1
  fi
}
for g in tools/verify-checksums.sh tools/repo-health.sh tools/verify-ci-workflow.sh; do
  [ -f "$g" ] && rodar "$g" bash "$g" || aviso "$g ausente, pulando"
done
if [ -f tools/verify-contract-freeze.py ] && command -v python3 >/dev/null 2>&1; then
  rodar 'tools/verify-contract-freeze.py' python3 tools/verify-contract-freeze.py
fi
if command -v lychee >/dev/null 2>&1; then
  rodar 'lychee (links internos)' lychee --offline --no-progress './**/*.md'
else
  aviso 'lychee nao instalado localmente — o job docs-links do CI fara essa checagem'
fi

# ---------------------------------------------------------------- 6. commit
info ''
info "== git status =="
# Sem pipe para head: com `set -o pipefail`, head fechando cedo gera SIGPIPE
# e abortaria o script antes do commit quando ha muitas dezenas de arquivos.
git status --short > /tmp/limpar-status.log 2>&1 || true
head -20 /tmp/limpar-status.log
if [ "$COMMIT" -eq 1 ]; then
  [ "$FALHA" -eq 0 ] || die 'um guarda falhou — commit NAO realizado'
  git add -A
  git commit -q -m "chore(repo): remove validation artifacts (auditoria 2026-11-05)

Remove $N arquivos de evidencia/relatorios/probes/docs das frentes A-AM
$( [ "$NIVEL2" -eq 1 ] && echo 'incluindo sourcemaps e snapshots; tests/ mantido' )
Sem alteracao de runtime, manifestos, hashes, schema ou contratos."
  info "${VERDE}OK:${FIM} commit local criado. Push fica por sua conta (eu nao escrevo no GitHub)."
else
  info 'Sem --commit: nada foi commitado. Quando estiver satisfeito:'
  info '   git add -A && git commit -m "chore(repo): limpeza validacao A-AM"'
fi
[ "$FALHA" -eq 0 ] || die 'pelo menos um guarda falhou — revise antes de commitar/pushar'
info ''
info "${VERDE}Concluido.${FIM} CI remoto, capturas do Admin real e copy(__kazHealth()) continuam pendentes; release_approved segue false."
