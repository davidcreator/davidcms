# Three.js Admin Preview — correção da ajuda traduzida

Patch incremental para `davidcreator/davidcms` no HEAD consultado `d1c8fcba10c593142d8dfc9a495273805bf6787e`.

Este pacote corrige a dica exibida no Admin para refletir o fluxo já integrado: tanto a Prévia completa quanto a thumb pública podem executar `custom_js`, mas somente dentro do iframe sandbox opaco; o cartão de edição não executa o script.

## Arquivos e aplicação

Copie os arquivos preservando os caminhos a partir da raiz do projeto:
- `dev/admin/language/pt-br/cms/page.php`
- `dev/admin/language/en-us/cms/page.php`
- `dev/admin/language/es-eu/cms/page.php`
- `tools/e2e/check_threejs_thumb.cjs`

Os arquivos de idioma são completos, não snippets. Faça backup antes de substituir.

## Verificação

O checker atualizado passou **22/22** verificações estáticas contra o snapshot seletivo do HEAD consultado. Rode na raiz do projeto:

```sh
node tools/e2e/check_threejs_thumb.cjs
```

Esse resultado não é um teste visual de browser. Posts de Blog e páginas CMS publicados continuam indisponíveis, portanto a thumb pública não foi validada com conteúdo real. Nenhum deploy, migration, alteração de instalação compartilhada ou escrita no GitHub foi feita por este pacote.
