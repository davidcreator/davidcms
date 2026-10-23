# Fase 8 · Tarefa 08 — Projetos do portfólio

> Objetivo: migrar o CRUD existente de projetos ao shell atual, preservando categorias N:N, capa/galeria/SEO e acrescentando filtros/paginação e CSRF, sem schema novo.

**Data:** 2026-10-23 · **Base:** `a9aa816383fc5c40ce39415fb2b9f3257ba9597c`.
**Estado:** continuidade solicitada pelo usuário; pacote preparado e testado localmente. Aplicação/homologação real pendentes. A Tarefa 07 foi informada como realizada com sucesso e não é reaberta.

## Sumário

1. [Escopo e contratos](#1-escopo-e-contratos)
2. [Arquivos, aplicação e cache](#2-arquivos-aplicação-e-cache)
3. [Tool local e evidências](#3-tool-local-e-evidências)
4. [E2E HTTP no laboratório](#4-e2e-http-no-laboratório)
5. [Aceite no navegador](#5-aceite-no-navegador)
6. [Falhas, limpeza e rollback](#6-falhas-limpeza-e-rollback)
7. [Limites](#7-limites)

## 1. Escopo e contratos

Um CRUD e ZIP nesta rodada: **projetos**. Não avança ferramentas/mensagens, não cria subgrupo de ajuste, não altera a categoria entregue ou o site público.

### Interface

- Lista com titlebar, total filtrado, capa, cliente, destaque/status, ordenação/data, tabela responsiva, empty state e confirmação no modal global.
- Filtros por nome visível (idioma ativo/fallback EN), status e destaque; paginação server-side de **20**, preservação nos links e recuo da página além do fim.
- Form em duas colunas: sete campos/idioma em tabs e sidebar de cliente/URL/flags/ordenação/categorias/capa; galeria com alt e sort_order.
- Nome EN obrigatório. JS abre somente a tab do primeiro inválido, sem cancelar validação nativa.
- Upload existente pelo `MediaUpload`, mesmo MIME/limite **2 MB**. Capa e galeria incluem CSRF; preview/labels por DOM seguro, sem innerHTML ou jQuery. Durante envio impede salvar/remover/upload concorrente; restaura controles sem retry.
- Índices da galeria partem do máximo existente e crescem, sem reutilizar chaves removidas. Clique em botão/ícone remove a linha local, não arquivo físico.
- Textos en-us/pt-br/es-eu completos. Corpo continua **textarea de seis linhas**, sem editor rico ou biblioteca do Blog.

### Preservado

Rotas `catalog/project`, `.add`, `.edit|ID`, `.delete|ID`, `.upload`. Os 21 IDs `input-{name,summary,description,tag,keyword,meta-title,meta-description}-N`; names `project_description[N][name|summary|description|tag|keyword|meta_title|meta_description]`; `client`, `url`, `image`, `status`, `featured`, `sort_order`; `project_category[]`; `project_image[N][image|alt|sort_order]`; controles `input-image`, `input-cover-file`, `cover-preview`, `[data-upload]`, `input-gallery-file`, `btn-add-image`, `gallery`, `[data-row]`, `[data-img]`, `[data-thumb]`, `[data-remove]`.

`getProjects()` sem argumentos retorna todos na **ordem original `sort_order ASC, project_id DESC`**, não alfabética nem ID crescente. As categorias do form continuam completas/sem LIMIT da grade. Galeria reabre por `sort_order ASC, project_image_id ASC`. Métodos de persistência/relações/SEO do model e normalização/validação do controller são preservados byte a byte.

**Mudança de segurança intencional:** add/edit/delete/**upload** passam a exigir `csrf_token` da mesma sessão, além da ACL `catalog/project`. Delete sem token não chega a remover imagens. Upload mantém JSON `{success,path,error}`. Três requests legados afetados foram atualizados; clientes externos precisam enviar token, sem bypass.

Não se altera schema/grants/SQL de instalação, `MediaUpload`, `system/**`, `catalog/**`, `install/**`, categorias, CMS, Blog, Appearance/Localization, `admin.js`, ASSET_V ou Composer do projeto.

## 2. Arquivos, aplicação e cache

### 17 arquivos de projeto

**Runtime (9):**

- `dev/admin/controller/catalog/project.php` e `dev/admin/model/catalog/project.php`.
- `dev/admin/view/template/catalog/project_list.twig` e `project_form.twig`.
- `dev/admin/view/js/catalog-project.js` (novo).
- `dev/admin/view/css/admin.css`: só adendos `.catalog-project-list` e `.catalog-project-form`; prefixo inteiro anterior intacto.
- `dev/admin/language/{en-us,pt-br,es-eu}/catalog/project.php`.

**QA/docs (8):**

- `tools/e2e/e2e_f808.sh`, `e2e_f808_contracts.php`, `e2e_f808_dom.cjs`, `e2e_f808_http.cjs`.
- `dev/tests/e2e/e2e_regressao.sh`: só três requests de projeto recebem o `$CSRF` já obtido. **A suíte antiga recria banco e não é executada pelo Tool novo.**
- Este guia, índice `docs/README.md` (linha 19), [ADR 0052](../agents-ia/decisoes/0052-f8-08-projetos-portfolio-no-shell.md).

Sete novos/dez modificados, mais `F8-08.patch`, `MANIFESTO-F8-08.json`, `SHA256SUMS`. Sem projeto inteiro, vendor, configs/credenciais/dados reais no ZIP; o script legado conserva suas fixtures públicas anteriores.

### Aplicação

1. Confira base `a9aa816383fc5c40ce39415fb2b9f3257ba9597c` com `git rev-parse HEAD`. Se alvos mudaram depois, revisar hashes/diff; não sobrescrever trabalho concorrente.
2. Usar branch/PR para staging e backup dos alvos, conforme as convenções. Não aplicar direto em produção.
3. Extrair ZIP fora do repositório e conferir `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, com `ENTREGA` apontando à extração absoluta:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-08.patch"
git apply --whitespace=error "$ENTREGA/F8-08.patch"
git diff --check
bash tools/e2e/e2e_f808.sh --check
```

Alternativa: copiar apenas os 17 alvos do manifesto. Sem árvore inteira, vendor, `--delete`, reaplicar ZIPs anteriores ou upgrades SQL.

Sincronizar apenas os alvos pelo fluxo controlado, conferir novo `catalog-project.js` HTTP 200 e invalidar cache CSS/PHP/Twig na homologação. **ASSET_V não é alterado**, portanto conferir cache de `admin.css`. Alteração em dev/GitHub não comprova deploy.

Paginação usa a library existente: normalização local de `&amp;amp;` no HTML gerado, com query URL-encoded, e CSS isolado. Não decodifica conteúdo arbitrário nem muda a library/núcleo. A lista de categorias ganha rolagem local, não paginação.

Commit sugerido:

```text
feat(admin): migrar projetos para o shell com galeria e CSRF
```

## 3. Tool local e evidências

```bash
bash tools/e2e/e2e_f808.sh --check
bash tools/e2e/e2e_f808.sh --help
```

Requer PHP CLI **8.1+**/mbstring, Twig 3 do Composer do projeto, Node 20+ com fetch/FormData/Blob/Headers.getSetCookie e Bash. Helpers anteriores da base são usados sem alterar seus arquivos nem executar os fluxos anteriores. Nenhuma instalação automática/dependência nova do CMS.

Twig padrão: `dev/system/storage/vendor/autoload.php`. Pode usar `TWIG_AUTOLOAD=/autoload/absoluto/local/vendor/autoload.php` para QA isolada e `MIRROR` para escolher o dev testado. Somente fontes/autoload confiáveis: não é sandbox para código desconhecido.

| Grupo executado pelo agente | Resultado | Natureza |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP de runtime + runner. |
| Contratos PHP/Twig | **45 PASS / 0 FAIL** | PHP **8.4.26**, Twig **3.30.0** reais; DB/serviços/model do controller/IO sessão em fixtures. |
| JS/DOM/upload/galeria | **12 PASS / 0 FAIL** | Fonte real, DOM/Bootstrap/fetch simulados. |
| Auxiliares HTTP novos | **10 PASS / 0 FAIL** | Parser N:N/galeria/IDs, PNG, flags e guardas FS em simulação/temporários. |
| Auxiliares compartilhados F8-07/F8-06 | **25 PASS / 0 FAIL** | Fetch/respostas simulados. |
| Controle negativo com projeto anterior | **25 PASS / 20 FAIL**, saída **1** | Falha esperada na suíte PHP/Twig. |
| Sintaxe JS/CJS/Bash | Aprovada | Regressão legada apenas bash -n. |

Regressões locais também passaram sem falhas: **F8-07 (40 PHP/Twig + 9 DOM + 25 auxiliares)**, **F8-06 (30 PHP/Twig + 6 DOM + 16 auxiliares)** e **F8-05 (32 guardas/modelos + 18 RTE + 10 mídia simulados)**. Isso não reabre o aceite anterior nem comprova backend/navegador real.

Classes reais são carregadas, controller/model executados e Twig strict/autoescape compila/renderiza templates. SQL é capturado, não enviado a MariaDB; Session real é criado sem construtor, apenas dados em memória. `MediaUpload::guard/delete` roda em PNGs de **FS temporário privado não servido**, fora de MIRROR/FUI e limpo ao final; store só no-file. Não houve move_uploaded_file/upload HTTP bem-sucedido ou persistência de uma instalação real.

**Não chamar esses 92 casos de E2Es reais aprovados.** PHP CLI/Twig de QA foram preparados isoladamente; sem instalar CMS/DB/servidor, baixar projeto inteiro ou alterar vendor/Composer do projeto. Não houve navegador, contraste computado, Actions/PHPStan/PHPUnit completo ou matriz PHP nesta preparação.

## 4. E2E HTTP no laboratório

### Segurança/pré-requisitos

`--http` grava: **um projeto fictício, uma categoria auxiliar própria e três PNGs 2×2** (capa e duas galerias), além de probe temporário. A categoria auxiliar evita usar/excluir categoria alheia e permite testar N:N mesmo se o laboratório estiver vazio.

Requer laboratório local descartável, `reamur_test`, idiomas en/pt-BR/es e conta fictícia com **access/modify em catalog/project E catalog/category**. Não altera ACL/grants para obter acesso. Antes de gravar verifica Save nos dois módulos; faz login uma única vez, sem retry.

Herda guardas F8-07/F8-06/F8-05: B/FUI/MIRROR explícitos, loopback, configs literais wizard com DB_DATABASE=reamur_test/DB_HOSTNAME loopback e HTTP_SERVER coerente; `.f805-disposable`; fontes/assets/MediaUpload iguais MIRROR/FUI. Confere também os nove alvos de projeto e diretórios de imagem sem symlinks. O marcador mantém nome por compatibilidade, sem criar subgrupo; não torna um banco real descartável.

**Não usar produção/dados ou credenciais reais, ambiente misto, proxy/túnel para produção, usuários concorrentes ou integrações produtivas.** Operador deve confirmar B/FUI, DB e diretórios reais do PHP. Config/nonce/hash não atesta backend/proxy mal configurado. Nunca vincular os dados do ensaio a conteúdo externo enquanto ele roda.

### Executar

Ajustar caminhos no terminal do laboratório, sem enviar senhas/tokens no chat:

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd -P)/dev"
# Só depois de confirmar banco/configs/diretórios descartáveis:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F808_HTTP='YES_WRITE_REAMUR_TEST'
read -r -p 'Usuário fictício com modify projeto e categoria: ' F808_USERNAME
read -r -s -p 'Senha fictícia: ' F808_PASSWORD; printf '\n'
export F808_USERNAME F808_PASSWORD
export F808_REPORT='/tmp/novo-resultado-f808.json'  # novo, inexistente
bash tools/e2e/e2e_f808.sh --http
unset F808_USERNAME F808_PASSWORD ALLOW_F808_HTTP
```

Relatório opcional, absoluto novo, 0600, sem sobrescrever anterior. Preflight bloqueado sai 2 antes de execução e não gera JSON de workflow. Não remover guardas/desativar TLS/alterar configs reais para forçar verde; Docker por hostname não loopback ou config PHP dinâmica podem ser recusados para avaliação pontual.

### Fluxo de 15 etapas preparado — não executado no backend pelo agente

1. Probe nonce único em `FUI/admin/view/js/f808-probe-<nonce>.txt`, conferido por B antes das credenciais.
2. Login com CSRF e cookies/tokens só em memória, sem jar/storage state.
3. Form de projetos ×3 idiomas, 21 campos, modify/config/upload; form de categoria confirma acesso. IDs de idiomas vêm do HTML, não pressupõem pt/es=2/3.
4. Criar categoria auxiliar própria, marcador único e ID real descoberto pelo link de nome, nunca ID1 fixo.
5. Add e upload de projeto sem token devem recusar sem projeto/arquivo novo.
6. Upload válido de três PNGs novos exclusivos; nomes não preexistentes, arquivos regulares/sem symlink, checksum no FUI e bytes HTTP no catalog iguais à fixture.
7. Criar projeto desabilitado/sem destaque com categoria própria, capa, duas galerias, alt/sort e sete campos por idioma.
8. Descobrir ID real e reabrir 21 campos, cliente/URL, N:N, capa/alt/ordem e flags.
9. Edit sem CSRF e URL inválida com token correto preservam os dados salvos.
10. Editar mesmo ID v2, status/featured on e ordem de galeria invertida, reabrindo para conferir caminhos/captions/sort/N:N.
11. Conferir filtros combinados nome/status/featured.
12. GET delete e POST sem token mantêm projeto/arquivos. 403 do GET negativo é tratado explicitamente, sem esconder 500.
13. Revalidar dados/ID/PNG próprios; excluir só projeto e três arquivos. Categoria deve permanecer.
14. Revalidar/excluir somente a categoria auxiliar própria.
15. Conferir ausência dos dois conteúdos e de arquivos novos do ensaio.

Sem SQL direto, drop/reset/recreate, instalação/upgrade/seed, suíte destrutiva ou upload de arquivo do usuário. Multipart não segue redirects, POST 307/308 não é repetido, redirects externos não recebem novo request/cookie. Em falha, preservar artefatos conhecidos para inspeção, sem adivinhar ID ou repetir mutação após timeout.

JSON real: `kind="http-workflow"`, `status`, marker, artifacts.projectId/categoryId/images, 15 etapas e cleanup. **Aceite do fluxo:** passed + 15 etapas pass + cleanup complete. Isso aprova só HTTP, não navegador. Sem HTML completo, credenciais, cookies ou tokens de autenticação/CSRF.

Cleanup refere aos registros/PNG/probe próprios, não remove diretórios padrão criados pelo componente, dados normais de sessão/rate limit ou sequências auto-increment. Se outro arquivo concorrente aparecer, o Tool não o remove para obter verde.

## 5. Aceite no navegador

Navegador moderno com Bootstrap local, fetch e AbortSignal.timeout. HTTP não executa o script de galeria nem valida foco/preview/CSS.

- [ ] Lista/form no shell; desktop/mobile, skins/claro/escuro sem overflow/perda de contraste.
- [ ] Filtros nome/status/featured inclusive zero, reset, total, página 2 e recuo. Volume real 21+ requer dados fictícios próprios; 45 foram só fixture.
- [ ] Nome EN obrigatório em outra tab: abre/foca primeiro inválido, inclusive múltiplos inválidos, sem erro de campo não focalizável.
- [ ] 21 IDs/names, summary/tag/HTML/Unicode/metadados/SEO e cliente/URL/status/featured/sort_order preservados ao salvar/reabrir.
- [ ] Todas as categorias disponíveis, sem limite da grade; marcar/desmarcar N:N persiste sem alterar/excluir categorias.
- [ ] Capa e múltiplas galerias upload/preview/salvar/reabrir; MIME/2 MB e CSRF; texto alternativo e ordenação persistem. Reabertura ordena por sort ASC/image_id ASC.
- [ ] Remover linhas intermediárias, clicar no ícone do botão e adicionar novamente não duplica names/índices; só remove associação, não arquivo físico imediatamente.
- [ ] Upload pendente bloqueia salvar/Enter/remover/envio concorrente; erro é texto e controles retornam sem retry.
- [ ] Delete via modal: cancelar preserva, confirmar POST+CSRF remove só projeto/capa/galeria de teste e deixa categorias existentes. Usar somente arquivos exclusivos fictícios.
- [ ] Conta fictícia readonly sem Add/Delete/Save, uploads/remover desabilitados e quatro POSTs negados; outro perfil real não foi coberto automaticamente.
- [ ] en-us/pt-br/es-eu, contadores/hint, sem CKEditor/Blog media/jQuery; categorias/CMS/Blog/Appearance/site público intactos.

Confirmar conclusão da Tarefa 08 após aplicação/homologação do usuário; não encerra automaticamente a Fase 8.

## 6. Falhas, limpeza e rollback

Timeout pode vir depois de o servidor gravar. Não repetir POST às cegas. Consultar JSON e marcador `f808-…`, verificar IDs/dados e somente então remover conteúdos próprios. Se ID não foi descoberto, não presumir.

Uploads sem associação podem deixar arquivos; inspeção do path exato/checksum conhecido, sem wildcard, `rm -rf`, reset SQL ou limpeza de view/image/project. Não usar imagem real/compartilhada para facilitar cleanup. O comportamento de exclusão de arquivos segue a base.

Probe só é removido em finally se regular/conteúdo igual ao nonce. Processo morto/probe alterado pode deixá-lo; inspecionar somente `f808-probe-<nonce>.txt` exato, nunca limpar view/js amplamente. Falha de cleanup mantém status failed/pending.

Upload continua imediato. Cancelar, substituir capa ou remover linha e salvar pode deixar arquivo órfão, como antes. Galeria inválida continua normalizada a caminho vazio/descartada pelo model, não rejeitada como capa. Não foi criada coleta automática, nova política de esquemas de URL (mantém FILTER_VALIDATE_URL), sanitização HTML ou proteção global de arquivo compartilhado/symlink do componente.

Rollback de código com patch original/backup e alvos sem alterações posteriores:

```bash
git apply -R --check "$ENTREGA/F8-08.patch"
git apply -R "$ENTREGA/F8-08.patch"
```

Se não aplicar, parar/revisar, sem reset --hard. Restaura dez arquivos/remove sete novos, sem reverter tarefas aceitas. Antes, limpar só registros/PNG/probe próprios; rollback não apaga dados/relatórios. Sincronizar só os alvos revertidos e invalidar cache normal.

A base antiga volta a não exigir CSRF em projetos; limitação conhecida, não recomendação de operação.

## 7. Limites

Sem GitHub write/deploy/produção, servidor/DB, HTTP do CMS, upload ou navegador reais nesta preparação. Sem Actions/PHPStan/PHPUnit completo/matriz PHP. Relações/arquivos reais, foco/preview, visual/contraste, outro perfil e volume exigem laboratório.

CSS e normalização de paginação locais; aria-labels internos da library como antes. Sem sanitização HTML ou atualização/licença do CKEditor; uso do editor não ampliado. MediaUpload e coleta de órfãos/referências permanecem escopos separados.

A conclusão da Tarefa 07 veio do usuário; só a igualdade dos 17 arquivos públicos foi conferida independentemente. Código publicado não prova deploy/persistência.

Referências: [ADR 0052](../agents-ia/decisoes/0052-f8-08-projetos-portfolio-no-shell.md), [F8-07](F8-07-Categorias-Portfolio.md), [F8-06](F8-06-Paginas-CMS.md), [base pública](https://github.com/davidcreator/davidcms/commit/a9aa816383fc5c40ce39415fb2b9f3257ba9597c).
