# ADR 0057 — F9-01: Inventário e proposta de campos personalizados

**Data:** 2026-10-23 · **Agente:** orquestrador, com futuros briefings install/admin/catalog.
**Status:** transição/planejamento autorizados; **desenho e schema ainda propostos para aprovação**.
**Base:** `96158da5810262246aa2dd34ca900cf45c0ecbed`.

## Autoridade de transição

O usuário declarou a Tarefa12 concluída/GitHub atualizado. Os seis arquivos publicados conferem por blob Git com a entrega. Em pergunta explícita de transição, escolheu **“Encerrar Fase8 e iniciar F9/Tarefa01”**, confirmando o aceite consolidado como operador e autorizando somente inventário/proposta de contratos, sem runtime/tabelas/migrações.

Registra-se a **Fase8 encerrada por aceite explícito do operador**, não por check/JSON publicado ou certificação independente do agente. O modelo público F8-12 pendente não é alterado para passado nem apresentado como evidência. Dívidas e histórico dos aceites continuam preservados.

## Contexto verificado

A ADR0047 prevê F9 custom fields tipoACF como fase própria. Foram verificados 14 arquivos atuais por Git blob/SHA256, incluindo schema-base/upgradeBlog, controllers/models/formCMS, model público real `catalog/model/catalog/page.php`, modelos projeto/Blog, idiomas e CSRF.

Não foi encontrado módulo dedicado nos nomes/caminhos inspecionados ou tabela dedicada nos schemas verificados; não se afirma busca textual exaustiva de todo vendor/núcleo. CMS usa page_id/page_description e keyword no seo_url; projeto usa project_id/name/summary/tag/galeria/N:N; Blog usa post_id/title/RTE/mídia. Campos nativos e métodos atuais não são extensibilidade genérica e não devem ser substituídos.

O comentário do controller público de páginas menciona HTML confiável/sanitizado, mas o payload administrativo verificado apenas trim/limita strings: não se infere sanitização implementada desse comentário. Novo campo de texto proposto é plain-text/autoescape; não se injeta em description|raw nem se resolve a dívida antiga aqui.

## Proposta, não implementação aprovada

1. Começar **por cms/page**, menor integração e sem upload, como MVP **a aprovar**. Projetos/Blog ficam candidatos posteriores, não habilitados por este contrato. Não expandir contatos/clientes/configurações/idiomas ou dado pessoal.
2. Seis tipos propostos: text255, textarea5000 plain-text, number como decimal string exato precision18/scale6, boolean normalizado bool, url http(s) sem credenciais/rede e select único de opções definidas. Richtext/media/repeater/relationship/código/condicionais ficam fora.
3. Namespace isolado **custom_fields** e chaves snake_case estáveis até64, sem campos nativos/reservados/prototype/config. Valores não reescrevem payload/schema/IDs/SEO existentes. Omitir namespace preserva legado e não aplica required novo; essa compatibilidade e seu trade-off exigem aprovação explícita de rollout.
4. Valor compartilhado em shared; localizado em by_language por ID real instalado, resolvendo en-us pelo registry, não posição/ID1 para o sistema novo. Fallback só na ausência, não string vazia/false/zero. Canonical EN obrigatório apenas quando required e namespace presente/grupo ativo; false/zero são valores válidos. Null não significa apagar implicitamente.
5. Patch de campo/idioma omitido preserva valores; vazio explícito limpa só opcionais. Draft/desativação esconde e preserva, sem hard-delete/backfill/coleta automática. Mudança incompatível de key/type/localização pede migração explícita; não converter dado antigo silenciosamente.
6. Visibilidade inicial **admin_only**. Exposição pública exige opt-in por definição, grupo ativo, entidade publicada e posicionamento aprovado no Twig. Sem raw/eval/network fetch ou publicação automática dos valores privados.
7. Modelo lógico separado: groupDefinition, fieldDefinition, fieldValue; chaves/natural keys e cardinalidades no JSON. **Sem nome físico aprovado, DDL, FK, alteração de *_description ou implementação de tabela**. Escolha de JSON/TEXT validado versus tabelas de labels normalizadas fica para revisão install/schema.
8. Escrita futura de valores herda modify da entidade+CSRF; leitura herda access. Gestão de definições precisará de rota/permissão próprias **a aprovar** em grupo existente; não se afirma que rota/serviço/método existe. UI/erros comuns em três idiomas, labels de definição multilíngues.
9. Salvamento atômico, exclusão do pai/valores, validação real de driver/transaction API e migração/rollback precisam de plano posterior; não prescrever um beginTransaction fictício sem verificar o driver. Admin→catalog é dividido por tarefa e mesmo contrato.

## Entrega Tarefa01

Somente JSON proposto, guia/inventário/roteamento, ADR, índice e Tool Node/Bash. **Zero dev/**, runtime/SQL/DDL/migração/dependência/site/idioma/default/grant/endpoint/editor alterados. Não existe feature funcional após aplicar este ZIP.

Tool `tools/e2e/e2e_f901.sh --check` valida identidade/flags de proposta, oito aprovações pendentes, inventário14fontes por hash e32modelos de contrato em memória/FS privado. `--self-test` roda só proposta/modelos. Não usa PHP/Composer/npm, servidor/DB/browser ou fluxo mutável. PASS não aprova desenho, não implementa persistência nem é E2E de CMS real.

## Alternativas e aprovação necessária

As oito decisões pendentes no contrato: primeiro alvo/tipos; required/compatibilidade quando namespace omitido; localização/fallback; schema físico; rota/ACL de definições; exposição/posicionamento público; lifecycle/migração; estratégia de testes/rollback. O usuário pode aceitar ou pedir outra direção antes da Tarefa02. Planejamento autorizado não equivale a aceitar estas escolhas.

Pré-fila proposta: Tarefa02 desenho físico/migração **após aprovação**; Tarefa03 CRUD de grupos/definições; Tarefa04 primeiro fluxo CMS; Tarefa05 leitura/apresentação pública explícita; Tarefa06 homologação/rollout. Cada uma uma entrega/ZIP, escopos finais ainda negociáveis.

## Consequências

A transição F8→F9 fica registrada sem reabrir aceites ou fabricar checklist real. Mantêm-se dívidas separadas CKEditor/sanitização, uploads/referências/órfãos, paginação global/confirms inline, roadmap visual próprio do catalog. Não se promete resolvê-las por campos personalizados.

Guia/modelo/revisão: [F9-01 — Campos personalizados](../../docs/F9-01-Campos-Personalizados.md).
