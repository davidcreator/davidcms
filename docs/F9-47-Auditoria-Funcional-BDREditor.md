# F9-47 — Auditoria funcional do BDREditor e correções pós-F9-46

**Status:** correções locais aplicadas e verificações estáticas direcionadas executadas. Não representa certificação completa do editor.

## Referência e escopo

- Repositório de referência: `davidcreator/davidcms`, branch `main`.
- Commit consultado: `adf0788d097238ec470962ab401a682162811e8f` (`feat(block-editor): add tabbed ribbon and translations`).
- Antes desta rodada de correções, os 12 arquivos locais comparados com esse commit coincidiam byte a byte. A comparação foi somente leitura; nada foi enviado ao GitHub.
- A auditoria separou falhas funcionais de scripts estáticos que procuram assinaturas literais antigas. As tarefas já concluídas — inclusive F9-43 e F9-46 — não foram refeitas.

## Correções incluídas

1. **Vínculo inline em áudio:** a ação de aplicar/remover link agora bloqueia `core/audio`, alinhando o editor à validação do documento e ao renderer. Antes, a interface permitia tentar uma operação que os outros níveis rejeitavam.
2. **Inserter contextual e estado do painel:** abrir por `/` ou pelos controles de inserção ativa o painel Elements, preserva o painel anterior e o restaura ao fechar/inserir. “Adicionar depois deste bloco” mantém o ID de destino. Os controles voltam a expor estado `aria-expanded` e alvo `aria-controls` coerentes. Entrar na prévia fecha o inserter para evitar estado aberto invisível.
3. **Ajuda de atalhos:** o parágrafo usa a classe CSS existente `rb-keyboard-help-text`, mantendo a ajuda associada ao estilo responsivo e ao foco visível.
4. **Mídia de vídeo no fluxo de Blog:** `prepareBlockSubmission()` agora fornece a raiz física de vídeo ao `BlockRenderer` e a verificação recursiva confirma `core/video_file` com `videoReferenceExists()`. Sem isso, um documento Blog com vídeo local podia ser rejeitado ao salvar/gerar o snapshot ou ao validar a prévia, apesar de o bloco e o picker estarem disponíveis.

Os arquivos completos atualizados são `dev/admin/view/js/blog-block-editor.js` e `dev/admin/controller/blog/blog.php`.

## Verificações executadas

### Aprovadas

- `node --check dev/admin/view/js/blog-block-editor.js`.
- `e2e_bdre_callout_static.cjs` — 34 verificações.
- `e2e_bdre_details_static.cjs` — 27 verificações.
- `e2e_bdre_navigation_static.cjs`.
- `e2e_bdre_outline_keyboard_static.cjs`.
- `e2e_bdre_preformatted_code_static.cjs` — 29 verificações.
- `e2e_bdre_spacer_static.cjs` — 36 verificações.
- `e2e_bdre_keyboard_static.cjs` — comandos, escopo de atalhos, ajuda acessível e localização.
- `e2e_f919_static.cjs`, `e2e_f922_static.cjs`, `e2e_f924_static.cjs` e `e2e_f926_static.cjs`.
- `check_single_page_ribbon.cjs` — **12/12** verificações contratuais.
- Assertivas estáticas específicas confirmaram os três pontos do fluxo de Blog para vídeo (`videoRoot`, construtor com as três raízes e `videoReferenceExists`) e os marcadores das correções de editor.
- Os testes de áudio e arquivo aprovaram as fixtures e a maior parte das validações/integrações antes de encontrar as incompatibilidades de contrato abaixo.

### Scripts que não passaram integralmente — classificação

Esses resultados não foram mascarados como sucesso. A leitura do código indica que as verificações listadas abaixo contêm expectativas de forma ou escopo que já não correspondem ao código atual; elas devem ser atualizadas antes de servirem como prova funcional:

- **`e2e_bdre_audio_static.cjs`:** as verificações de fixture, schema, caminho, renderer, traduções e integração passaram. As falhas restantes incluem a exigência literal de que o picker/modal não tenha upload nem endpoint de upload; o código atual possui upload de áudio e fluxo dedicado. Também há uma verificação rígida de passagem de raízes que não reconhece as chamadas/refatorações atuais. Não há alegação de que upload ou exclusão tenham sido testados em execução.
- **`e2e_bdre_file_static.cjs`:** fixtures, validação, renderer, upload e controles do picker passaram; falhou a verificação textual de raízes físicas. O fluxo de submissão e o código de prévia usam raízes explícitas, e esta entrega acrescenta a raiz de vídeo faltante ao fluxo de submissão.
- **`e2e_bdre_block_copy_static.cjs`:** dois marcadores esperam chamadas diretas/assinaturas antigas. O código inspecionado usa helper de botão, clona IDs, valida o documento com o contexto de mídia (`allowPageMedia`), verifica o limite JSON de 4 MiB e faz um único `commit` após a validação. A suíte foi registrada como falha, não como aprovação.
- **`e2e_bdre_hierarchical_reordering_static.cjs`:** a primeira verificação exige a assinatura antiga de três parâmetros; a implementação atual também recebe o contexto opcional `allowPageMedia`. A execução da suíte é considerada não aprovada, embora a função e seus consumidores tenham sido inspecionados.
- **`e2e_bdre_table_static.cjs`:** o marcador de HTML espera `<th scope="col">` como literal fixo. O renderer atual acrescenta o atributo de aparência filtrado ao elemento e renderiza as células com `renderInlineRuns`; a forma literal do teste não contempla essa extensão. Suíte não aprovada.
- **`e2e_bdre_unsaved_changes_static.cjs`:** classifica chamadas `fetch` e uso de `sessionStorage` como falha geral. No código atual, `fetch` atende aos endpoints de padrões e `sessionStorage` ao histórico temporário de revisões; isso não equivale ao salvamento persistente automático do documento, que continua dependendo do fluxo explícito do formulário. A asserção precisa distinguir esses usos. Suíte não aprovada.
- **`e2e_f923_static.cjs`:** falha em marcadores exatos `renderInserter()` e `this.undoStack.length > 50`; o editor chama `renderInserter` com o estado de abertura e limita os históricos por quantidade **e** peso em `pushHistory`. Suíte não aprovada.

## Limites desta validação

- QA estático/isolado em Node.js; **não** houve teste em navegador real, runtime PHP, aplicação local, staging, banco, migrações, deploy ou chamada de escrita ao GitHub.
- PHP CLI não estava disponível nesta sessão, portanto o lint PHP ficou pendente.
- A aprovação das verificações estáticas não substitui validação integrada do upload de mídia, salvamento, prévia e renderização no ambiente da aplicação.
- Não se declara que a auditoria funcional geral do BDREditor esteja concluída.
