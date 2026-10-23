# ADR 0053 — F8-09: Ferramentas conectadas no shell

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** continuidade solicitada; implementação preparada, aplicação/homologação pendentes.
**Base:** `175da8e75d6b076bcce23b6a7ac939ce72145caa`.

## Contexto

O usuário declarou a Tarefa 08 concluída/GitHub atualizado. Os 17 arquivos conferem por blob Git com a entrega em `175da8e`; isso confirma publicação, não inventa detalhes de homologação/deploy. O próximo módulo prioritário ainda legado é `tool/tool`, cadastro de ícones/links de ferramentas conectadas; não se implementam Fluxa/Quotia/Vertex/Solis nem APIs externas neste lote.

O CRUD possui apenas name/description por idioma, vendor64, URL opcional http(s), status/sort_order e ícone via MediaUpload. Sem SEO/página própria, galeria, categorias ou editor rico. Métodos de persistência/validação já existentes devem ser preservados.

## Decisão

1. Migrar lista/form ao padrão F8: titlebar/cards, tabela responsiva, modal delete, tabs, sidebar de dados/ícone/ações, estados vazios e três idiomas completos.
2. Preservar rotas `tool/tool`, `.add`, `.edit|ID`, `.delete|ID`, `.upload`; os seis IDs/names `tool_description[N][name|description]`, campos vendor/url/image/status/sort_order e controles `input-icon-file`, `btn-upload-icon`, `icon-preview`, `input-image`. Descrição continua input text255, não textarea/editor. Nome EN obrigatório e128; vendor64/URL255 permanecem.
3. `getTools(array $filters = [])` adiciona filtros opcionais nome/fornecedor/status e recorte de20 solicitado pelo controller; `getTotalTools` usa os mesmos joins/predicados. Sem argumentos continua lista completa em `sort_order ASC, tool_id ASC`. Preservar exatamente COALESCE de nome/descrição, sem introduzir NULLIF: tradução vazia ativa continua vazia, só NULL cai em EN como antes.
4. Exigir CSRF estável nos quatro POSTs além da ACL `tool/tool`; delete inválido não chega ao unlink e upload recusa antes do MediaUpload. JSON success/path/error e componente compartilhado ficam intactos.
5. Externalizar o JS em `admin/view/js/connected-tools.js`, DOM-ready/idempotente. Revelar só a tab do primeiro inválido sem cancelar validação nativa. Upload inclui token, same-origin, não segue redirects/retries, impede salvar/duplo envio durante pendência, atualiza hidden/events e preview DOM seguro, sem innerHTML. Manter object-fit-contain para não recortar ícones.
6. CSS extra somente sob `.connected-tools-list`; prefixo anterior e ASSET_V intactos. Corrigir apenas o duplo ampersand na paginação gerada, como nas tarefas anteriores, sem tocar library/núcleo. Invalidação de cache é documentada.
7. Atualizar pontualmente `dev/tests/e2e/e2e_f213.sh`: CSRF do login, token no form e oito mutações de ferramenta, contagem das três tabs em lugar de legends. Setup/reset/SQL legado ficam intactos e não são executados pelo Tool novo.
8. Sem schema/grants/migrações, site público, outros CRUDs, admin.js, Composer ou CKEditor/Blog media novos.

## Tool e verificação

Entrada `tools/e2e/e2e_f809.sh`, padrão `--check`. PHP8.4.26, Twig3.30.0 (versão do lock), Node20.20.2:

- **36 PASS / 0 FAIL**: controller/model/Twig reais, DB/serviços/model do controller/IO de sessão em fixtures e SQL capturado. Session sem construtor; MediaUpload guard/delete somente em FS temporário privado não servido, separado de MIRROR e limpo. Não comprova upload HTTP/banco real.
- **9 PASS / 0 FAIL**: fonte JS com DOM/Bootstrap/fetch simulados, incluindo foco lógico, token, readonly, DOM seguro/contain, corrida salvar/upload e sem retry.
- **8 PASS / 0 FAIL** auxiliares HTTP novos + **25 compartilhados** F8-07/F8-06, em simulação/FS temporário.
- Lint6PHP e sintaxe JS/CJS/Bash aprovados; controle negativo na ferramenta original: **17 PASS / 19 FAIL**, saída1 esperada.
- Regressões locais F8-08/F8-07/F8-06/F8-05 verdes, sem reabrir seus aceites ou confundir modelos com backend real.
- Sem servidor, DB, navegador, HTTP/upload reais, URL externa aberta, produção, escrita GitHub ou Actions.

`--http` é opt-in separado, preparado em **13 etapas**, ainda não executado contra o backend: loopback/configs literais reamur_test/marker/fontes e MediaUpload iguais/sem symlinks; probe identifica FUI antes do login; conta fictícia com modify tool/tool; uma ferramenta/PNG exclusivos; i18n/CSRF, vendor/URL vazia→HTTPS, salvar/reabrir/status/filtros e delete próprio. IDs/idiomas são descobertos no HTML. Só helpers/transportes anteriores são usados, sem executar seus fluxos. Sem SQL direto/reset/seed/limpeza ampla/retry de POST. A URL cadastrada nunca é aberta pelo Tool.

## Consequências e limites

- Clientes dos quatro endpoints mutáveis precisam de csrf_token; sem bypass legado.
- Regra de URL permanece **prefixo http(s):// ou vazio**, não FILTER_VALIDATE_URL, DNS ou teste de serviço online. Não há endurecimento silencioso, SEO ou editor novo.
- Upload imediato/órfãos/cancelamento/troca/exclusão física permanecem a base. Teste usa PNG novo exclusivo; nunca imagem compartilhada real. Saneamento global do componente é escopo separado.
- Homologar foco/preview, mobile/temas/contraste, outro perfil ACL, volume real. Guardas não atestam backend/proxy/PHP/diretórios mal configurados; proibido produção/dados reais/túnel/proxy para produção.
- Suíte f213 permanece destrutiva, não homologada integralmente aqui; mudanças somente de compatibilidade UI/CSRF. Não se declara sua bateria real aprovada.
- Entrega preparada/publicação não comprova aplicação, nem encerra a Fase8.

Guia, aplicação, limpeza e rollback: [F8-09 — Ferramentas conectadas](../../docs/F8-09-Ferramentas-Conectadas.md).
