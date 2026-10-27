#!/usr/bin/env bash
# Safely apply a previously prepared local patch. This script does not commit,
# push, deploy, execute SQL, or contact GitHub.
set -euo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo "Usage: bash apply-patch.sh <patch-file> [expected-HEAD]" >&2
  exit 2
fi

patch_file="$1"
expected_head="${2:-}"

if [[ ! -f "$patch_file" ]]; then
  echo "Patch file not found: $patch_file" >&2
  exit 2
fi

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo "Run this command from inside the target Git repository." >&2
  exit 2
}
actual_head="$(git -C "$repo_root" rev-parse HEAD)"

if [[ -n "$expected_head" && "$actual_head" != "$expected_head" ]]; then
  echo "HEAD mismatch; patch not applied." >&2
  echo "  expected: $expected_head" >&2
  echo "  actual:   $actual_head" >&2
  exit 3
fi

# Preflight first. No SQL or Git write/network operation is performed.
git -C "$repo_root" apply --check "$patch_file"
git -C "$repo_root" apply "$patch_file"
echo "Patch applied locally to: $repo_root"
echo "No commit, push, staging, deploy, or migration was performed."
