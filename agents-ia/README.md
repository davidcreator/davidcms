# Contexto de agentes — BDREditor CMS > Páginas

- Fonte upstream consultada: branch `main`, HEAD `5ced14b6c1ca412a7a39f6b3ee73bf22c8b9b1f5`.
- Escopo: editor de conteúdo em CMS > Páginas; sem Site Editor, tema, colaboração, deploy, migration ou escrita no banco.
- Persistência autorizada: prefixo `REAMURCMS_BDRE_JSON_V2:` mais documento `format_version: 2`, no campo existente `page_description.description` (`MEDIUMTEXT`).
- A validação server-side precede a persistência. O catálogo renderiza apenas envelopes válidos usando `BlockRenderer`; envelopes inválidos falham fechados.
- Não converter destrutivamente conteúdo legado: converter apenas se o parser estrito aprovar; senão manter textarea original. Conteúdo marcado inválido permanece preservado e não é enviado cru ao Twig.
- Não baixar/clonar o projeto inteiro. Atualizações upstream devem copiar fontes pontuais e verificar hash/HEAD read-only.
- PHP runtime não estava disponível; browser real e save/reopen também não foram testados. Não declarar integração runtime concluída antes dessa validação.
- Áudio/arquivo/media continuam sem upload/deleção nesta tarefa. Seletores da biblioteca Blog não são montados em Pages; campos de caminho local são manuais.
