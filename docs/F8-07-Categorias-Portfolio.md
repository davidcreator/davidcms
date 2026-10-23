# Fase 8 · Tarefa 07 — Categorias do portfólio

> Objetivo: migrar o CRUD existente de categorias ao padrão atual do painel, preservando hierarquia, SEO e MediaUpload; acrescentar filtros/paginação e CSRF sem schema novo.

**Data:** 2026-10-23 · **Base:** `c081c4239c25897154ed68cd556412d47cb7d094`.
**Estado:** escopo escolhido pelo usuário, implementação preparada; aplicação e homologação real pendentes. A Tarefa 06 está concluída conforme informado pelo usuário e não é reaberta.

## Sumário

1. [Escopo e contratos](#1-escopo-e-contratos)
2. [Arquivos e aplicação](#2-arquivos-e-aplicação)
3. [Tool local e evidências](#3-tool-local-e-evidências)
4. [E2E HTTP no laboratório](#4-e2e-http-no-laboratório)
5. [Aceite no navegador](#5-aceite-no-navegador)
6. [Falhas, limpeza e rollback](#6-falhas-limpeza-e-rollback)
7. [Limites](#7-limites)

## 1. Escopo e contratos

A Tarefa 07 trata **somente categorias**, não projetos, ferramentas ou mensagens. Usa fase/tarefa, sem novo subgrupo de ajuste.

### Interface

- Lista com titlebar, total filtrado, miniatura, nome, pai, status, ordenação, data e ações; tabela responsiva, empty state e confirmação no modal global.
- Busca por **nome visível** do idioma ativo/fallback EN e status; paginação server-side de **20**. Mantém filtros nos links, normaliza queries inválidas e recua da página fora do intervalo.
- Form em duas colunas: cinco campos por idioma em tabs, parentesco/status/ordenação, imagem e ações na sidebar.
- Nome EN obrigatório. O script abre a aba do **primeiro** inválido, sem impedir a validação nativa nem alternar para os outros inválidos da mesma tentativa.
- Descrição continua **textarea de quatro linhas**, sem CKEditor, mídia do Blog, jQuery, novo filemanager ou alteração do site público.
- Upload existente, com PNG/JPEG/WebP/GIF de até **2 MB**, pelo componente compartilhado `MediaUpload`. O JS agora é arquivo local, envia CSRF, usa DOM seguro para preview e emite input/change. Durante envio, desabilita upload/seletor e impede salvar; não repete request automaticamente.
- Textos completos en-us/pt-br/es-eu; controle de modify na UI e backend.

### Compatibilidade

Rotas mantidas: `catalog/category`, `.add`, `.edit|ID`, `.delete|ID`, `.upload`. Não há endpoint novo.

Mantidos `category_description[N][name|keyword|description|meta_title|meta_description]`; IDs `input-{name,keyword,description,meta-title,meta-description}-N`; `parent_id`/`input-parent`, `image`/`input-image`, `sort_order`/`input-sort-order`, `name=status`, `input-cover-file`, `btn-upload-cover`, `cover-preview`. A chave do nome é **name**, não title de páginas/blog.

**Hierarquia:** `getParentOptions()` e os métodos de persistência/SEO/relações do model não são alterados. O seletor continua completo, sem o limite de 20 da grade, e exclui self/descendentes. Delete promove **filhos diretos à raiz**, não apaga filhos/projetos; remove relações do ID excluído como antes.

`getCategories()` sem argumentos continua retornando todas as categorias em **ordem alfabética** por nome ativo/fallback EN. Só a chamada paginada ganha desempate por category_id. `sort_order` segue armazenado/usado pelos consumidores existentes, mas não vira a ordem da grade por mudança silenciosa. Filtro LIKE preserva o comportamento de curingas SQL; não se introduz fulltext/índices.

**Mudança intencional de segurança:** add/edit/delete/**upload** exigem `csrf_token` da mesma sessão, além da ACL `catalog/category`. Upload mantém JSON `{success,path,error}`; no delete sem token não se chega ao unlink. Chamadas antigas sem token precisam ser atualizadas; não há bypass.

O componente `dev/system/library/media_upload.php` permanece intacto. Sua validação, geração de nome e exclusão física não são refatoradas. Não se altera schema, SQL, grants, `admin.js`, `system/**`, `catalog/**`, `install/**`, Composer, Appearance ou Blog.

## 2. Arquivos e aplicação

### 17 arquivos de projeto

**Runtime (9):**

- `dev/admin/controller/catalog/category.php` e `dev/admin/model/catalog/category.php`.
- `dev/admin/view/template/catalog/category_list.twig` e `category_form.twig`.
- `dev/admin/view/js/catalog-category.js` (novo).
- `dev/admin/view/css/admin.css`: somente adendo `.catalog-category-list`; todo prefixo anterior preservado.
- `dev/admin/language/{en-us,pt-br,es-eu}/catalog/category.php`.

**QA/documentação (8):**

- `tools/e2e/e2e_f807.sh` (entrada), `e2e_f807_contracts.php`, `e2e_f807_dom.cjs`, `e2e_f807_http.cjs`.
- `dev/tests/e2e/e2e_regressao.sh`: apenas cinco POSTs de categoria recebem o `$CSRF` que o script já obtinha. **Não executado pelo Tool**: o script antigo recria banco.
- Este guia, `docs/README.md` (linha 18) e [ADR 0051](../agents-ia/decisoes/0051-f8-07-categorias-portfolio-no-shell.md).

Acompanhantes: `F8-07.patch`, `MANIFESTO-F8-07.json`, `SHA256SUMS`. São sete arquivos novos e dez modificados. Sem clone completo, vendor, assets do projeto inteiro, configs de ambiente, dados reais ou credenciais reais no ZIP. O script legado contém apenas suas fixtures públicas preexistentes.

### Aplicar

1. Confira `git rev-parse HEAD`: base `c081c4239c25897154ed68cd556412d47cb7d094`. Se os alvos mudaram depois, revise hashes/diff; não sobrescreva alterações concorrentes.
2. Use branch de trabalho/PR para staging e backup dos alvos, conforme o projeto. Não aplicar diretamente em produção.
3. Extraia o ZIP fora do repositório e rode `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, usando `ENTREGA` como caminho absoluto da extração:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-07.patch"
git apply --whitespace=error "$ENTREGA/F8-07.patch"
git diff --check
bash tools/e2e/e2e_f807.sh --check
```

Alternativa: copiar somente os 17 arquivos do manifesto nos caminhos exatos. Não copiar árvore inteira, vendor, usar `--delete`, reaplicar pacotes anteriores ou executar upgrades SQL.

### Cópia servida/cache

Sincronize só os alvos pelo fluxo controlado do projeto. Confirme `catalog-category.js` com HTTP 200, invalide o cache do CSS e, quando aplicável, PHP/Twig na homologação. O **ASSET_V não muda**; o CSS existente precisa de atenção ao cache. O script novo tem boot DOM-ready, não depende de JS inline.

A paginação reaproveita a library existente: o CSS extra é isolado ao módulo e o controller corrige apenas `&amp;amp; → &amp;` no HTML gerado pelo componente, com query URL-encoded. Não decodifica conteúdo arbitrário nem altera o núcleo.

Sugestão de commit:

```text
feat(admin): migrar categorias do portfólio para o shell com CSRF
```

## 3. Tool local e evidências

```bash
bash tools/e2e/e2e_f807.sh --check
bash tools/e2e/e2e_f807.sh --help
```

Requer PHP CLI **8.1+**/mbstring, Twig 3 pelo Composer do projeto, Node.js 20+ com fetch/Headers.getSetCookie e Bash. Sem instalação automática ou dependência nova da aplicação. Usa os helpers F8-05/F8-06 já publicados, sem executar seus fluxos.

Twig é carregado por padrão de `dev/system/storage/vendor/autoload.php`. `TWIG_AUTOLOAD=/caminho/absoluto/vendor/autoload.php` permite autoload local de QA e `MIRROR` permite escolher explicitamente o `dev` testado. Somente fontes/autoload confiáveis: isto não é sandbox para executar código desconhecido.

| Grupo executado pelo agente | Resultado | Natureza |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP de runtime + runner PHP. |
| Contratos PHP/Twig | **40 PASS / 0 FAIL** | PHP **8.4.26**, Twig **3.30.0** reais. DB/serviços/model do controller/IO de sessão em fixtures. |
| JS formulário/upload | **9 PASS / 0 FAIL** | Fonte real com DOM/Bootstrap/fetch simulados. |
| Auxiliares HTTP novos | **9 PASS / 0 FAIL** | Multipart, PNG, parent/select, IDs, flags e FS em simulação. |
| Auxiliares compartilhados F8-06 | **16 PASS / 0 FAIL** | Respostas/fetch simulados. |
| Controle negativo com categoria antiga | **21 PASS / 19 FAIL**, saída **1** | Falha esperada da suíte PHP/Twig. |
| Sintaxe JS/CJS/Bash | Aprovada | Regressão legada somente `bash -n`, não executada. |

Regressões locais das tarefas aceitas também passaram: **F8-06 — 30 PHP/Twig + 6 DOM + 16 helpers HTTP**; **F8-05 — 32 guardas/modelos + 18 RTE + 10 mídia simulados**, sem falhas. Isso não reabre seu aceite nem comprova backend/navegador real.

A suíte carrega classes reais do projeto, executa controller/model e compila/renderiza Twig strict com autoescape. O SQL é capturado em fixture, não executado em MariaDB. Session real é instanciado sem construtor, só com dados em memória.

`MediaUpload::guard/delete` roda de fato em PNGs de **diretório temporário privado não servido**, separado de MIRROR/FUI, removido ao final sem limpeza ampla. `store` é exercitado no caso no-file; não há `move_uploaded_file` bem-sucedido, upload HTTP ou banco reais. Isso não comprova persistência de uma instalação do CMS.

**Não chamar esses resultados de 74 E2Es reais aprovados.** Não se iniciou servidor/DB/navegador nem se alterou GitHub, produção, vendor ou Composer do projeto. Não foi executado PHPStan/PHPUnit completo, Actions ou matriz de todas as versões PHP.

## 4. E2E HTTP no laboratório

### 4.1 Pré-requisitos e segurança

`--http` faz gravações controladas: **duas categorias fictícias (raiz/filho), um PNG 2×2 e um probe temporário**. Requer laboratório local descartável já funcional, `reamur_test`, conta fictícia com access/modify `catalog/category`, idiomas en/pt-BR/es e permissões normais no diretório de imagens.

Reutiliza preflight F8-06/F8-05: B/FUI/MIRROR explícitos, loopback, configs literais do wizard com DB_DATABASE=reamur_test, DB_HOSTNAME loopback e HTTP_SERVER correspondente, marcador de descarte, fontes/assets iguais. Compara também os nove arquivos de categoria **e `system/library/media_upload.php` sem alterá-lo**. Rejeita symlinks no caminho de imagens, inclusive links pendentes.

`.f805-disposable` conserva o nome por compatibilidade das guardas; não é novo subgrupo. Criar esse marcador não transforma um banco real em descartável.

**Proibido produção, dados/credenciais reais, ambiente misto, túnel ou proxy para produção.** O operador deve garantir que B serve FUI e que o PHP usa esse banco/diretórios. Configs/nonce/hashes não atestam um backend/proxy mal configurado. Use laboratório isolado, sem usuários concorrentes ou integrações produtivas; os registros criados não podem receber vínculos externos durante o ensaio.

### 4.2 Executar

Ajuste os caminhos reais. Nunca envie senhas/tokens pelo chat nem os versione:

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd -P)/dev"

# Só após conferir configs/banco/diretórios descartáveis:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F807_HTTP='YES_WRITE_REAMUR_TEST'
read -r -p 'Usuário fictício: ' F807_USERNAME
read -r -s -p 'Senha fictícia: ' F807_PASSWORD; printf '\n'
export F807_USERNAME F807_PASSWORD
export F807_REPORT='/tmp/novo-resultado-f807.json'  # novo, ainda inexistente

bash tools/e2e/e2e_f807.sh --http
unset F807_USERNAME F807_PASSWORD ALLOW_F807_HTTP
```

O arquivo de relatório é opcional, absoluto, novo e criado com permissão 0600; nunca sobrescreve evidência anterior. Se o preflight bloquear, a saída é 2 e não há relatório de execução. Não remova guardas/desative TLS/altere configs reais para forçar verde. Config dinâmica ou DB Docker por hostname não loopback pode ser recusado e exige avaliação pontual.

### 4.3 Fluxo preparado de 14 etapas — ainda não executado pelo agente

1. Probe com nonce único em `FUI/admin/view/js/f807-probe-<nonce>.txt`, conferido por B **antes de enviar credenciais**.
2. Uma tentativa de login com CSRF, cookies/tokens em memória, sem `.jar` ou storage state.
3. Form nos três idiomas, modify, CSRF, URL de upload e IDs dos idiomas descobertos nas tabs (não presume pt/es=2/3).
4. Add sem token deve recusar sem criar o marcador próprio.
5. Upload multipart de PNG sem token deve recusar sem arquivo novo.
6. Upload válido de PNG2×2: path novo do módulo, arquivo regular sem symlink, checksum no FUI e mesmos bytes servidos pelo catalog.
7. Criar raiz desabilitada com imagem/HTML/Unicode/SEO nos três idiomas; descobrir seu ID real pelo link/nome único.
8. Criar filho próprio ligado à raiz, usando o dropdown completo.
9. Reabrir dados dos dois, verificar imagem e exclusão da própria/descendente no seletor da raiz.
10. Edit com token incorreto e POSTs self-parent/descendente devem preservar o salvo.
11. Editar raiz v2 no mesmo ID, habilitar, reabrir e conferir filtros nome/status.
12. GET delete e POST com token incorreto não podem remover categorias nem PNG.
13. Revalidar dados/ID/PNG próprios, excluir só raiz; exigir PNG removido pelo comportamento existente e filho mantido/promovido a raiz.
14. Revalidar/excluir só filho, conferir ausência dos registros e de arquivos novos do ensaio.

Não executa SQL direto, reinstalação, upgrades, seeds, drop/recreate, upload de arquivo do usuário, biblioteca de Blog ou suíte legada destrutiva. IDs nunca são fixados em 1. POSTs não são repetidos após timeout/307/308; redirects externos não recebem nova requisição/cookies. Multipart não segue redirects. Falha após tentativa de gravação preserva os artefatos conhecidos para inspeção.

O JSON tem `kind: "http-workflow"`, `status`, `marker`, `artifacts` (rootId, childId, image quando conhecidos), 14 etapas e `cleanup`. **Aceite desse fluxo:** `status=passed`, 14 etapas pass e `cleanup=complete`. Não inclui HTML completo, credenciais, cookies ou tokens de autenticação/CSRF.

`cleanup=complete` significa registros/PNG próprios removidos e probe removido; não apaga diretórios padrão que o componente cria nem dados normais de sessão/rate limit/auto-increment. Se houver outra gravação concorrente, o Tool não remove seus arquivos para obter verde.

## 5. Aceite no navegador

Usar navegador moderno com Bootstrap local, fetch e AbortSignal.timeout. O E2E HTTP **não executa JS nem comprova layout/foco/preview**.

- [ ] Lista/form no shell, responsivos desktop/mobile, claro/escuro/skins legíveis; sem overflow externo.
- [ ] Busca por nome (inclusive `0`), status, reset, total, página 2, desempate estável e recuo além do fim. Volume servidor de 21+ requer dados fictícios próprios; 45 itens foram testados apenas em fixture.
- [ ] Pai/filho/neto: dropdown completo, sem self/descendentes; nenhuma opção desaparece por paginação da grade. POST adulterado é recusado.
- [ ] Criar/editar/salvar/reabrir preserva os cinco campos por idioma, HTML/acentos/SEO, parent/image/status/sort_order. A lista continua alfabética, não vira sort_order.
- [ ] EN vazio enquanto outra aba está ativa: salvar revela/foca o primeiro inválido, inclusive com mais de um inválido, sem erro de campo não focalizável.
- [ ] Upload JPG/PNG/WebP/GIF válido, limite/tipo inválido, preview/imagem persistida e eventos; sem jQuery, CKEditor ou novo gerenciador.
- [ ] Durante upload, clique duplo não duplica e salvar/Enter não persiste prematuramente; erro vira texto, controles voltam ao estado normal e não há retry automático.
- [ ] Modal delete: cancelar preserva; confirmar envia POST+CSRF. Delete promove filhos diretos à raiz e não exclui projetos/filhos. Usar só dados fictícios exclusivos.
- [ ] Conta fictícia readonly sem Add/Delete/Save, upload desabilitado e os quatro POSTs negados no servidor. Outro perfil real não foi testado automaticamente.
- [ ] en-us/pt-br/es-eu completos; CMS/Blog/Appearance e formulário de projetos intactos. Conferir que categorias novas/antigas continuam disponíveis ao formulário de projetos, sem alterá-lo.

Confirmar a conclusão da Tarefa 07 somente depois de aplicação/homologação do usuário; não encerra a Fase 8 automaticamente.

## 6. Falhas, limpeza e rollback

Um timeout pode ocorrer **depois** de o servidor gravar. Não repetir POST às cegas. Em falha, examine o JSON e procure somente o marcador `f807-…` na UI. Confira dados/IDs/imagem antes de excluir; se IDs não foram descobertos, não os adivinhe.

Se houve upload sem associação, o JSON pode conter o path ou arquivos inesperados. Inspecione somente o arquivo exato conhecido e seu hash/conteúdo; não use wildcard, `rm -rf`, reset SQL ou limpeza de `view/image/category`. Não vincule imagem de conteúdo real/compartilhada para facilitar a limpeza. O comportamento preexistente de delete de imagem permanece; o ensaio usa arquivo novo exclusivo.

O probe é removido em `finally` somente se regular e conteúdo igual ao nonce. Se o processo for morto/probe alterado, inspecione o nome exato `f807-probe-<nonce>.txt`; não limpe `view/js` amplamente. O relatório sinaliza probeCleanup pendente quando consegue registrar a falha.

Upload continua imediato: cancelar ou substituir imagem pode deixar arquivo órfão como na base. Esta entrega não inventa coleta automática nem endpoint de remoção. Isso e eventual endurecimento global de referências/symlinks do componente são escopos separados.

Rollback de código, com patch original/backup e sem alterações posteriores nos alvos:

```bash
git apply -R --check "$ENTREGA/F8-07.patch"
git apply -R "$ENTREGA/F8-07.patch"
```

Se não aplicar, pare/revise; não force `git reset --hard`. A reversão restaura dez arquivos e remove os sete novos, sem reverter a Tarefa 06. Antes, limpe somente registros/PNG/probe próprios no laboratório; rollback de código não apaga dados/relatórios. Sincronize só os alvos revertidos e invalide cache normal.

A base antiga volta a não exigir CSRF em categorias: limitação conhecida, não recomendação de operação.

## 7. Limites

- Sem escrita remota GitHub, deploy, servidor, DB, HTTP do CMS, upload ou navegador reais nesta preparação. Tool HTTP está preparado, não homologado no backend do usuário.
- SQL/persistência reais, contraste/foco/preview nativos, relações reais com projetos e outro perfil ACL precisam de laboratório. Sem Actions/PHPStan/PHPUnit completo/matriz PHP executados aqui.
- CSS e escape de paginação são locais; aria-labels internos da library ficam como antes. Nenhuma correção global do núcleo é insinuada.
- HTML continua o contrato antigo, sem sanitização nova. MediaUpload permanece intacto e nenhuma solução global de arquivo compartilhado/órfão é declarada.
- CKEditor 4.22.1, sua segurança/licença e a biblioteca de mídia do Blog ficam separados; não se amplia seu uso.
- Publicação de código não comprova deploy. A conclusão da Tarefa 06 foi informada pelo usuário; só a igualdade dos 18 arquivos públicos foi verificada de forma independente.

Referências: [ADR 0051](../agents-ia/decisoes/0051-f8-07-categorias-portfolio-no-shell.md), [F8-06](F8-06-Paginas-CMS.md), [F8-05](F8-05-Validacao-Integrada-Blog.md) e [base pública](https://github.com/davidcreator/davidcms/commit/c081c4239c25897154ed68cd556412d47cb7d094).
