# ADR 0079 — Temas do Catalog como ferramenta Admin separada

- **Status:** Aceita para F10-02
- **Data:** 2026-10-27

## Contexto
Pages administra conteúdo editorial e Blog administra posts. Aparência do Catalog precisa de ferramenta separada, com permissões próprias e sem editar código de aplicação.

## Decisão
1. Criar rota Admin `design/theme` com ACL separada.
2. Persistir tokens visuais e identificador de cena declarativa allowlisted; não aceitar paths ou scripts enviados pelo navegador.
3. Versionar snapshots para auditoria. Publicação é explícita e registrada.
4. Código Twig arbitrário não é executável via editor visual. Edição Twig, preview sandboxado e rollback de fontes permanecem requisito de entrega subsequente; nenhuma fonte do núcleo é gravável pela ferramenta.
5. Three.js continua sob ADRs 0072–0074: cenas e componentes aprovados, sem JavaScript livre.

## Consequências
Migrations são arquivos locais e não foram executadas. O primeiro incremento entrega gestão de tokens/cenas e revisão básica. A seleção de tema ainda não integra os templates públicos ativos; a ativação no Admin é persistida e versionada, mas o runtime/preview/fonte Twig ainda precisam ser concluídos antes de declarar F10-02 plenamente aceito.
