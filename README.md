# CMS > Páginas — mídia na prévia privada

**Base:** `main` at `91d91dfd2bf65b891afa6572eefbbd6334152246` (`feat(cms/pages): add audio/video/download support to page media`).

## O que muda

A rota de prévia não persistente `cms/page.preview` passa a configurar o `BlockRenderer` com as raízes de áudio, vídeo e downloads do Catalog e o modo de validação Pages. O renderer continua sendo a autoridade: só resolve referências locais allowlisted, verifica arquivo/MIME/limites e bloqueia symlinks. O validador Pages aceita os roots próprios e os paths Blog já salvos, apenas para compatibilidade de leitura; novos seletores e uploads não são afetados.

## Proteções mantidas

- ACL `modify cms/page`, POST e CSRF.
- Cache privado/no-store, `X-Frame-Options: DENY`, CSP e remoção de `custom_js` antes da renderização.
- Nenhum endpoint/modelo de mídia do Blog; sem alteração no Blog.
- Sem schema, migration, banco de dados, deploy, staging ou GitHub write.

## Arquivos

- `CMS-Pages-Preview-Media-Integration.patch` — patch unificado sobre o HEAD indicado.
- `tree/dev/admin/controller/cms/page.php` — versão final do único arquivo alterado.

## Verificações

O patch foi aplicado em uma cópia isolada da fonte-base e comparado com a árvore final; `git apply --check` passou. PHP CLI não está disponível nesta sessão, então PHP lint/runtime não foi executado. Nenhum teste de browser ou banco foi executado.
