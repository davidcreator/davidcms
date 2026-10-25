# BDREditor `core/audio` — escopo e decisões

## Dentro do incremento

Adicionar um bloco funcional de áudio nativo ao editor de conteúdo do Blog, com seleção de arquivos MP3/OGG/WAV preexistentes e locais. O usuário pediu um próximo incremento após `core/file`, com fontes pontuais, ferramenta/checker estático, fixture, documentação e ZIP.

## Decisões explícitas

- Pasta dedicada: `catalog/view/audio/blog/`.
- Acervo é provisionado fora do BDREditor por processo operacional já existente.
- Picker é somente leitura; nenhuma inferência de upload ou exclusão.
- Player HTML nativo com controles, sem autoplay, scripts de reprodução, embeds ou origens externas.
- MIME e extensão devem corresponder à allowlist; existência e caminho são validados no servidor.
- Sem migration, alteração de schema, persistência compartilhada, deploy, staging ou escrita no GitHub.
- Não atribuir número oficial de tarefa sem aprovação.

## Fora do escopo

- Captura/microfone, gravador, conversão/transcodificação, playlists, waveform, autoplay, looping, download forçado, links remotos, upload, exclusão, edição de metadados ou sincronização.
- Site Editor, temas, header/footer, colaboração ou dependência/asset Gutenberg.
- Garantia de reprodução para todo navegador/codec, antivírus ou classificação legal/licença do acervo.

## Aceite estático

- `core/audio` validado somente em formato v2; atributos e conteúdo allowlisted.
- Origem externa, traversal, extensão indevida, link inline e arquivos inválidos rejeitados.
- Picker protegido por ACL, GET somente, listagem local e sem mutação.
- Renderer emite player acessível e sem autoplay/scripts quando o asset é validado.
- Strings nos três idiomas; fixture e checker isolado no pacote.
- ZIP, SHA-256 e manifestação de limites de verificação.
