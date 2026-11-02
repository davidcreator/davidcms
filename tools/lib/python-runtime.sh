#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# python-runtime.sh — resolvedor portatil de interpretador Python.
#
# No Windows, `python3`/`python` podem ser APENAS o App Execution Alias da
# Microsoft Store: `command -v` encontra, mas qualquer execucao imprime
# "Python was not found..." e sai com 49. Confiar em `command -v` produzia
# FAIL (exit 49) em guardas que nao tem culpa nenhuma.
#
# Aqui testamos `import sys` de verdade antes de aceitar o candidato e
# tambem suportamos o launcher oficial `py -3`. Nao usa eval.
#
# Efeito: define PYTHON_CMD (ex.: "python3", "python", "py -3") e a funcao
# pyrun(). Se nenhum interpretador real existir, PYTHON_CMD fica VAZIO e os
# chamadores devem registrar SKIP explicito — nunca PASS.
# ════════════════════════════════════════════════════════════════════════
PYTHON_CMD="${PYTHON_CMD:-}"
if [ -z "$PYTHON_CMD" ]; then
  for cand in python3 python py; do
    command -v "$cand" >/dev/null 2>&1 || continue
    if [ "$cand" = py ]; then
      py -3 -c 'import sys' >/dev/null 2>&1 || continue
      PYTHON_CMD='py -3'
    else
      "$cand" -c 'import sys' >/dev/null 2>&1 || continue
      PYTHON_CMD="$cand"
    fi
    break
  done
fi
# Word-splitting intencional: PYTHON_CMD so contem os valores fixos acima.
# Nunca e derivado de entrada do usuario.
pyrun() { $PYTHON_CMD "$@"; }
export PYTHON_CMD
