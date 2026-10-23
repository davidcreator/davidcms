# ADR 0051 — F8-07: Categorias do portfólio no shell

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** escopo escolhido pelo usuário; implementação preparada, aplicação/homologação pendentes.
**Base:** `c081c4239c25897154ed68cd556412d47cb7d094`.

## Contexto

O usuário declarou a Tarefa 06 concluída e escolheu **Fase 8 / Tarefa 07 — Categorias do portfólio**. Os 18 arquivos da entrega anterior foram conferidos por blob Git e são iguais aos publicados em `c081c423`. Isso verifica a publicação, não substitui a homologação informada pelo usuário, cujos detalhes não foram inventados. A Tarefa 06 não é reaberta.

`catalog/category` ainda usava layout `.dash/.panel`, idiomas empilhados e confirmação inline. Add/edit/delete/upload não exigiam CSRF. O módulo já tem hierarquia, SEO global e upload compartilhado `MediaUpload`, com PNG/JPEG/WebP/GIF de até 2 MB em `catalog/view/image/category/`. Os projetos usam essas categorias, mas seu CRUD/model não entra neste lote.

## Decisão

1. Migrar somente lista/form de categorias ao padrão das tarefas F8 anteriores: titlebar, cards, tabela responsiva, status, empty state, modal de delete, tabs por idioma e sidebar de parentesco/ordenação/imagem/ações.
2. Manter rotas, `category_description[N][name|keyword|description|meta_title|meta_description]`, 15 IDs, `parent_id`, `image`, `sort_order`, `status`, IDs dos controles de imagem, nome EN obrigatório e regras de SEO. Corpo continua textarea, sem editor rico, jQuery, filemanager ou biblioteca de mídia do Blog.
3. `getCategories(array $filters = [])` adiciona filtros opcionais nome/status e recorte de 20 no controller; `getTotalCategories` compartilha joins/predicados. Sem argumentos, mantém todos os registros e a ordem alfabética por nome ativo/fallback EN. Apenas a lista paginada ganha desempate por category_id. Não ordenar a grade por sort_order: seria mudar a base. O campo continua armazenado para seus consumidores existentes.
4. Preservar **byte a byte** os métodos de persistência, relações, SEO e `getParentOptions`. Este continua chamando `getCategories()` **sem LIMIT externo**, mantendo opções completas e exclusão da própria categoria/descendentes além da primeira página. Delete continua promovendo filhos diretos à raiz e removendo relações, sem excluir projetos ou subcategorias.
5. Exigir CSRF estável em add/edit/delete/**upload**, além da ACL existente `catalog/category`. Em falha no delete, não chegar ao unlink; em upload, recusar antes de `MediaUpload::store`. O JSON `{success,path,error}` e o componente compartilhado ficam intactos. Os cinco POSTs de categoria na regressão legada passam a enviar o token já obtido.
6. Mover o JS inline para `admin/view/js/catalog-category.js`, DOM-ready e idempotente. Revelar somente a aba do primeiro campo inválido, sem cancelar a validação nativa. Upload inclui CSRF; aceita só origem local da aplicação, bloqueia redirects e repetição durante upload, não usa innerHTML para o retorno, emite input/change e impede salvar enquanto há envio pendente. Erros são texto traduzido, não HTML.
7. Acrescentar só CSS sob `.catalog-category-list`, sem alterar o prefixo anterior ou temas. Reutilizar a normalização local `&amp;amp; → &amp;` do HTML seguro de paginação, com query codificada, sem mudar `system/library/pagination.php`. ASSET_V não muda; invalidar cache do CSS.
8. Completar rótulos CMS de categoria nos três idiomas existentes, sem mexer nas telas de Localization/Appearance ou criar schema/grants/rotas.

## Tool E2E e verificação

Entrada: `tools/e2e/e2e_f807.sh`. `--check` é local; `--http` é opt-in separado.

- **40 PASS / 0 FAIL** em PHP **8.4.26**, Twig **3.30.0** (versão do lock), com controller/model/Twig e `MediaUpload` reais. DB/model do controller, serviços e IO de sessão são fixtures. SQL real é capturado, não enviado a MariaDB. Session é criado sem construtor; arquivos PNG/guard/delete usam **FS temporário privado não servido**, fora de MIRROR, removido ao final. Isso não é upload HTTP real.
- **9 PASS / 0 FAIL**: fonte JS real com DOM/Bootstrap/fetch simulados, incluindo primeiro inválido, CSRF, preview seguro, readonly e corrida salvar/upload.
- **9 PASS / 0 FAIL**: auxiliares HTTP novos, com **16 compartilhados F8-06** também verdes; cookies/redirects, multipart, PNG e guardas de FS testados em simulação/temporários.
- Lint dos seis PHP e sintaxe JS/CJS/Bash aprovados. Controle negativo com categoria original: **21 PASS / 19 FAIL**, saída 1 esperada.
- Nenhum servidor, PHP-FPM, MariaDB, navegador ou upload real iniciado. Nenhuma produção, escrita GitHub ou Action executada.

O HTTP reutiliza o Client/parser/preflight F8-06/F8-05 sem chamar os fluxos anteriores. Exige opt-in `ALLOW_F807_HTTP`, laboratório loopback/config literal `reamur_test`, conta fictícia, marcador de descarte, fontes iguais MIRROR/FUI, componente `media_upload.php` igual e diretório de imagem sem symlinks. Probe identifica o FUI antes do login.

O fluxo de **14 etapas preparado, ainda não executado contra o backend**, cria só duas categorias próprias e um PNG 2×2, descobre IDs/idiomas no HTML, confere bytes FS/HTTP, CSRF, parentesco/ciclo, salvar/reabrir, filtros, delete e filho virando raiz. Só exclui seus IDs/PNG validados. Falha preserva artefatos para inspeção; não repete POST nem executa SQL direto/reset.

## Alternativas

- Migrar projetos junto: recusado para manter um CRUD e ZIP por rodada.
- Paginar também as opções de pai: recusado; quebraria hierarquia/anti-ciclo.
- Integrar CKEditor/Blog media ou refatorar `MediaUpload`: fora do escopo.
- Corrigir a paginação global: dívida separada; CSS e escape ficam isolados neste módulo.

## Consequências e limites

- Clientes que chamam os quatro POSTs administrativos precisam de `csrf_token`; não há bypass legado.
- Upload continua imediato e só se vincula ao salvar. Cancelamento/troca pode deixar arquivos órfãos como na base; não se inventa coleta/remoção ampla. Delete de imagem permanece o comportamento preexistente; o E2E usa arquivo novo exclusivo, nunca imagem compartilhada real.
- Não há sanitização nova de HTML nem atualização/licença nova do CKEditor; seu uso não é ampliado.
- Homologar navegador/foco, preview, mobile/temas/contraste, outro perfil ACL e volume real. O preflight não atesta DB/proxy mal configurado: proibido usar produção, túnel ou proxy para produção.
- Código preparado não significa aplicação/deploy ou conclusão da Fase 8.

Guia de aplicação/Tool/aceite/limpeza/rollback: [F8-07 — Categorias do portfólio](../../docs/F8-07-Categorias-Portfolio.md).
