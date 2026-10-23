# ADR 0049 — F8-05: biblioteca de mídia para capas do Blog

| | |
| --- | --- |
| **Status** | aceita |
| **Data** | 2026-10-22 |
| **Agente proponente** | admin / orquestrador |
| **Contexto** | O formulário do Blog ainda aceita uma string para a imagem de capa, sem seletor integrado. O usuário confirmou uma biblioteca de mídia baseada no filesystem, restrita ao Blog e sem schema/migração. A solução deve preservar o campo e URLs externas já aceitas, proteger operações de arquivos por ACL/CSRF e deixar o importador WordPress e os demais diretórios de mídia fora do escopo. |

## Decisão

1. A biblioteca usa exclusivamente `catalog/view/image/blog/`, com navegação por subpastas, busca/paginação server-side, upload via `MediaUpload` (whitelist MIME e limite existentes de 2 MB) e criação de pastas. Não cria tabela nem altera o schema.
2. Os endpoints ficam no controller existente `blog/blog`, herdando a ACL de `blog/blog`. A leitura requer permissão `modify`; upload, criação/exclusão de arquivos e pastas exigem POST, permissão e token CSRF. Add/edit/delete do CRUD do Blog também passam a exigir CSRF.
3. Caminhos são normalizados e confinados à raiz da biblioteca; links simbólicos e traversal são rejeitados. Imagens só podem ser excluídas quando nenhum registro `blog_post.image` as referencia; pastas só quando vazias (desconsiderando o `index.html` de proteção do CMS). O servidor revalida as condições no momento da exclusão.
4. O formulário mantém `name="image"` e `id="input-image"`, preview existente e entrada de URLs HTTP(S). A biblioteca permite selecionar uma imagem local, enviar uma nova, navegar/criar pastas ou usar uma URL externa HTTP(S).
5. Novas mensagens são adicionadas aos arquivos de idioma existentes `en-us`, `pt-br` e `es-eu` de `blog/blog`. As suítes E2E do CRUD são atualizadas para CSRF; uma suíte F8-05 cobre a biblioteca. O importador WordPress (F7-03) e diretórios fora do Blog não são alterados.

## Alternativas consideradas

1. **Reutilizar `common/filemanager.*` legado** — rejeitada: existe JavaScript legado, mas não um controller funcional correspondente; não se cria dependência em uma API ausente.
2. **Criar tabela ou metadados de mídia no banco** — rejeitada: o requisito aprovado é filesystem-only e não justifica migração.
3. **Expandir a biblioteca para todos os módulos ou integrar ao importador WordPress** — rejeitada: fora do escopo confirmado; a fronteira Blog-only reduz risco de apagar/alterar mídia não relacionada.

## Consequências

- ✅ As capas ficam selecionáveis e gerenciáveis sem mudar o contrato do banco, com busca e paginação no servidor.
- ✅ A biblioteca não grava nem exclui fora de `catalog/view/image/blog/`; arquivos em uso e diretórios não vazios são protegidos.
- ✅ URLs HTTP(S) continuam disponíveis como valor de capa, sem serem tratadas como arquivos locais da biblioteca.
- ⚠️ O E2E completo recria o banco descartável `reamur_test` e requer o ambiente local Apache/PHP/MariaDB; não deve ser executado contra dados reais.
- ⚠️ O upload continua sujeito aos limites da configuração PHP/webserver, além do limite de 2 MB validado por `MediaUpload`.

## Documentos e arquivos atualizados

- `dev/admin/controller/blog/blog.php`, `dev/admin/model/blog/media.php`, `dev/admin/view/template/blog/blog_form.twig`, `dev/admin/view/js/blog-media.js`, `dev/admin/view/js/admin.js` (preview de caminhos locais legados).
- `dev/admin/language/{en-us,pt-br,es-eu}/blog/blog.php`.
- `tools/e2e/e2e_f701.sh`, `tools/e2e/e2e_f805_blog_media.sh`.
- Nenhuma alteração em schema, `dev/catalog/**`, `dev/install/**` ou `blog/import`.

## Adendo — F8-05 · Ajuste 01 (2026-10-23)

**Base verificada:** `722f98294beaac85a75b111075222e3187abb0e7`.
**Escopo:** correção do boot do seletor, sem alterar as decisões acima.

O shell registra `blog-media.js` no `<head>` sem `defer`. A IIFE original
consultava o modal/campo antes do parsing do formulário e retornava sem
registrar os eventos. O ajuste usa uma IIFE nomeada e, enquanto o documento
está `loading`, aguarda `DOMContentLoaded` com `{ once: true }`. Nos estados
`interactive`/`complete` continua inicializando imediatamente. Não modifica
`header.twig`, `Document`, rotas, payloads, ACL/CSRF, banco ou idiomas.

- Runtime alterado: `dev/admin/view/js/blog-media.js` (somente o boot).
- Ferramentas adicionadas: `tools/e2e/e2e_f805_a01_blog_media.sh` e
  `tools/e2e/e2e_f805_a01_blog_media_dom.cjs`.
- O modo padrão é uma regressão do JS real com DOM, HTTP e Bootstrap
  **simulados**, sem dependências externas, banco ou rede. O modo backend é
  opt-in, bloqueado por confirmação/URL local/marcador, e reutiliza a suíte
  HTTP/DB F8-05 existente, que é destrutiva.
- Verificado na preparação: fonte corrigida **10 pass / 0 fail**; fonte
  anterior **4 pass / 6 fail** no controle negativo. Nenhum E2E PHP/MariaDB
  ou navegador real foi executado nesta entrega.
- `ASSET_V` foi preservado; invalidar o cache deste JS e conferir a resposta
  na Network ao aplicar em homologação.
- Aplicação, homologação real e a validação integrada F8-05 permanecem
  pendentes. CKEditor fica para o Ajuste 02, fora deste pacote.

Guia: [F8-05-A01 — Inicialização da biblioteca de mídia](../../docs/F8-05-A01-Inicializacao-Biblioteca-Midia.md).

## Fase 8 · Tarefa 05 — validação integrada (2026-10-23)

**Base conferida:** `f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a`. O usuário confirmou a aplicação dos passos anteriores e a consulta pública confirmou a presença de ambos na `main`, com o `admin.js` idêntico ao pacote de configuração do editor. Isso confirma fontes publicadas; não é prova de deploy/homologação de navegador ou persistência.

**Organização:** a continuidade usa somente **Fase 8 / Tarefa 05**, conforme orientação do usuário. Nomes A01/A02 anteriores permanecem como histórico/compatibilidade; não são criados novos níveis de ajuste nem renomeados arquivos já aceitos.

### Ferramenta de continuidade

- Entrada única: `tools/e2e/e2e_f805.sh`. `--check` executa guardas/contratos locais e reutiliza as regressões já aplicadas; não roda a suíte legada destrutiva.
- Preparação local: `tools/e2e/e2e_f805.cjs`. Sem bibliotecas externas, HTTP ou execução de PHP/SQL. Opt-in explícito, loopback, marcador de descarte, configs admin/catalog literais `reamur_test` e hashes MIRROR/FUI são obrigatórios para preparar/limpar dois QA temporários.
- Fluxo assistido: `tools/e2e/e2e_f805_browser.js`, carregado temporariamente no navegador nativo já autenticado do laboratório. Carregar o Tool não executa o fluxo; um segundo comando explícito autoriza a gravação. A permissão dura 15 minutos e o navegador confere os hashes dos assets recebidos antes dos testes.
- O fluxo verifica editor real/Source, três idiomas, upload/seleção/preview, submit/FormData nativo sem `updateElement()` manual, criar/editar o mesmo post, reabrir/recarregar, CSRF negativo e proteção de capa referenciada. Ao passar, remove apenas seu post/PNG/pasta, com ID descoberto e propriedade conferida. Em falha, preserva para inspeção e nunca presume ID, varre mídia, executa SQL, apaga diretórios recursivamente ou repete POST automaticamente.
- Não requer Playwright/npm/Composer/CDN. Não modifica código da aplicação, vendor, schema, rotas, ACL/CSRF, importador ou licença/versão do editor. Os dois arquivos QA são temporários de homologação e não devem ser versionados/publicados permanentemente.

### Evidência e estado

Preparação local: **32 PASS / 0 FAIL** em guardas/modelos do Tool; regressões existentes **18 PASS / 0 FAIL** RTE e **10 PASS / 0 FAIL** mídia, todas com as limitações de simulação declaradas. Fontes pontuais comparadas byte a byte com a base. **Não houve navegador/DOM nativo/core real, PHP/HTTP/banco, upload/persistência ou deploy executado pelo agente nesta entrega.**

A primeira execução real do Tool novo ainda depende do laboratório/sessão do usuário. O relatório `F8-05-resultado-<identificador>.json`, com `kind=native-browser-workflow`, todas as etapas aprovadas e limpeza completa, é a evidência solicitada para esse fluxo — não deve ser confundido com os testes locais. Layout/acessibilidade, idiomas da interface, fallback e papéis ACL complementares têm checklist no guia. Os configs/nonce/hashes são guardas operacionais de um laboratório confiável, não atestação do banco ativo de um proxy nem isolamento contra o administrador do servidor.

**Tarefa 05 permanece em validação; Fase 8 não encerrada.** Suporte/segurança/licença do CKEditor 4.22.1 e sanitização do HTML continuam dívidas separadas, sem suprimir avisos de versão.

Guia: [F8-05 — Validação integrada do Blog](../../docs/F8-05-Validacao-Integrada-Blog.md).
