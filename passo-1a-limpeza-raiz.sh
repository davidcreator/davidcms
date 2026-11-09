#!/usr/bin/env bash
# ==============================================================================
# passo-1a-limpeza-raiz.sh
# Limpeza controlada da Etapa 1A (Raiz / arquivos de hotfix / limpezas antigas)
#
# Padrao: DRY-RUN (nao altera nada sem --aplicar).
# Nunca faz git commit nem git push automaticamente.
#
# Uso:
#   bash passo-1a-limpeza-raiz.sh                       # dry-run (mostra 1A.1 e 1A.2)
#   bash passo-1a-limpeza-raiz.sh --etapa 1a1 --aplicar # remove 41 arquivos sem impacto no CI
#   bash passo-1a-limpeza-raiz.sh --etapa 1a2 --aplicar # remove install.lock + F11-* + atualiza CI/CHECKSUMS
#   bash passo-1a-limpeza-raiz.sh --etapa tudo --aplicar
# ==============================================================================
set -euo pipefail

ETAPA="tudo"
APLICAR=0

while [ "$#" -gt 0 ]; do
  case "$1" in
    --etapa)
      ETAPA="${2:-}"
      shift 2
      ;;
    --aplicar)
      APLICAR=1
      shift
      ;;
    --help|-h)
      sed -n '2,15p' "$0"
      exit 0
      ;;
    *)
      echo "Argumento desconhecido: $1 (use --help)" >&2
      exit 2
      ;;
  esac
done

case "$ETAPA" in
  1a1|1a2|tudo) ;;
  *) echo "Etapa invalida: '$ETAPA'. Use: 1a1 | 1a2 | tudo" >&2; exit 2 ;;
esac

[ -d .git ] || { echo "ERRO: execute na raiz do clone Git do davidcms." >&2; exit 1; }
[ -f dev/catalog/index.php ] || { echo "ERRO: dev/catalog/index.php nao encontrado; diretorio invalido." >&2; exit 1; }

# --- Etapa 1A.1: 41 arquivos sem dependencia nos CHECKSUMS*.txt nem links em outros .md ---
ARQS_1A1=(
  "INSTALAR.bat"
  "instalar-bo.ps1"
  "instalar-bo.py"
  "COPIAR-MANUALMENTE.md"
  "verificar-bo.html"
  "diagnostico-console.js"
  "limpar-github.sh"
  "listas/inventario-HEAD-9c606810.csv"
  "listas/lista-limpeza-final-C.txt"
  "listas/lista-nivel-A-remover.txt"
  "listas/lista-nivel-A2-map.txt"
  "listas/lista-nivel-B-compilar.txt"
  "listas/lista-rodada-final-D.txt"
  "docs/aplicar-limpeza-escopos.sh"
  "docs/aplicar-limpeza-final-C.sh"
  "docs/aplicar-rodada-final.sh"
  "docs/verificar-congelados.sh"
  "docs/verificar-links.sh"
  "docs/VERIFICAR.ps1"
  "docs/PLANO-LIMPEZA.md"
  "docs/RELATORIO-FINAL-LIMPEZA.md"
  "docs/inventario-limpeza.csv"
  "docs/LEIA-ME-FINAL.txt"
  "docs/RESTAURAR-docs-README.txt"
  "docs/correcao/A-vazamento-failclosed.patch"
  "docs/correcao/B-editor-dados-reais.patch"
  "docs/correcao/correcao-runtime.patch"
  "docs/correcao/patch.json"
  "docs/correcao/patches.json"
  "threejs/index.html"
  "capturas/01-site-home-desktop.jpg"
  "capturas/02-site-home-mobile.jpg"
  "capturas/03-site-portfolio.jpg"
  "capturas/04-admin-login.jpg"
  "capturas/05-admin-dashboard.jpg"
  "capturas/06-admin-editor.jpg"
  "capturas/07-admin-appearance.jpg"
  "capturas/08-appearance-CORRIGIDO.jpg"
  "capturas/09-login-CORRIGIDO.jpg"
  "capturas/10-portfolio-CORRIGIDO.jpg"
  "capturas/11-editor-ABRE.jpg"
)

# --- Etapa 1A.2: itens com ajuste casado de .gitignore / docs/README.md / CHECKSUMS ---
ARQS_1A2=(
  "dev/system/storage/install.lock"
  "F11-01-INDICE.md"
  "F11-02-INDICE.md"
  "F11-04-INDICE.md"
  "F11-05-INDICE.md"
  "F11-06-INDICE.md"
  "F11-07-INDICE.md"
  "F11-08-INDICE.md"
  "F11-09-INDICE.md"
  "F11-10-INDICE.md"
  "F11-11-INDICE.md"
  "F11-12-INDICE.md"
  "F11-13-INDICE.md"
  "F11-14-INDICE.md"
  "F11-15-INDICE.md"
  "F11-16-INDICE.md"
  "CHECKSUMS-F11-04.txt"
  "CHECKSUMS-F11-05.txt"
  "CHECKSUMS-F11-06.txt"
  "CHECKSUMS-F11-07.txt"
  "CHECKSUMS-F11-08.txt"
  "CHECKSUMS-F11-09.txt"
  "CHECKSUMS-F11-10.txt"
  "CHECKSUMS-F11-11.txt"
  "CHECKSUMS-F11-12.txt"
  "CHECKSUMS-F11-13.txt"
  "CHECKSUMS-F11-14.txt"
  "CHECKSUMS-F11-15.txt"
  "CHECKSUMS-F11-16.txt"
)

rm_tracked_list() {
  local label="$1"
  shift
  local items=("$@")
  local presentes=()
  local bytes=0
  for p in "${items[@]}"; do
    if git ls-files --error-unmatch "$p" >/dev/null 2>&1; then
      presentes+=("$p")
      if [ -f "$p" ]; then
        bytes=$((bytes + $(wc -c < "$p")))
      fi
    elif [ -e "$p" ]; then
      echo "  [nao versionado no Git, ignorado]: $p"
    fi
  done

  echo "== $label: ${#presentes[@]} arquivo(s) versionado(s) encontrado(s) ($bytes bytes) =="
  if [ "${#presentes[@]}" -eq 0 ]; then
    echo "  Nada pendente nesta etapa."
    return 0
  fi

  for p in "${presentes[@]}"; do
    echo "  - $p"
  done

  if [ "$APLICAR" -eq 1 ]; then
    git rm -q -- "${presentes[@]}"
    echo "  [OK] Removidos do indice e do disco (staged para commit)."
  else
    echo "  [DRY-RUN] Rode com --aplicar para executar git rm."
  fi
}

aplicar_ajustes_1a2() {
  echo "== Ajustes casados da Etapa 1A.2 =="

  # 1. Adicionar install.lock no .gitignore
  if [ -f .gitignore ]; then
    if ! grep -qxF 'dev/system/storage/install.lock' .gitignore; then
      echo "  + Adicionar 'dev/system/storage/install.lock' ao .gitignore"
      if [ "$APLICAR" -eq 1 ]; then
        printf '\n# Lock local de instalacao (nao versionar)\ndev/system/storage/install.lock\n' >> .gitignore
        git add .gitignore
      fi
    else
      echo "  = .gitignore ja ignora dev/system/storage/install.lock"
    fi
  fi

  # 2. Remover linha de indices da raiz em docs/README.md (para manter lychee verde)
  if [ -f docs/README.md ] && grep -q 'F11-01-INDICE.md' docs/README.md; then
    echo "  ~ Remover linha de links para F11-*-INDICE.md em docs/README.md"
    if [ "$APLICAR" -eq 1 ]; then
      python3 - <<'PY'
from pathlib import Path
p = Path('docs/README.md')
lines = p.read_text(encoding='utf-8').splitlines(keepends=True)
filtered = [l for l in lines if 'F11-01-INDICE.md' not in l]
p.write_text(''.join(filtered), encoding='utf-8')
PY
      git add docs/README.md
    fi
  fi

  # 3. Atualizar CHECKSUMS.txt unico (preserva itens ativos, remove F11-*-INDICE.md, inclui docs/README.md)
  if [ -f CHECKSUMS.txt ]; then
    echo "  ~ Atualizar CHECKSUMS.txt consolidado (removendo F11-*-INDICE.md e recalculando hashes)"
    if [ "$APLICAR" -eq 1 ]; then
      python3 - <<'PY'
import hashlib, zlib
from pathlib import Path

cs = Path('CHECKSUMS.txt')
entries = []
seen = set()
for line in cs.read_text(encoding='utf-8').splitlines():
    s = line.strip()
    if not s or s.startswith('#'):
        continue
    parts = s.split()
    path = parts[-1]
    if path.startswith('F11-') and path.endswith('-INDICE.md'):
        continue
    if Path(path).is_file() and path not in seen:
        entries.append(path)
        seen.add(path)

for extra in ('.gitignore', 'docs/README.md'):
    if Path(extra).is_file() and extra not in seen:
        entries.append(extra)
        seen.add(extra)

out = [
    '# Manifesto consolidado de integridade (pos-limpeza Etapa 1A)',
    '# Formato: CRC32  SHA256  tamanho(bytes)  caminho',
    '',
]
for path in sorted(entries):
    data = Path(path).read_bytes()
    crc = f'{zlib.crc32(data) & 0xffffffff:08x}'
    sha = hashlib.sha256(data).hexdigest()
    out.append(f'{crc}  {sha}  {len(data)}  {path}')

cs.write_text('\n'.join(out) + '\n', encoding='utf-8')
PY
      git add CHECKSUMS.txt
    fi
  fi
}

if [ "$ETAPA" = "1a1" ] || [ "$ETAPA" = "tudo" ]; then
  rm_tracked_list "Etapa 1A.1 (sem impacto no CI)" "${ARQS_1A1[@]}"
fi

if [ "$ETAPA" = "1a2" ] || [ "$ETAPA" = "tudo" ]; then
  rm_tracked_list "Etapa 1A.2 (install.lock + F11-* + CHECKSUMS-F11-*)" "${ARQS_1A2[@]}"
  aplicar_ajustes_1a2
fi

if [ "$APLICAR" -eq 1 ]; then
  echo
  echo "== Executando guardas locais de conferencia =="
  [ -f tools/verify-deps-layout.sh ] && bash tools/verify-deps-layout.sh || true
  [ -f tools/verify-checksums.sh ] && bash tools/verify-checksums.sh || true
  [ -f tools/verify-ci-workflow.sh ] && bash tools/verify-ci-workflow.sh || true
  echo
  echo "Proximo passo sugerido: revise 'git status' e 'git diff --cached', depois faca seu commit."
else
  echo
  echo "Modo DRY-RUN finalizado. Nenhuma alteracao foi gravada."
fi
