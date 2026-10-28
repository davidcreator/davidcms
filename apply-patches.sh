#!/usr/bin/env bash
# Safely preflight and apply one or more unified patches in sequence.
# Run from the target repository root, or pass --root DIR.
set -Eeuo pipefail
IFS=$'\n\t'

usage() {
  cat <<'USAGE'
Usage:
  ./apply-patches.sh [--root DIR] [--dry-run | --apply] PATCH [PATCH ...]
  ./apply-patches.sh [--root DIR] [--dry-run | --apply] --patch-dir DIR

Options:
  --root DIR       Target repository root (default: current directory).
  --dry-run        Validate sequentially in a temporary staging tree; do not modify target (default).
  --apply          Preflight all patches, then apply them in the given/sorted order.
  --patch-dir DIR  Use all *.patch files in DIR, sorted lexicographically.
  -h, --help       Show this help.

The script fails closed: all patches must apply in order before any target file is
changed. On an apply-stage failure, it restores all files touched by the batch.
It never commits, pushes, deploys, or runs migrations.
USAGE
}

root=$PWD
mode=dry-run
patch_dir=
declared_patches=()
while (($#)); do
  case "$1" in
    --root)
      (($# >= 2)) || { echo "ERROR: --root requires a directory." >&2; exit 2; }
      root=$2; shift 2 ;;
    --dry-run) mode=dry-run; shift ;;
    --apply) mode=apply; shift ;;
    --patch-dir)
      (($# >= 2)) || { echo "ERROR: --patch-dir requires a directory." >&2; exit 2; }
      patch_dir=$2; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    --) shift; while (($#)); do declared_patches+=("$1"); shift; done ;;
    -*) echo "ERROR: unknown option: $1" >&2; usage >&2; exit 2 ;;
    *) declared_patches+=("$1"); shift ;;
  esac
done

[[ -d "$root" ]] || { echo "ERROR: target root not found: $root" >&2; exit 2; }
root=$(cd "$root" && pwd -P)
command -v patch >/dev/null 2>&1 || { echo "ERROR: 'patch' is required." >&2; exit 2; }

if [[ -n "$patch_dir" ]]; then
  [[ ${#declared_patches[@]} -eq 0 ]] || { echo "ERROR: use either --patch-dir or patch file arguments, not both." >&2; exit 2; }
  [[ -d "$patch_dir" ]] || { echo "ERROR: patch directory not found: $patch_dir" >&2; exit 2; }
  shopt -s nullglob
  for item in "$patch_dir"/*.patch; do declared_patches+=("$item"); done
  shopt -u nullglob
fi

if [[ ${#declared_patches[@]} -eq 0 ]]; then
  echo "ERROR: provide at least one patch file (or use --patch-dir)." >&2
  usage >&2
  exit 2
fi

patches=()
for item in "${declared_patches[@]}"; do
  [[ -f "$item" ]] || { echo "ERROR: patch file not found: $item" >&2; exit 2; }
  patches+=("$(cd "$(dirname "$item")" && pwd -P)/$(basename "$item")")
done

work=$(mktemp -d "${TMPDIR:-/tmp}/apply-patches.XXXXXXXX")
cleanup() { rm -rf "$work"; }
trap cleanup EXIT
stage="$work/stage"
backup="$work/backup"
mkdir -p "$stage" "$backup"

declare -A known_paths=()
declare -A existed_before=()
paths=()

# Parse unified-diff file headers. Restrict paths to safe repository-relative paths.
for patch_file in "${patches[@]}"; do
  while IFS= read -r line; do
    case "$line" in
      '--- '*|'+++ '*)
        rel=${line:4}
        rel=${rel%%$'\t'*}
        [[ "$rel" == /dev/null ]] && continue
        [[ "$rel" != *' '* ]] || { echo "ERROR: filenames with spaces are not supported in patch headers: $rel" >&2; exit 2; }
        rel=${rel#./}
        rel=${rel#a/}
        rel=${rel#b/}
        [[ -n "$rel" && "$rel" != /* ]] || { echo "ERROR: unsafe patch path in $patch_file: $rel" >&2; exit 2; }
        case "/$rel/" in */../*|*/./../*) echo "ERROR: path traversal in patch: $rel" >&2; exit 2 ;; esac
        if [[ -z ${known_paths[$rel]+x} ]]; then
          known_paths[$rel]=1
          paths+=("$rel")
        fi
        ;;
    esac
  done < "$patch_file"
done

((${#paths[@]} > 0)) || { echo "ERROR: no unified-diff file paths found in the supplied patch(es)." >&2; exit 2; }

# Seed the isolated staging tree with only files that the patch batch can touch.
for rel in "${paths[@]}"; do
  source_file="$root/$rel"
  if [[ -L "$source_file" ]]; then
    echo "ERROR: refusing to patch a symlink target: $rel" >&2
    exit 2
  fi
  if [[ -f "$source_file" ]]; then
    existed_before[$rel]=1
    mkdir -p "$stage/$(dirname "$rel")" "$backup/$(dirname "$rel")"
    cp -p "$source_file" "$stage/$rel"
    cp -p "$source_file" "$backup/$rel"
  else
    existed_before[$rel]=0
  fi
done

# Sequential preflight on the staging tree supports patch batches with dependencies.
for patch_file in "${patches[@]}"; do
  echo "Preflight: $(basename "$patch_file")"
  patch --dry-run --forward --batch -p1 -d "$stage" < "$patch_file"
  patch --forward --batch -p1 -d "$stage" < "$patch_file" >/dev/null
done

if [[ "$mode" == dry-run ]]; then
  echo "Dry run passed: ${#patches[@]} patch(es) apply in sequence. No repository files were changed."
  exit 0
fi

# The whole batch was validated. Recheck each patch against the real root immediately
# before applying. Restore the original touched-file set if any real application fails.
rollback() {
  echo "ERROR: applying the batch failed; restoring touched files to their pre-run state." >&2
  for rel in "${paths[@]}"; do
    if [[ ${existed_before[$rel]} == 1 ]]; then
      mkdir -p "$root/$(dirname "$rel")"
      cp -p "$backup/$rel" "$root/$rel"
    else
      rm -f "$root/$rel"
    fi
  done
}

for patch_file in "${patches[@]}"; do
  echo "Applying: $(basename "$patch_file")"
  if ! patch --dry-run --forward --batch -p1 -d "$root" < "$patch_file"; then
    rollback
    exit 1
  fi
  if ! patch --forward --batch -p1 -d "$root" < "$patch_file"; then
    rollback
    exit 1
  fi
done

echo "Applied ${#patches[@]} patch(es) successfully. No commit, push, deployment, or migration was performed."
