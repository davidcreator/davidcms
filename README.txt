Passo 1B — Limpeza de Codigo Morto (dev/system/) e Assets Orfaos (dev/*/view/)
==============================================================================

Conteudo do pacote:
- passo-1b-limpeza-dev.sh

Como usar na raiz do seu clone local do davidcms:

1) Simular tudo sem alterar nada (padrao DRY-RUN):
   bash passo-1b-limpeza-dev.sh

2) Aplicar apenas a Etapa 1B.1 (39 arquivos PHP/config/XML legados + ajuste
   automatico do dev/system/storage/phpstan-baseline.neon):
   bash passo-1b-limpeza-dev.sh --etapa 1b1 --aplicar

3) Aplicar apenas a Etapa 1B.2 (425 arquivos de assets JS/CSS/SCSS orfaos):
   bash passo-1b-limpeza-dev.sh --etapa 1b2 --aplicar

   Ou executar tudo junto:
   bash passo-1b-limpeza-dev.sh --etapa tudo --aplicar

4) Revisar e commitar manualmente:
   git status
   git commit -m "chore: limpeza da etapa 1B (codigo morto e assets orfaos)"
