# BDREditor — incremento `core/audio`

Pacote cumulativo de revisão para o editor exclusivo do ReamurCMS. Acrescenta o bloco nativo `core/audio` ao pacote anterior `core/file`/download. Gutenberg permanece apenas referência conceitual: sem dependência, código ou assets Gutenberg/WordPress.

## O que entra neste incremento

- Novo bloco `core/audio` em formato v2, com referência local e rótulo rich-text editável.
- Player nativo `<audio controls preload="none">`, label acessível, sem autoplay, script, embed ou origem externa.
- Biblioteca somente leitura da raiz dedicada `catalog/view/audio/blog/`; lista somente MP3, OGG e WAV cujo MIME detectado corresponda à extensão.
- Endpoint de listagem GET protegido por ACL `modify`; ao salvar, o servidor revalida referência e arquivo real.
- Renderer público falha fechado para arquivo ausente, symlink, caminho fora da raiz ou MIME incompatível.
- Strings PT-BR, EN-US e ES-EU.
- **Não** adiciona upload, exclusão, rename, URL remota, schema, migration ou tabela. A pasta pode ser criada/provisionada manualmente e os arquivos de áudio não são incluídos no pacote.

O pacote retém as mudanças do incremento anterior de downloads (`core/file`) para facilitar revisão cumulativa; as restrições do incremento anterior permanecem descritas em `docs/BDREditor-File-Download.md`. A cópia-base foi conferida contra os fontes pontuais de `main` no HEAD `142bc89d153f49dd7adc85adf91d2b97c75f52d8` (somente leitura); este pacote local não publica nem altera o GitHub.

## Raízes e provisionamento

1. Aplicar os arquivos pontuais sob `dev/` após revisão.
2. Provisionar MP3, OGG ou WAV aprovados por você em `catalog/view/audio/blog/` por seu fluxo de release/infra. Não coloque áudio privado ou cujo uso/licença não permita publicação.
3. Confirmar permissões de leitura do processo PHP e do servidor web. O endpoint lê apenas nomes e metadados e não altera o conteúdo da pasta.
4. Cache-bust dos assets deve seguir a convenção já existente do CMS. Não foi feito deploy nem alteração remota.

`dev/catalog/view/audio/blog/index.html` é uma página neutra para reduzir risco de listagem de diretório; não inclui áudio fictício.

## Verificações

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-audio-media.js
node --check tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
python3 -m json.tool tests/fixtures/core_audio.json >/dev/null
```

Executado nesta revisão: suite áudio **49 PASS**, suite cumulativa `core/file` **48 PASS**, `node --check` nos scripts envolvidos e validação JSON das duas fixtures. O checker usa fixture sintética e executa apenas o validador JavaScript isolado em VM sem DOM/browser; além disso, procura contratos em fontes, rotas, ACL, MIME, renderer e traduções. **Não comprova** integração real no CMS, PHP, MIME do ambiente, configuração de servidor, reprodução em navegadores ou acessibilidade por leitor de tela.

## Fora do escopo / segurança

- Nenhum upload ou exclusão: evita adicionar novos vetores de escrita e impede que o editor altere acervo existente.
- Os arquivos são servidos publicamente pelo catálogo; não use a pasta para conteúdo confidencial ou áudio com direitos incertos.
- MIME/extensão e existência são checados no catálogo e no renderer, mas isso não é antivírus nem análise semântica do áudio.
- Não foram executados PHP/runtime, navegador, banco, staging, deploy, migrations, testes destrutivos ou escrita no GitHub.
- Nenhuma numeração oficial de tarefa foi inventada ou atribuída.

## Pacote

- Escopo/decisões: `docs/BDREditor-Audio-Scope.md`
- Contrato e integração: `docs/BDREditor-Audio.md`
- Fixture: `tests/fixtures/core_audio.json`
- Checker estático: `tools/e2e/e2e_bdre_audio_static.cjs`
- Manifesto SHA-256: `SHA256SUMS`
