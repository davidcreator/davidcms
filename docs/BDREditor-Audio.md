# BDREditor `core/audio` — contrato e integração

## Contrato de bloco

- Tipo: `core/audio`.
- Formato: somente `format_version: 2`.
- Atributos allowlisted: apenas `src`.
- `src`: caminho relativo com forma `view/audio/blog/<nome>`, basename ASCII allowlisted, sem ponto duplo, query, fragmento, barra invertida, whitespace ou origem absoluta; extensão `mp3`, `ogg` ou `wav`.
- `content`: lista de inline runs v2 válida e com rótulo visível; links inline são proibidos.
- Sem children.

Exemplo estrutural (o ficheiro do exemplo não está incluído):

```json
{
  "format_version": 2,
  "blocks": [{
    "id": "audio-1",
    "type": "core/audio",
    "attributes": {"src": "view/audio/blog/faixa.mp3"},
    "children": [],
    "content": [{"text": "Ouça a faixa", "marks": [], "href": null}]
  }]
}
```

O schema de bloco valida estrutura e caminho. Além disso, o controller admin verifica que o destino é arquivo existente local com MIME permitido antes de persistir conteúdo. O renderer público valida de novo path, raiz física, symlinks e MIME; sem arquivo válido, não emite markup do player.

## Renderização

Markup fixo do renderer:

- `<figure class="rms-block rms-block-audio">`;
- `<audio controls preload="none" aria-label="...">`;
- `<source>` com URL local resolvida pelo `catalogAssetUrl()` e MIME determinado pela extensão já validada;
- `<figcaption>` com runs renderizados pela allowlist inline.

Sem autoplay, atributos livres, script, iframe, embed ou URL controlada externamente. O editor também oferece preview com controles nativos e `preload="none"`.

## Biblioteca local

- Raiz: `catalog/view/audio/blog/`.
- Somente listagem GET; ACL `modify` exigida.
- Apenas arquivos regulares de primeiro nível são considerados; symlink, diretório, arquivo oculto, `index.html`, extensão não permitida ou MIME divergente são ignorados.
- Sem rota/modelo de upload, exclusão ou rename de áudio.
- Respostas não incluem conteúdo binário; seleção devolve caminho relativo dentro da raiz.

A pasta é pública no catálogo. Provisionamento e controle de direitos são responsabilidade do processo operacional do projeto.

## Compatibilidade e dependências

- Compatível com o estado `main` conferido em `142bc89d153f49dd7adc85adf91d2b97c75f52d8` e com o incremento `core/file` imediatamente anterior.
- Usa `BlockDocument` e `BlockRenderer` existentes, adicionando a raiz física de áudio ao construtor em admin e catálogo.
- Não altera schema de persistência, migration, tabelas ou formato de armazenamento.
- Scripts novos/atualizados são locais; no catálogo o player é HTML nativo. A rota está sujeita a sessão e ACL existentes do admin.

## Testes e limitações

A fixture cobre documento v2 válido e rejeições de origem externa, traversal, extensão não permitida, link inline e v1. O checker testa o validador JS em VM sem DOM e inspeciona contratos dos arquivos. Não testa runtime PHP/DB, MIME em produção, navegação completa do CMS, política HTTP, leitor de tela ou reprodução real. Staging segue pausado.
