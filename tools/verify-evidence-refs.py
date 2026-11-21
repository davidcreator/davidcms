#!/usr/bin/env python3
"""
verify-evidence-refs.py — F11-15, reforcado pela ADR 0130

Guard READ-ONLY de INTEGRIDADE DE EVIDENCIA. Confere que cada referencia em
tools/decision-evidence.json aponta para um arquivo existente e que o trecho
citado realmente ocorre nele. Nao reimplementa os guardas existentes (esses
checam contratos, checkers, workflow e dependencias).

  python3 tools/verify-evidence-refs.py [raiz-candidata]

A raiz candidata (mesmo convenio de tools/verify-ci-workflow.sh e do
verify-docs-index.php) permite auditar uma arvore proposta sem tocar na do
repositorio — e e o que da ao teste unitario um estado errado para reprovar.

Segunda checagem (ADR 0130): os artefatos de ESTADO de tools/ nao podem repetir
contagem de decisao — "N PENDING_DECISION" ou "N contratos congelados" com N
diferente do que a matriz diz agora.
Quem repete numero cristaliza o dia em que ele foi verdade. Foi o caso aqui: a
frase fixa deste próprio guarda estava falsa havia 4 dias, o JSON de evidencia
dizia a mesma frase, e a auditoria de drift errava o total de contratos por 19. exit 0 = referencias coerentes e nenhuma
contagem reescrita | exit 1 = referencia quebrada ou contagem divergente.
Nao escreve, nao acessa rede, nao executa PHP/Twig/browser/banco.
"""
import json
import os
import sys

DEFAULT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


def load_matrix(root):
    """Verdade derivada: a matriz e a fonte, nunca um numero transcrito aqui."""
    try:
        with open(os.path.join(root, 'tools', 'decision-matrix.json'), encoding='utf-8') as fh:
            m = json.load(fh)
    except Exception:
        return None
    counts = m.get('status_counts', {}) or {}
    return {
        'pending': counts.get('PENDING_DECISION'),
        'decided': counts.get('DECIDED'),
        'frozen': len(m.get('frozen_artifacts_sha256', {}) or {}),
    }


def artefatos_de_estado(root):
    """JSON de governanca em tools/ que o CI ou um guarda le — e o script do
    proprio guarda. Documento .md fica fora: prosa no passado
    ("a regra so bloqueava quando estava PENDING") e registro, e o
    CHANGELOG e cheio disso. Limite declarado na ADR 0130."""
    out = []
    tdir = os.path.join(root, 'tools')
    if os.path.isdir(tdir):
        for nome in sorted(os.listdir(tdir)):
            caminho = os.path.join(tdir, nome)
            if not os.path.isfile(caminho):
                continue
            if nome.endswith('.json') or nome == 'verify-evidence-refs.py':
                out.append(caminho)
    return out


def contagens_reescritas(root, matriz):
    """Toda contagem de status/contratos escrita em prosa nos artefatos de
    estado tem de bater com a matriz. Numero errado = FAIL; numero nenhum =
    nada a dizer, e e o que se prefere."""
    import re
    if matriz is None:
        return ['decision-matrix.json ausente ou ilegivel — nada com o que comparar']
    por_chave = {'PENDING_DECISION': 'pending', 'DECIDED': 'decided'}
    achados = []
    padrao = re.compile(r'(\d+)\s*(?:`)?\b(PENDING_DECISION|DECIDED)\b')
    congelados = re.compile(r'(\d+)\s+contratos congelados')
    for caminho in artefatos_de_estado(root):
        texto = open(caminho, encoding='utf-8', errors='replace').read()
        for numero, chave, contexto in [(m.group(1), m.group(2), m.group(0))
                                       for m in padrao.finditer(texto)]:
            atual = matriz[por_chave[chave]]
            if atual is None or int(numero) != atual:
                achados.append('%s: "%s" — a matriz diz %s %s'
                               % (os.path.relpath(caminho, root), contexto.strip(), atual, chave))
        for m in congelados.finditer(texto):
            if int(m.group(1)) != matriz['frozen']:
                achados.append('%s: "%s" — a matriz lista %d'
                               % (os.path.relpath(caminho, root), m.group(0).strip(), matriz['frozen']))
    return achados


def main():
    root = os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_ROOT
    EVID = os.path.join(root, 'tools', 'decision-evidence.json')
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
            p = os.path.join(root, ref['file'])
            if not os.path.isfile(p):
                broken.append('%s: arquivo ausente %s' % (did, ref['file']))
                continue
            text = open(p, encoding='utf-8', errors='replace').read()
            if ref['snippet'] not in text:
                broken.append('%s: trecho ausente em %s -> %r' % (did, ref['file'], ref['snippet']))
                continue
            ok += 1

    matriz = load_matrix(root)
    rewrite = contagens_reescritas(root, matriz)
    print('\n== F11-15 — integridade de evidencias: %d/%d referencias coerentes ==' % (ok, total))
    for b in broken:
        print('   QUEBRADA  %s' % b)
    print('== ADR 0130 — contagem de decisao reescrita em artefato de estado: %d ==' % len(rewrite))
    for r in rewrite:
        print('   DIVERGENTE  %s' % r)
    if broken or total == 0 or rewrite:
        print('\n== RESULTADO: REPROVADO — corrija as referencias antes de usar o dossie ==')
        return 1
    print('   3 decisoes investigadas | 7 herdadas e marcadas como nao reauditadas')
    print('\n== RESULTADO: COERENTE ==')
    if matriz:
        print('   decision-matrix.json lido agora: %s DECIDED / %s PENDING_DECISION / %d contratos congelados'
              % (matriz['decided'], matriz['pending'], matriz['frozen']))
    print('   Recomendacao nao e decisao — e o numero acima e lido da matriz, nao transcrito aqui.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
