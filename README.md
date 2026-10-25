# BDREditor — fluxo de conteúdo separado para CMS > Páginas

Pacote incremental preparado sobre o HEAD de `main` verificado em 25/10/2026: `09210ae7913a60b87bcaf81dac9906c0b7fcb05d`.

## O que muda

- O formulário geral de CMS > Páginas passa a exibir um resumo textual curto por idioma, com acesso icon-only ao editor de conteúdo.
- Ao criar uma página, salvar os dados gerais abre o editor dedicado. Salvar o conteúdo retorna ao formulário geral.
- Os dados gerais, SEO, campos customizados, status e ordem continuam pertencendo ao formulário geral; o editor dedicado os envia como valores ocultos para preservá-los.
- O editor continua em salvamento manual, mantém o campo `description` existente e o envelope `REAMURCMS_BDRE_JSON_V2:`. Nenhuma migration ou novo campo de banco é incluído.
- Ações do editor ficam icon-only onde a ação é inequívoca; rótulos acessíveis continuam via `aria-label`, `title` e texto para leitores de tela.
- Conteúdo legado que não pode ser convertido permanece preservável/editável; documentos v2 inválidos não são sobrescritos silenciosamente.

## Arquivos

Copie os arquivos sob `dev/` para os mesmos caminhos no projeto. O pacote não contém o projeto inteiro.

## Verificação

```sh
node tools/e2e/check_page_content_flow.cjs
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-block-editor-init.js
```

O checker é estático/contratual, não um navegador real. PHP não está instalado neste ambiente; a instalação CMS, renderização Twig e round-trip real em banco precisam ser verificados em ambiente de desenvolvimento autorizado. Não execute em produção. Sem escrita GitHub, banco, migrations, staging ou deploy.

## Smoke test manual recomendado

1. Em CMS > Páginas, criar página: confirmar que a tela geral só mostra resumo e que Salvar abre o editor dedicado.
2. Criar/alterar conteúdo em cada idioma, salvar, confirmar retorno ao formulário geral e resumo atualizado.
3. Verificar que título, SEO, campos customizados, status e ordem permanecem iguais após o retorno.
4. Editar uma página legada e uma página com envelope v2; confirmar que o conteúdo permanece no campo de descrição existente.
5. Conferir navegação por teclado e nomes acessíveis dos botões icon-only.
