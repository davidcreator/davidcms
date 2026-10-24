# Orientação para agentes — F9-16

## Fonte e limites

- Projeto: `https://github.com/davidcreator/davidcms`, branch `main`.
- HEAD consultado antes da continuação: `a0515fe2734cd0b1851f545701b34d6f135cdbdb` (`feat(blog): add allowlisted block renderer support`).
- O pacote contém apenas arquivos pontuais sob `dev/`; não baixe nem clone o projeto inteiro.
- Não faça GitHub write, deploy, alteração no banco, execução de migrations ou testes PHP/SQL/browser/app.
- Não presuma que F9-12 esteja aplicada. Não abra, copie ou mencione dumps privados, especialmente `bd-davidalmeida.sql`.
- Preserve backups, hashes e o HTML legado; falha na leitura da tabela sidecar não significa tabela ausente.

## Revisão antes de qualquer integração

1. Compare somente os caminhos deste pacote com o HEAD verificado.
2. Revise o protocolo POST por idioma entre controller e Twig, incluindo os campos `blog_block_mode`, `blog_blocks` e `blog_editor_ready`.
3. Revise transação/rollback e backup do HTML legado no model.
4. Confirme a segurança dos caminhos locais de imagem e a interação com a Biblioteca de Mídia.
5. Consulte `docs/F9-16-Editor-Admin-Blog-Blocos.md` e o checklist `tools/e2e/README.md`.
6. Só depois de autorização explícita e ambiente descartável apropriado, planeje testes/migration; fora do escopo desta entrega.
