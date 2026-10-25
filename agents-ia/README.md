# Instruções para agentes — BDREditor cumulativo

- Produto-alvo: editor nativo exclusivo do ReamurCMS chamado BDREditor. Gutenberg é referência apenas; não importar pacote, código ou assets.
- `core/file` permanece limitado à biblioteca dedicada de downloads e ao seu escopo documentado.
- `core/audio`: somente MP3/OGG/WAV locais preexistentes em `catalog/view/audio/blog/`; picker GET somente; sem upload, exclusão, rename, origem remota, script ou embed.
- Preservar validação/renderer server-side. Referências locais estritas; nada de traversal, HTML arbitrário ou atributos livres.
- Não modificar o helper global de imagem. Manter ACL `modify` e comportamento de uploads existentes fora deste escopo.
- Nenhum banco/schema/migration, operação GitHub, deploy ou staging nesta etapa.
- Não executar PHP/SQL/browser/app/runtime; usar o checker estático `node tools/e2e/e2e_bdre_audio_static.cjs` e verificações source-only.
- Não receber credenciais por chat, não incluir dumps privados nem logs runtime. Riscos de versionar logs devem ser sinalizados.
- Novos tipos, limites, mutações de áudio, persistência compartilhada ou endpoints adicionais exigem escopo/aprovação; não inferir.
