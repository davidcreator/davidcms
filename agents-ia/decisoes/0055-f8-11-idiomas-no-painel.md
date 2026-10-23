# ADR 0055 — F8-11: Tela de Idiomas no shell

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** inclusão escolhida explicitamente pelo usuário; implementação preparada, aplicação/homologação pendentes.
**Base:** `b3eb7262e77ef25335e21446c0f64c7017dfe1a6`.

## Contexto

O usuário declarou a Tarefa10 concluída/GitHub atualizado. Seus15 arquivos conferem com a entrega por blob Git em b3eb726, sem inferir detalhes de backend/deploy. Na triagem de templates, confirmou-se que Localization/Idiomas ainda usa layout antigo; Usuários/Webhooks/Marketplace já têm componentes F8-03 e seus confirm inline não justificam reimplementar o CRUD inteiro. Dashboard/login têm classes próprias intencionais F8-01; marcadores de classe isolados não comprovam regressão.

Como Idiomas fora excluído dos lotes anteriores, pediu-se escolha entre migrá-lo, consolidar validação ou planejarF9. O usuário escolheu **Fase8/Tarefa11 — Tela de Idiomas**, sem novos idiomas/schema/troca automática dos padrões.

## Decisão

1. Migrar lista/form de `localization/language` para titlebar/cards/tabela responsiva/feedback/readonly Bootstrap, no shell existente. Manter nomes/IDs/code imutável, metadados/status e padrões por app existentes. Sem add/delete/upload ou publicação de pacotes de idioma.
2. `getLanguages(array $options=[])` acrescenta busca nome/code/locale, status e paginação20 solicitada pelo controller. `getTotalLanguages` usa o mesmo predicado. **Sem argumentos continua completo e na ordem sort_order ASC/language_id ASC**. `getLanguage/editLanguage/getEnabledCodes` permanecem byte a byte; gate status1+diretório de admin segue como a base.
3. Separar dados da grade (`languages`) das opções completas (`language_options`). Filtrar/paginar ou esvaziar a grade não pode remover opções dos seletores de padrão. Não mudar defaults em GET, não semear valores novos; fallback en-us permanece apenas o existente.
4. Exigir CSRF em POST de padrões e edit, mantendo access/modify de localization/language e validações originais (default precisa estar habilitado; não desativar default de nenhuma app; name/locale até32; code não editável).
5. Adicionar returns explícitos após redirects. Response encerra web, mas retorna em CLI; evitar fallthrough e deixar contratos previsíveis sem alterar Response/core.
6. Corrigir callback de erros: static arrow não pode acessar `$this`. Corrigir repopulação com array_replace (a união anterior preservava o banco à esquerda) e checkbox unchecked no POST; inicializar error no GET. Preservar payload de escrita e corpos das validações de domínio.
7. Código permanece disabled/sem name; default ativo bloqueado na UI com status hidden1 para salvar metadados sem desativá-lo. Backend continua rejeitando POST adulterado que tente desativar default. Readonly tem fieldset disabled/sem Save.
8. CSS só sob `.localization-language-list`; prefixo anterior intacto, paginação com normalização local de ampersand. ASSET_V inalterado/cache documentado, sem JS runtime novo. Textos necessários nos três arquivos de idioma existentes, inclusive text_home ausente localmente.
9. f402 histórico recebe só captura CSRF da lista e token nos oito POSTs. Setup/reset/SQL/grants/agregados legados não são auditados/refatorados/executados pelo Tool novo. Não se declara a bateria real antiga aprovada.

## Tool e evidências locais

`tools/e2e/e2e_f811.sh --check`: PHP8.4.26/Twig3.30.0 reais, Node20.20.2.

- **40 PASS/0FAIL**: controller/model/Twig reais; DB/serviços/model do controller/IO sessão em fixtures, SQL capturado, Session sem construtor. Diretórios en/pt/es somente em FS temporário privado não servido, separado de MIRROR e removido ao fim. Nenhum idioma/default real alterado.
- **8 PASS/0FAIL** novos auxiliares HTTP +16F8-06 compartilhados com respostas simuladas.
- Lint6PHP/sintaxe CJS/Bash aprovados. Controle negativo na tela antiga: **4PASS/36FAIL**, saída1 esperada, não uma contagem de36defeitos independentes.
- Regressões locais F8-10/09/08/07/06/05 verdes, sem reabrir aceites nem comprovar backend/navegador real.
- Sem servidor, DB, navegador, defaults/idiomas reais, GitHub write/deploy/Actions ou f402 destrutivo executados.

`--http` é opt-in preparado em **10etapas**, ainda não executado contra backend. Requer laboratório loopback/configs literais reamur_test/marker/fontes MIRROR=FUI, conta fictícia e ID+code reais de um idioma ativo instalado **não-padrão e não en-us**. Identidade e padrões são conferidos antes de qualquer POST.

O teste altera **somente sort_order**, preservando name/locale/status/code, verifica persistência e restaura exatamente. Nunca POST no formulário de padrões, cria/desativa idioma, troca locale/code/status, executa SQL/reset ou cria/remove diretório de idioma. Falha após tentativa de gravação preserva evidências/ordem pendente para inspeção, sem retry ou restauração cega. Relatório registra ID/code/ordens/estado de restauração, não nome/locale/credenciais/cookies.

## Consequências/limites

- Clientes mutáveis precisam de csrf_token; não há bypass. Defaults só mudam por POST explícito autorizado do operador, como função preexistente, nunca pelo pacote/GET/Tool HTTP.
- Métodos de domínio/FS/persistência preservados; validação original de default não ganhou política nova de diretório no mesmo lote. Aviso de diretório ausente e gate getEnabledCodes permanecem como base.
- Não há DDL/grants/install, outros CRUDs, `system/**`, `catalog/**`, `install/**`, Composer, admin.js, SMTP ou editor do Blog alterados. Nenhum pack/diretório novo de idioma é entregue.
- Homologar campos/seletores/default-lock, mobile/temas/contraste, perfil real readonly e volume. Mudança manual de padrões/status/locale deve ser testada pelo usuário somente em laboratório, fora do HTTP automático limitado.
- Guardas não atestam DB/PHP/proxy incorreto; proibido produção/dados reais/túnel/proxy/concorrrência. Se não há alvo não-padrão elegível, não instalar idioma nem trocar default para forçar o teste.
- Entrega preparada ou publicada não encerra automaticamente Fase8.

Guia/Tool/aceite/rollback: [F8-11 — Idiomas no painel](../../docs/F8-11-Idiomas-no-Painel.md).
