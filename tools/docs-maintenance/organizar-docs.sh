#!/usr/bin/env bash
# Organiza apenas os candidatos explicitamente aprovados na lista do kit.
# Default: plano somente-leitura. NUNCA faz commit, push, purge ou opera no banco.
#
#   bash tools/docs-maintenance/organizar-docs.sh --plan
#   bash tools/docs-maintenance/organizar-docs.sh --apply --id f9-02-duplicado
#   bash tools/docs-maintenance/organizar-docs.sh --apply --id frentes-integral
#   bash tools/docs-maintenance/organizar-docs.sh --restore latest
#
# A remoção aprovada usa git rm (staged) e guarda cópia em .git/docs-cleanup-backups/.
# Para FRENTES-INTEGRAL, o script exige primeiro que as referências sejam tratadas.
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo 'ERRO: execute dentro da raiz do clone Git do davidcms.' >&2; exit 2;
}
cd "$ROOT"

MODE=plan
RESTORE_REF=''
IDS=()
while (($#)); do
  case "$1" in
    --plan) MODE=plan; shift ;;
    --apply) MODE=apply; shift ;;
    --restore) MODE=restore; RESTORE_REF="${2:-}"; shift 2 ;;
    --id) IDS+=("${2:?informe o ID após --id}"); shift 2 ;;
    --help|-h)
      sed -n '1,24p' "$0"; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

BACKUP_ROOT="$(git rev-parse --git-path docs-cleanup-backups)"
HEAD_SHORT="$(git rev-parse --short HEAD)"

frozen_check() {
  local target="$1" m crc sha size path
  for m in CHECKSUMS*.txt; do
    [[ -f "$m" ]] || continue
    while read -r crc sha size path; do
      [[ -n "${crc:-}" && "$crc" != \#* ]] || continue
      [[ "$path" == "$target" ]] && { echo "BLOQUEADO: $target consta em $m" >&2; return 1; }
    done < "$m"
  done
  return 0
}

unchanged_check() {
  local target="$1"
  git ls-files --error-unmatch -- "$target" >/dev/null 2>&1 || {
    echo "BLOQUEADO: caminho não está versionado: $target" >&2; return 1;
  }
  git diff --quiet HEAD -- "$target" || {
    echo "BLOQUEADO: alteração local no alvo: $target" >&2; return 1;
  }
  git diff --cached --quiet HEAD -- "$target" || {
    echo "BLOQUEADO: alteração staged no alvo: $target" >&2; return 1;
  }
  [[ -f "$target" ]] || { echo "BLOQUEADO: arquivo ausente: $target" >&2; return 1; }
}

integral_sources_check() {
  local commit='0ae835b5' p n=0
  git cat-file -e "$commit^{commit}" 2>/dev/null || {
    echo "BLOQUEADO: commit de arquivo histórico $commit não existe nesta cópia Git." >&2; return 1;
  }
  while IFS= read -r p; do
    [[ -n "$p" ]] || continue
    n=$((n+1))
    git cat-file -e "$commit^:$p" 2>/dev/null || {
      echo "BLOQUEADO: fonte não recuperável no histórico: $p" >&2; return 1;
    }
  done < <(git diff-tree --no-commit-id --name-only --diff-filter=D -r "$commit" -- '*.md')
  [[ "$n" -eq 64 ]] || {
    echo "BLOQUEADO: esperado 64 Markdown recuperáveis; encontrados $n." >&2; return 1;
  }
  echo "OK: os 64 Markdown de origem podem ser recuperados de $commit^ no Git." >&2
}

reference_check() {
  local id="$1" name="$2" refs
  refs="$(git grep -l -F -- "${name##*/}" -- . 2>/dev/null | grep -Fxv -- "$name" || true)"
  case "$id" in
    f9-02-duplicado)
      # Inventário histórico pode citar o artefato; código/teste/documento ativo não.
      refs="$(printf '%s\n' "$refs" | grep -v '^listas/inventario-HEAD-9c606810\.csv$' | sed '/^$/d' || true)"
      ;;
  esac
  if [[ -n "$refs" ]]; then
    echo "BLOQUEADO: atualize/revise referências antes de retirar $name:" >&2
    printf '  %s\n' $refs >&2
    return 1
  fi
}

candidate_check() {
  local id="$1" path expected duplicate_sha
  case "$id" in
    f9-02-duplicado)
      path='docs/F9-02-Contrato-Campos-Personalizados.json'
      expected='c042ec00f5b179aff66fd794495b5b8b9c803067dba84c7eb9107d39bc6cac04'
      unchanged_check "$path" || return 1
      frozen_check "$path" || return 1
      [[ "$(sha256sum "$path" | awk '{print $1}')" == "$expected" ]] || {
        echo "BLOQUEADO: hash inesperado em $path; reanalise o conteúdo." >&2; return 1;
      }
      duplicate_sha="$(sha256sum docs/F9-01-Contrato-Campos-Personalizados.json | awk '{print $1}')"
      [[ "$duplicate_sha" == "$expected" ]] || {
        echo 'BLOQUEADO: F9-01 e F9-02 não são mais cópias idênticas.' >&2; return 1;
      }
      reference_check "$id" "$path" || return 1
      ;;
    frentes-integral)
      path='docs/FRENTES-INTEGRAL.md'
      expected='8f564851da5490546c2924f4ed090ee7a3fabe509eadb4f88247e8656e957e20'
      unchanged_check "$path" || return 1
      frozen_check "$path" || return 1
      [[ "$(sha256sum "$path" | awk '{print $1}')" == "$expected" ]] || {
        echo "BLOQUEADO: hash inesperado em $path; reanalise o conteúdo." >&2; return 1;
      }
      integral_sources_check || return 1
      reference_check "$id" "$path" || return 1
      ;;
    *) echo "ID não permitido: $id" >&2; return 2 ;;
  esac
  printf '%s\n' "$path"
}

if [[ "$MODE" == plan ]]; then
  cat <<EOF
Plano (somente leitura) | repo=$ROOT | HEAD=$HEAD_SHORT
Nenhum arquivo será alterado neste modo.

Candidatos permitidos, cada um exige --apply --id e confirmação digitada:
  f9-02-duplicado  Remoção reversível do JSON idêntico ao contrato F9-01.
  frentes-integral Remoção reversível do arquivo concatenado, só após limpar as referências.

A cópia de restauração do README, os 20 documentos cobertos por CHECKSUMS, os contratos/status
JSON, as decisões pendentes, os relatórios F9-27, os assets de tema e os guias de runtime não
fazem parte desta allowlist e não podem ser removidos por este script.

Para revisar elegibilidade agora, use explicitamente:
  bash tools/docs-maintenance/organizar-docs.sh --apply --id f9-02-duplicado
  bash tools/docs-maintenance/organizar-docs.sh --apply --id frentes-integral
A opção --apply também é um comando de escrita: valide a saída e só confirme se esta é sua decisão.
EOF
  exit 0
fi

if [[ "$MODE" == restore ]]; then
  [[ "$RESTORE_REF" == latest ]] || { echo 'Uso: --restore latest' >&2; exit 2; }
  [[ -d "$BACKUP_ROOT" ]] || { echo 'Nenhum backup encontrado.' >&2; exit 1; }
  archive="$(ls -1t "$BACKUP_ROOT"/docs-*.tar.gz 2>/dev/null | head -1 || true)"
  [[ -n "$archive" && -f "$archive" ]] || { echo 'Nenhum backup encontrado.' >&2; exit 1; }
  mapfile -t paths < <(tar -tzf "$archive" | grep -v '/$')
  for p in "${paths[@]}"; do
    [[ ! -e "$p" ]] || { echo "BLOQUEADO: destino já existe; nada restaurado: $p" >&2; exit 1; }
  done
  tar -xzf "$archive" -C "$ROOT"
  git add -- "${paths[@]}"
  echo "Restaurados e staged: ${paths[*]}"
  echo "Backup original preservado em $archive"
  exit 0
fi

[[ "$MODE" == apply ]] || exit 2
((${#IDS[@]} > 0)) || { echo 'Informe ao menos um --id; nenhum padrão de aplicação.' >&2; exit 2; }
TARGETS=()
for id in "${IDS[@]}"; do
  TARGETS+=("$(candidate_check "$id")")
done
# Evita nomes repetidos e garante que o backup restaure somente os alvos autorizados.
mapfile -t TARGETS < <(printf '%s\n' "${TARGETS[@]}" | sort -u)

printf '\nSerão staged como remoção (sem commit/push):\n'
printf '  %s\n' "${TARGETS[@]}"
printf 'Backup reversível: %s\n' "$BACKUP_ROOT"
printf 'Para autorizar, digite exatamente: LIMPAR DOCS %s\n> ' "$HEAD_SHORT"
read -r answer
[[ "$answer" == "LIMPAR DOCS $HEAD_SHORT" ]] || { echo 'Cancelado; nada alterado.'; exit 0; }

stamp="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_ROOT"
archive="$BACKUP_ROOT/docs-$stamp.tar.gz"
tar -czf "$archive" -- "${TARGETS[@]}"
printf '%s\n' "HEAD=$HEAD_SHORT" "$(printf '%s\n' "${TARGETS[@]}")" > "$BACKUP_ROOT/docs-$stamp.manifest"
git rm -- "${TARGETS[@]}"
echo "Remoções staged; backup salvo em $archive"
echo 'Nada foi commitado ou enviado. Restauração: bash tools/docs-maintenance/organizar-docs.sh --restore latest'
