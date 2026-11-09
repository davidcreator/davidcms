Passo 1A — Limpeza da Raiz e Artefatos de Hotfix/Limpezas Antigas
==================================================================

Conteudo do pacote:
- passo-1a-limpeza-raiz.sh

Como usar na raiz do seu clone local do davidcms:

1) Conferir (dry-run, nao altera nada):
   bash passo-1a-limpeza-raiz.sh

2) Aplicar apenas a Etapa 1A.1 (41 arquivos sem impacto no CI):
   bash passo-1a-limpeza-raiz.sh --etapa 1a1 --aplicar

3) Aplicar apenas a Etapa 1A.2 (remove install.lock, F11-*-INDICE.md e CHECKSUMS-F11-*,
   adiciona install.lock ao .gitignore, ajusta docs/README.md e atualiza CHECKSUMS.txt):
   bash passo-1a-limpeza-raiz.sh --etapa 1a2 --aplicar

   Ou tudo de uma vez:
   bash passo-1a-limpeza-raiz.sh --etapa tudo --aplicar

4) Revisar e commitar manualmente:
   git status
   git commit -m "chore: limpeza da etapa 1A (raiz e artefatos historicos)"
