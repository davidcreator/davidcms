# ADR 0064 — F9-08: Exposição pública opt-in de custom fields em páginas CMS

**Data:** 2026-10-24 · **Agente:** catalog / orquestrador.  
**Status:** implementação local para revisão; não executada em runtime.  
**Base verificada:** `davidcreator/davidcms` @ `4c79b4865db2cdf05e98a2684da4f035807f89c2`.

## Contexto

A F9-07 concluiu o salvamento administrativo de valores em CMS/page. O schema F9-02 já distingue `visibility` e define localização. Sem um leitor público, o MVP ainda não apresenta nenhum dado personalizado. A aprovação inicial determinou privado por padrão e CMS/page como primeira entidade.

## Decisão

1. Implementar leitor específico no catálogo, `custom_field/value`, sem reutilizar o Model administrativo.
2. A consulta retorna apenas página publicada, entidade `cms/page`, grupo ativo, `visibility='public'` e tipos da whitelist. A camada pública não aceita entity ID de outro módulo.
3. Valor localizado usa idioma atual e fallback en-US; valor compartilhado usa language ID zero. Labels também usam fallback en-US.
4. `boolean` é convertido para string traduzida; `select` resolve a label por igualdade exata com a opção armazenada; os demais tipos são texto literal.
5. O Twig renderiza em conteúdo HTML normal, submetido ao autoescape global. O bloco novo nunca usa `raw`, não cria link e limita o conteúdo a 5.000 caracteres.
6. A descrição HTML antiga de `cms/page` mantém seu contrato anterior; esta ADR não afirma que ela seja sanitizada por este trabalho.

## Alternativas consideradas

- **Exibir todos os campos e confiar no admin:** rejeitado; quebraria o padrão privado e exporia `admin_only`.
- **Usar `description|raw` para todos os tipos:** rejeitado; o valor de custom field é texto e não deve virar HTML executável.
- **Montar URLs clicáveis para `url`:** adiado; não necessário para MVP e exigiria validação de protocolo/atributos de segurança na saída.
- **Exibir opções select pelo valor armazenado:** rejeitado; valor é identificador técnico, o label é apresentação e a correspondência deve ser exata.

## Consequências

- Admin pode optar explicitamente por publicar cada definição por metadado de visibilidade; `admin_only` permanece excluído.
- O site só ganha novo bloco quando existem valores públicos preenchidos; páginas sem esses dados permanecem sem bloco adicional.
- A consulta depende do schema F9-02; aplicação/deploy deve aguardar validação integrada em laboratório.
- Esta tarefa não altera busca, sitemap, cache, APIs ou formulários públicos.

## Validação

`tools/e2e/e2e_f908.sh --check` verifica estaticamente filtros e escape. Não houve execução de PHP, banco, navegador ou produção.
