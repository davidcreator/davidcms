#!/usr/bin/env python3
"""
verify-evidence-refs.py — F11-15

Guard READ-ONLY de INTEGRIDADE DE EVIDENCIA. Confere que cada referencia em
tools/decision-evidence.json aponta para um arquivo existente e que o trecho
citado realmente ocorre nele. Nao reimplementa os guardas existentes (esses
checam contratos, checkers, workflow e dependencias).

  python3 tools/verify-evidence-refs.py

exit 0 = todas as referencias coerentes | exit 1 = referencia quebrada.
Nao escreve, nao acessa rede, nao executa PHP/Twig/browser/banco.
"""
import json
import os
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
EVID = os.path.join(ROOT, 'tools', 'decision-evidence.json')


def main():
    if not os.path.isfile(EVID):
        print('FAIL  evidencia ausente: tools/decision-evidence.json')
        return 1
    try:
        with open(EVID, encoding='utf-8') as fh:
            e = json.load(fh)
        entries = e['entries']
        assert isinstance(entries, list) and len(entries) == 10
    except Exception as exc:
        print('FAIL  JSON de evidencia invalido: %s' % exc)
        return 1

    total = ok = 0
    broken = []
    for d in entries:
        did = d.get('id', '?')
        if d.get('evidence_level') == 'INHERITED_F11_11':
            print('   %-4s HERDADO (nao reauditado nesta frente)' % did)
            continue
        for ref in d.get('evidence_refs', []):
            total += 1
            p = os.path.join(ROOT, ref['file'])
            if not os.path.isfile(p):
                broken.append('%s: arquivo ausente %s' % (did, ref['file']))
                continue
            text = open(p, encoding='utf-8', errors='replace').read()
            if ref['snippet'] not in text:
                broken.append('%s: trecho ausente em %s -> %r' % (did, ref['file'], ref['snippet']))
                continue
            ok += 1

    print('\n== F11-15 — integridade de evidencias: %d/%d referencias coerentes ==' % (ok, total))
    for b in broken:
        print('   QUEBRADA  %s' % b)
    if broken or total == 0:
        print('\n== RESULTADO: REPROVADO — corrija as referencias antes de usar o dossie ==')
        return 1
    print('   3 decisoes investigadas | 7 herdadas e marcadas como nao reauditadas')
    print('\n== RESULTADO: COERENTE ==')
    print('   Lembrando: recomendacoes NAO sao decisoes. decision-matrix.json segue PENDING_DECISION.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
