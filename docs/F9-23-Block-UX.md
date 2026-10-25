# Fase 9 · Tarefa 23 — Block UX Gutenberg-like

**Base:** repositório `davidcreator/davidcms`, branch `main`, HEAD `184bff0c52b57bfbdbd43922093d21240f3fc836` (commit F9-22 verificado antes do trabalho local). F9-23 é a etapa seguinte indicada em `docs/F9-21-Roadmap-Editor-Avancado.md`.

## Escopo implementado localmente

- Inserter pesquisável por categoria com popover; insere após o irmão selecionado ou dentro de containers group/column/list, respeitando o esquema. Preserva preset de duas/três colunas.
- Comando `/` no início do texto ou após espaço abre busca; escolher um tipo remove o caractere de comando e insere um bloco irmão. Em item de lista, oferece só item irmão; não permite inserir dentro das colunas fixas.
- Toolbar de bloco contextual: mover irmão para cima/baixo, duplicar árvore com novos IDs, remover e inserir depois. Reordenação por drag/drop usa handle em qualquer nível; drops cruzando pais diferentes são ignorados. Atalhos Alt+↑/↓ e botões fornecem alternativas acessíveis.
- Transformações sem perda silenciosa entre parágrafo, título, citação e botão. Um único `href` comum vira URL do botão; URLs mistas impedem essa conversão. Ao sair de botão para texto, o destino vira `href` inline e só é permitido com conteúdo textual. Blocos de mídia/layout não oferecem transformação destrutiva nesta fase.
- Outline recursivo selecionável e sincronizado ao estado da seleção.
- Limite de undo/redo permanece 50 snapshots; ações estruturais e undo/redo têm mensagens localizadas com `aria-live`.
- Rótulos e status novos estão localizados em PT-BR, EN-US e ES-EU.

## Guardrails de estrutura

- A busca respeita o contexto da árvore; list-item só é inserido como irmão em list. A quantidade de colunas não muda por ações de bloco. A exclusão/duplicação de coluna fixa é bloqueada.
- Duplicação regenera recursivamente todos os IDs. Movimento aceita apenas irmãos; não muda hierarquia ou tipos.
- Transformação é limitada ao conjunto textual compatível e mantém os runs; atributo/links incompatíveis são removidos de modo explícito.
- F9-22 v1/v2, renderer, persistence, transação/ACL/CSRF e fluxo de salvar permanecem sem mudança. Sem migration, autosave ou HTML arbitrário.

## Arquivos alterados

O pacote contém oito fontes pontuais sob `dev/admin/` (controller, três idiomas, CSS, init JS, editor JS e Twig como referência de integração), fixtures sintéticas, checker estático, documentação e manifesto. O init/template foram incluídos para inspeção do ponto de integração; a principal UI e as strings estão no JS/controller/CSS/línguas.

## Validação e limites

- Checagens permitidas: `node --check` no editor/init/checker; parse do fixture JSON; checker estático de marcadores e traduções.
- Resultado estático: PASS; 47 chaves localizadas em 3 idiomas, 9 fixtures parseadas e chaves controller/idiomas cobertas. CSS com chaves balanceadas.
- Nenhum PHP/SQL/browser/app/E2E runtime ou banco foi executado. Nenhuma alteração no GitHub, deploy ou migration.
- Assim, interação real de foco, seleção, ARIA e drag/drop precisa validar-se futuramente em staging descartável, com autorização explícita.

## Próxima etapa sugerida

F9-24: blocos de conteúdo e mídia (galeria, cover, image caption/alinhamento, preview de embed/botão e padrões de layout), cada tipo com contrato JSON/renderer allowlist e comportamento mobile.
