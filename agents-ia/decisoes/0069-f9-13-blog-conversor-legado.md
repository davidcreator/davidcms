# ADR 0069 — F9-13: conversão HTML do Blog fail-closed

**Data:** 2026-10-24 · **Status:** biblioteca isolada preparada; runtime não executado.  
**Base:** `davidcreator/davidcms` `main` @ `9cb6e196a3d8b77008961dd1736ec77f3bc01ea7`.

## Contexto

O editor em blocos recebe descrições Blog multilíngues em HTML legado. A F9-11 determina conversão em memória ao editar, persistência só após salvar com sucesso, preservação da fonte não representável e rejeição de blocos desconhecidos. F9-12 fornece o sidecar; nenhuma dessas decisões autoriza alterar posts automaticamente.

## Decisão

1. Implementar serviço puro PHP `BlockDocument`: parser de subset, validador de documento versionado, serializer JSON canônico e decoder estrito.
2. Parser aceita apenas tags/atributos explícitos do contrato F9-13. Formatação inline, links/embeds, imagens externas/paths ambíguos, comentários, DOCTYPE/ENTITY, atributos adicionais, warning do parser ou markup desconhecido colocam o documento inteiro em revisão.
3. Estado de revisão tem `document=null` e conserva o HTML original como string exata; nunca retorna blocos parciais que poderiam apagar markup na próxima gravação.
4. Tipos são `core/paragraph`, `core/heading`, `core/list`, interno `core/list-item`, `core/quote`, `core/image` e `core/separator`. IDs são determinísticos para mesma fonte e caminho.
5. Serializer só aceita versão 1 e nós/atributos permitidos; não renderiza HTML nem faz escaping público (responsabilidade da F9-15).
6. Nenhuma chamada a banco, gravação ou alteração do controller/tela. Consumidor futuro deve manter fallback, mostrar status/preview, e só persistir numa ação explícita bem sucedida.
7. Fixtures positivas/negativas e runner PHP são incluídos, mas não executados devido à restrição de não executar testes PHP/app/browser/SQL. A gate da entrega é estática e não deve ser chamada de teste funcional.

## Alternativas

- Remover tags desconhecidas enquanto converte: rejeitado por perda silenciosa.
- Converter fragmentos arbitrários para bloco que reproduz HTML cru: rejeitado por trust boundary e renderização insegura.
- Sanitizar fontes silenciosamente: rejeitado; sanitização/renderer são tarefa distinta e exigem revisão.
- Persistir ao abrir o formulário: rejeitado pelo contrato F9-11.
- Converter imagens absolutas externas para trusted image block: rejeitado até regra de media/path guard confirmada.

## Consequências

- Muitos posts reais podem retornar `review_required`; esta é escolha deliberada em favor de preservação.
- Compatibilidade de mídia depende da Media Library/path guard e deve ser revisada antes de integração.
- A classe exige PHP DOM/libxml. Seu suporte/versão precisa de verificação no ambiente de deploy antes de rollout.
- Fixture runner e static gate não substituem syntax/runtime tests feitos pelo mantenedor em ambiente autorizado.
