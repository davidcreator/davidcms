#!/usr/bin/env python3
"""
verify-contract-freeze.py — F11-11

Guard READ-ONLY da fronteira entre diagnostico e decisao.

  python3 tools/verify-contract-freeze.py

* Recalcula o SHA-256 dos artefatos congelados em decision-matrix.json.
* Reprova (exit 1) qualquer alteracao de contrato enquanto a decisao
  correspondente ainda estiver PENDING_DECISION — impede que um contrato
  seja reemitido "por inferencia".
* Fail-closed: artefato ausente ou matriz invalida tambem sao exit 1.

Nao escreve, nao acessa rede, nao altera contratos, checkers, codigo,
banco, staging ou producao. Nao substitui os guardas existentes.
"""
import hashlib
import json
import os
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
MATRIX = os.path.join(ROOT, 'tools', 'decision-matrix.json')


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as fh:
        for chunk in iter(lambda: fh.read(65536), b''):
            h.update(chunk)
    return h.hexdigest()


def main():
    if not os.path.isfile(MATRIX):
        print('FAIL  matriz ausente: tools/decision-matrix.json')
        return 1
    try:
        with open(MATRIX, encoding='utf-8') as fh:
            m = json.load(fh)
        frozen = m['frozen_artifacts_sha256']
        decisions = m['decisions']
        assert isinstance(frozen, dict) and frozen
        assert isinstance(decisions, list) and decisions
    except Exception as exc:
        print('FAIL  matriz invalida: %s' % exc)
        return 1

    divergentes, ausentes, ok = [], [], 0
    for rel, esperado in sorted(frozen.items()):
        p = os.path.join(ROOT, rel)
        if not os.path.isfile(p):
            ausentes.append(rel)
            continue
        if sha256(p) != esperado:
            divergentes.append(rel)
        else:
            ok += 1

    print('== F11-11 — congelamento de contratos (%d artefatos) ==' % len(frozen))
    print('   %d/%d hashes coerentes | %d pendentes de decisao' %
          (ok, len(frozen), sum(1 for d in decisions if d.get('status') == 'PENDING_DECISION')))
    for rel in ausentes:
        print('   AUSENTE   %s (fail-closed)' % rel)
    for rel in divergentes:
        donos = [d['id'] for d in decisions
                 if rel in d.get('frozen_artifacts', [])
                 and d.get('status') == 'PENDING_DECISION']
        if donos:
            print('   REGRA     %s mudou sem decisao registrada (%s)' % (rel, ', '.join(donos)))
        else:
            print('   DIVERGENTE %s' % rel)

    if ausentes or divergentes:
        print('\n== RESULTADO: BLOQUEADO — registre a decisao em decision-matrix.json antes de alterar contratos ==')
        return 1
    print('\n== RESULTADO: COERENTE — nenhum contrato alterado sem decisao ==')
    print('   Lembrando: decisoes pendentes NAO sao aprovacao. F9-27 segue BLOCKED_NOT_RUN.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
