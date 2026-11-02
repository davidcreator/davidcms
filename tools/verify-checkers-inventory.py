#!/usr/bin/env python3
"""
verify-checkers-inventory.py — F11-07 / frente A

Guard de ESTABILIDADE do conjunto de checkers estaticos. NAO exige que os 36
checkers com saida nao-zero passem: exige que o conjunto seja IDENTICO ao
inventario auditado em tools/checkers-inventory.json.

  python3 tools/verify-checkers-inventory.py

* REGRESSAO  = novo checker com rc != 0 que nao esta no inventario -> exit 1
* RESOLVIDO  = checker inventariado que agora passa -> aviso (atualize o JSON)
* ESTAVEL    = conjunto identico -> exit 0

Read-only: nao altera checkers, nao marca NOT_RUN como PASS, nao acessa rede,
banco, PHP, browser, staging ou producao. Apenas executa os checkers .cjs
existentes (que, por sua vez, sao estaticos ou recusam runtime por design).
"""
import json
import os
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
INV = os.path.join(ROOT, 'tools', 'checkers-inventory.json')
E2E = os.path.join(ROOT, 'tools', 'e2e')


def main():
    if not os.path.isfile(INV):
        print('FAIL  inventario ausente: tools/checkers-inventory.json')
        return 1
    with open(INV, encoding='utf-8') as fh:
        inv = json.load(fh)
    expected = {e['file'] for e in inv['findings']}

    files = sorted(f for f in os.listdir(E2E) if f.endswith('.cjs')) if os.path.isdir(E2E) else []
    if not files:
        print('FAIL  tools/e2e/*.cjs nao encontrado')
        return 1

    actual = {}
    for f in files:
        try:
            r = subprocess.run(['node', os.path.join('tools', 'e2e', f)],
                               cwd=ROOT, capture_output=True, text=True, timeout=90)
            rc = r.returncode
        except subprocess.TimeoutExpired:
            rc = 'TIMEOUT'
        except Exception as exc:  # pragma: no cover
            print('AVISO  %s: %s' % (f, exc))
            rc = 'ERROR'
        if rc != 0:
            actual[f] = rc

    atual = set(actual)
    regressoes = sorted(atual - expected)
    resolvidos = sorted(expected - atual)

    print('== F11-07 — estabilidade dos checkers (%d executados) ==' % len(files))
    print('   inventario: %d nao-zero auditados | observados agora: %d' % (len(expected), len(atual)))
    for f in sorted(atual & expected):
        print('   ESTAVEL   %-52s rc=%s [%s]' % (f, actual[f], inv['by_file'][f]['category']))
    for f in resolvidos:
        print('   RESOLVIDO %-52s (atualize tools/checkers-inventory.json)' % f)
    for f in regressoes:
        print('   REGRESSAO %-52s rc=%s  <-- NOVA FALHA' % (f, actual[f]))

    print()
    if regressoes:
        print('== RESULTADO: REGRESSAO — %d nova(s) falha(s) nao auditada(s) ==' % len(regressoes))
        return 1
    if resolvidos:
        print('== RESULTADO: ESTAVEL com %d resolucao(oes) pendente(s) de atualizacao do inventario ==' % len(resolvidos))
        return 0
    print('== RESULTADO: ESTAVEL — conjunto identico ao inventario ==')
    print('   Lembrando: estabilidade NAO e aprovacao. F9-27 segue BLOCKED_NOT_RUN.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
