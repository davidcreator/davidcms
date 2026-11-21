#!/usr/bin/env python3
"""
verify-contract-freeze.py — F11-11

Guard READ-ONLY da fronteira entre diagnostico e decisao.

  python3 tools/verify-contract-freeze.py

* Recalcula o SHA-256 dos artefatos congelados em decision-matrix.json.
* ADR 0128 (F1): reprova (exit 1) qualquer artefato cujo hash NAO esteja
  registrado na matriz -- seja porque o arquivo mudou depois da gravacao, seja
  porque o artefato nao tinha hash nenhum (o caso do PROJECT-HANDOFF.md, fora
  da matriz desde o F11-14 e por isso nunca comparado).
* A regra antiga era mais fraca do que parecia: so reprovava quando o dono
  estava PENDING_DECISION. Com as 34 decisoes DECIDED ela imprimia DIVERGENTE
  e nao bloqueava nada de verdade.
* Reemitir hash e ato HUMANO e datado: `reissued` diz por que o hash mudou,
  `revalidated_by` quem conferiu. Inferir isso de um `git log` e exatamente o
  erro que esta ADR corrige.
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

    lacunas = m.get('lacunas_conhecidas', []) or []
    lacunas_por_arquivo = {}
    for item in lacunas:
        lacunas_por_arquivo[item['arquivo']] = item

    divergentes, ausentes, sem_hash, ok, aceitos = [], [], [], 0, []
    for rel, esperado in sorted(frozen.items()):
        p = os.path.join(ROOT, rel)
        if not os.path.isfile(p):
            ausentes.append(rel)
            continue
        if sha256(p) != esperado:
            divergentes.append(rel)
        else:
            ok += 1

    # ADR 0128: artefato que a matriz nem menciona era invisivel para o guarda.
    for rel in sorted(lacunas_por_arquivo):
        p = os.path.join(ROOT, rel)
        if not os.path.isfile(p):
            ausentes.append(rel)
            continue
        item = lacunas_por_arquivo[rel]
        if sha256(p) == item['sha256']:
            aceitos.append(rel)
        else:
            sem_hash.append(rel)

    print('== F11-11 — congelamento de contratos (%d artefatos + %d lacuna(s) conhecida(s)) ==' %
          (len(frozen), len(lacunas_por_arquivo)))
    print('   %d/%d hashes coerentes | %d pendentes de decisao | %d/%d lacunas cobertas' %
          (ok, len(frozen), sum(1 for d in decisions if d.get('status') == 'PENDING_DECISION'),
           len(aceitos), len(lacunas_por_arquivo)))
    for rel in ausentes:
        print('   AUSENTE   %s (fail-closed)' % rel)
    for rel in divergentes:
        donos = [d['id'] for d in decisions
                 if rel in d.get('frozen_artifacts', [])
                 and d.get('status') == 'PENDING_DECISION']
        if donos:
            print('   REGRA     %s mudou sem decisao registrada (%s)' % (rel, ', '.join(donos)))
        else:
            print('   DIVERGENTE %s — decisao ja registrada, mas o hash nao foi reemitido' % rel)
    for rel in sem_hash:
        print('   SEM_HASH  %s mudou depois da cobertura registrada; reemita com decisao' % rel)

    if ausentes or divergentes or sem_hash:
        print('\n== RESULTADO: BLOQUEADO — reemita o hash em tools/decision-matrix.json '
              '(reissued + revalidated_by) ou reverta o contrato ==')
        return 1
    print('\n== RESULTADO: COERENTE — nenhum contrato alterado fora de decisao registrada ==')
    print('   Lembrando: decisao registrada NAO e aprovacao de release. F9-27 segue BLOCKED_NOT_RUN.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
