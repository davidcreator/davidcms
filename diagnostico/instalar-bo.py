#!/usr/bin/env python3
"""Frente BO-r3 — instalador local do runtime (alternativa ao git apply).

Voce aplica. Nao escreve no GitHub, nao roda migration/deploy/CI, nao
altera SQL, contratos congelados, CHECKSUMS.txt nem o catalogo publico.

Uso (dentro da pasta extraida do pacote):
    python3 instalar-bo.py --repo /caminho/para/davidcms --check
    python3 instalar-bo.py --repo /caminho/para/davidcms --apply

O modo --apply copia os 10 arquivos de patch/arquivos/ para os MESMOS
caminhos no repositorio e falha alto e claro se o modulo novo nao existir ao
final. E a causa mais comum de "3D nao aparece": main.js atualizado, mas
scenes/david-universe.js ausente -> import 404 -> o modulo ES inteiro aborta.
"""
import os, sys, shutil, argparse, hashlib

ARQUIVOS = [
 'dev/system/library/three/runtime/three/main.js',
 'dev/system/library/three/asset_manifest.php',
 'dev/system/library/theme/theme_package.php',
 'dev/system/library/theme/condition_engine.php',
 'dev/system/library/theme/theme_snapshot.php',
 'dev/system/library/three/scene_preset_registry.php',
 'dev/catalog/controller/startup/theme.php',
 'dev/catalog/controller/common/home.php',
 'dev/admin/controller/design/theme.php',
 'dev/system/library/three/runtime/three/scenes/david-universe.js',  # NOVO
]
NOVO = 'dev/system/library/three/runtime/three/scenes/david-universe.js'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--repo', required=True, help='raiz do checkout do davidcms')
    ap.add_argument('--apply', action='store_true', help='copia os arquivos (padrao: so verifica)')
    ap.add_argument('--check', action='store_true', help='apenas verifica (padrao)')
    a = ap.parse_args()
    repo = os.path.abspath(a.repo)
    here = os.path.dirname(os.path.abspath(__file__))
    cands = [os.path.abspath(os.path.join(here, 'patch', 'arquivos')),
             os.path.abspath(os.path.join(here, '..', 'patch', 'arquivos')),
             os.path.abspath(os.path.join(here, '..', '..', 'patch', 'arquivos'))]
    src = next((p for p in cands if os.path.isdir(p)), cands[0])
    if not os.path.isdir(repo):
        print('ERRO: repositorio nao existe:', repo); return 2
    if not os.path.isdir(src):
        print('ERRO: patch/arquivos/ nao encontrado ao lado deste script:', src); return 2

    P, F = 0, 0
    def ok(n, c, x=''):
        nonlocal P, F
        P += 1 if c else 0; F += 0 if c else 1
        print(('OK   ' if c else 'FAIL ') + n + ((' · ' + x) if x else ''))

    print('== BO-r3 instalador | repo=%s | modo=%s ==\n' % (repo, 'APPLY' if a.apply else 'CHECK'))

    # 1) todos os arquivos de origem existem no pacote
    for rel in ARQUIVOS:
        ok('pacote contem ' + rel.split('/')[-1], os.path.isfile(os.path.join(src, rel)))

    # 2) o modulo novo e o ponto critico
    novo_src = os.path.join(src, NOVO)
    ok('modulo novo tem conteudo real', os.path.isfile(novo_src) and os.path.getsize(novo_src) > 10000,
       str(os.path.getsize(novo_src)) + ' bytes' if os.path.isfile(novo_src) else 'ausente')

    # 3) copia
    if a.apply:
        for rel in ARQUIVOS:
            d = os.path.join(repo, rel)
            os.makedirs(os.path.dirname(d), exist_ok=True)
            shutil.copyfile(os.path.join(src, rel), d)
        print('\n10 arquivos copiados.\n')
    else:
        print('\nModo CHECK: nada foi copiado. Use --apply para instalar.\n')

    # 4) verificacao POS-install (so faz sentido com --apply)
    if a.apply:
        for rel in ARQUIVOS:
            d = os.path.join(repo, rel)
            ok('instalado ' + rel.split('/')[-1], os.path.isfile(d) and os.path.getsize(d) > 0)
        m = os.path.join(repo, 'dev/system/library/three/runtime/three/main.js')
        ok('main.js importa david-universe',
           os.path.isfile(m) and "./scenes/david-universe.js" in open(m, encoding='utf8').read())
        am = os.path.join(repo, 'dev/system/library/three/asset_manifest.php')
        ok('asset_manifest publica david-universe',
           os.path.isfile(am) and "three/scenes/david-universe.js" in open(am, encoding='utf8').read())
        h = os.path.join(repo, 'dev/catalog/controller/common/home.php')
        ok('home.php mapeia three_hero_enabled',
           os.path.isfile(h) and "'david-universe' => 'three_hero_enabled'" in open(h, encoding='utf8').read())
        d = os.path.join(repo, NOVO)
        ok('ARQUIVO NOVO LEGIVEL NO SERVIDOR', os.path.isfile(d) and os.path.getsize(d) > 10000,
           d)

        print('\n== proximos passos ==')
        print('1. Limpe o cache de opcode/Twig do CMS e recarregue com Ctrl+F5.')
        print('2. Importe tema/david_universe_3d_bo.reamurtheme.json no Admin e ATIVE o tema.')
        print('3. Abra verificar-bo.html na raiz do site (diagnostico visual, sem console).')
        print('4. Confira no Network: three-runtime/three/scenes/david-universe.js -> 200,')
        print('   Content-Type application/javascript. Um 404 aqui derruba o main.js inteiro.')

    print('\nBO instalador: %d OK / %d FALHAS' % (P, F))
    return 0 if F == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
