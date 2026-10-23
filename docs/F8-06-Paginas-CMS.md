# Fase 8 · Tarefa 06 — Páginas CMS

> Objetivo: migrar a lista e o formulário de páginas de conteúdo para o padrão atual do painel, com filtros/paginação, três idiomas, validação nativa e CSRF, sem mudar schema ou introduzir editor rico.

**Data:** 2026-10-23 · **Base pública:** `98a035f2007e627815f57993ac0125182cdaf5f8`.
**Estado:** escopo escolhido pelo usuário; pacote preparado e testado localmente. Aplicação e homologação reais pendentes. A Tarefa 05 foi declarada concluída pelo usuário e não é reaberta aqui.

## Sumário

1. [Escopo e contratos](#1-escopo-e-contratos)
2. [Arquivos](#2-arquivos)
3. [Aplicação e cache](#3-aplicação-e-cache)
4. [Tool e resultados locais](#4-tool-e-resultados-locais)
5. [E2E HTTP no laboratório](#5-e2e-http-no-laboratório)
6. [Aceite visual e funcional](#6-aceite-visual-e-funcional)
7. [Rollback](#7-rollback)
8. [Limites](#8-limites)

## 1. Escopo e contratos

A continuidade usa somente **Fase 8 / Tarefa 06**, sem novos nomes de “ajuste”. Não avança para o visual do site ou Fase 9.

### Entregue

- Lista com titlebar, total filtrado, tabela responsiva, status, ações, mensagens/empty state, busca por título e filtro de status.
- Paginação server-side de **20 registros**, ordenação antiga `sort_order ASC, page_id ASC`, contagem consistente e recuo de página fora do intervalo.
- Form em duas colunas: conteúdo/SEO em tabs por idioma e sidebar de status/ordenação/ações.
- Corpo **textarea**, sem carregar CKEditor, biblioteca de mídia ou upload. HTML e Unicode seguem aceitos pelo contrato existente, sem nova transformação/sanitização.
- Delete POST por modal global Bootstrap, sem `confirm()` inline.
- CSRF em **add/edit/delete**, usando o serviço existente e token estável da sessão. Ações modify ocultas para readonly; ACL backend preservada.
- Script CMS mínimo revela a aba do primeiro campo inválido para que o navegador possa focá-lo; não usa `preventDefault`, não ignora o `required` EN e não submete manualmente.
- Textos completos em `en-us`, `pt-br`, `es-eu`; contadores/hint de status reutilizam o `admin.js` existente, sem modificá-lo.

### Preservado

Rotas `cms/page`, `.add`, `.edit|ID`, `.delete|ID`; `page_description[language_id][title|keyword|description|meta_title|meta_description]`; os IDs `input-{title,keyword,description,meta-title,meta-description}-N`; `input-sort-order`, `name=sort_order`, `name=status`; título EN obrigatório e SEO global.

`getPages()` sem argumentos permanece a chamada compatível que retorna todas as páginas. Novos filtros são opcionais; apenas o controller novo solicita o recorte de 20. Busca usa título visível do idioma atual, com fallback EN, via LIKE; curingas SQL de LIKE não são alterados.

**Mudança intencional de segurança:** scripts/clientes que enviam POST para CMS agora precisam de `csrf_token` da mesma sessão. Não há bypass para clientes antigos. Os dois scripts existentes afetados foram atualizados pontualmente.

Não se altera Blog/mídia, Appearance, Localization, shell/global JS, `system/**`, `catalog/**`, `install/**`, tabelas, migrations, grants ou Composer do projeto.

## 2. Arquivos

### Runtime — somente o módulo CMS e estilo isolado

- `dev/admin/controller/cms/page.php` — dados/ACL/CSRF/filtros/paginação/assets.
- `dev/admin/model/cms/page.php` — lista/contagem com predicados compartilhados; os métodos de persistência continuam intactos.
- `dev/admin/view/template/cms/page_list.twig` e `page_form.twig` — novo layout, IDs/names preservados.
- `dev/admin/view/js/cms-page.js` — boot DOM-ready e abertura da aba inválida, restrito a `data-cms-page-form`.
- `dev/admin/view/css/admin.css` — somente adendo sob `.cms-page-list`; prefixo original preservado byte a byte.
- `dev/admin/language/{en-us,pt-br,es-eu}/cms/page.php` — rótulos e erro CSRF.

A library de paginação gera markup legado e duplo escape de ampersands. O CSS é isolado ao CMS e o controller normaliza **apenas** `&amp;amp;` no HTML de paginação gerado pela própria library, com filtros URL-encoded. Não se decodifica conteúdo arbitrário nem se corrige o núcleo global nesta tarefa.

### QA e documentação

- `tools/e2e/e2e_f806.sh` — entrada única.
- `tools/e2e/e2e_f806_contracts.php` — 30 contratos PHP/Twig.
- `tools/e2e/e2e_f806_dom.cjs` — 6 casos do script real com DOM/Bootstrap simulados.
- `tools/e2e/e2e_f806_http.cjs` — 16 testes de auxiliares e E2E HTTP opt-in.
- `dev/tests/e2e/e2e_regressao.sh` — somente três POSTs CMS passam a enviar o token já obtido.
- `tools/e2e/e2e_f701.sh` — somente o POST CMS da verificação SEO cruzada recebe o token já obtido.
- [ADR 0050](../agents-ia/decisoes/0050-f8-06-paginas-cms-no-shell.md), este guia e `docs/README.md`.

São **18 arquivos de projeto**, acompanhados de `F8-06.patch`, `MANIFESTO-F8-06.json` e `SHA256SUMS`. Sem clone completo, vendor/dependências, conteúdo, credenciais reais ou banco no pacote. Os scripts legados permanecem destrutivos; **não são chamados pelo Tool novo**.

## 3. Aplicação e cache

1. Confira `git rev-parse HEAD`: a base esperada é `98a035f2007e627815f57993ac0125182cdaf5f8`. Se houver commits posteriores/alterações nos alvos, revise o diff e hashes antes de aplicar; não sobrescreva trabalho concorrente.
2. Use branch de trabalho/PR para staging conforme as convenções do projeto; preserve backup dos alvos.
3. Extraia `F8-06.zip` em diretório vazio fora do repositório e confira `sha256sum -c SHA256SUMS` dentro da extração.
4. Na raiz do repositório, usando `ENTREGA` como o caminho absoluto da extração:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-06.patch"
git apply --whitespace=error "$ENTREGA/F8-06.patch"
git diff --check
git status --short
bash tools/e2e/e2e_f806.sh --check
```

Alternativa manual: copiar apenas os 18 arquivos de projeto nos caminhos indicados pelo manifesto. Não copiar pastas inteiras, executar build/deploy integral, usar `--delete` ou reaplicar ZIPs anteriores.

### Cópia servida

Sincronize apenas os arquivos alterados pelo fluxo controlado do projeto. Alterar `dev/` não comprova deploy. Invalide cache de `admin.css`, confira o novo `cms-page.js` servido com HTTP 200 e, se houver cache PHP/Twig, invalide-o apenas no ambiente de homologação pelo procedimento normal.

O **ASSET_V global não é alterado**. Os nomes novos não dependem de cache antigo; a atualização do CSS existente exige atenção. A página deve usar o shell, não os containers `.dash/.panel` antigos. Não há upgrade SQL a executar.

Sugestão de commit:

```text
feat(admin): migrar páginas CMS para o shell com filtros e CSRF
```

## 4. Tool e resultados locais

```bash
bash tools/e2e/e2e_f806.sh --check
bash tools/e2e/e2e_f806.sh --help
```

Requisitos do Tool: **PHP CLI 8.1+**, `mbstring`, Twig 3 pelo Composer do projeto, Node.js 20+ e Bash. A versão Twig do lock-base é **3.30.0**. Não há instalação automática de pacotes, nova dependência da aplicação ou alteração do Composer no ZIP.

Por padrão o teste carrega `dev/system/storage/vendor/autoload.php`. Para fixture local isolada, pode usar `TWIG_AUTOLOAD=/caminho/absoluto/vendor/autoload.php`; o diretório `MIRROR` aponta ao `dev` testado. Não execute autoload/fontes de origem não confiável: estes testes não são sandbox de segurança.

### Evidência executada pelo agente

| Grupo | Resultado | O que realmente executou |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP de runtime e o runner PHP. |
| Contratos PHP/Twig | **30 PASS / 0 FAIL** | PHP **8.4.26**, Twig **3.30.0**, classes reais do projeto; DB/model do controller/serviços/IO de sessão em fixtures. |
| JS CMS | **6 PASS / 0 FAIL** | Fonte real, DOM/Bootstrap simulados. |
| Helpers HTTP | **16 PASS / 0 FAIL** | Parser/cookies/redirects/IDs/guardas, fetch e respostas simulados. |
| Controle negativo nas fontes anteriores | **12 PASS / 18 FAIL**, saída **1** | Falha esperada na suíte PHP/Twig com o CMS antigo. |
| Sintaxe JS/CJS/Bash | Aprovada | Inclui os dois scripts legados, sem executá-los. |

O controller/model são executados de fato, e os templates são compilados/renderizados com Twig strict/autoescape. O SQL do model real é **capturado**, não enviado ao MariaDB. Session real é instanciado sem construtor apenas para seu array de dados; não há cookies/handler nativo ou conexão de sessão real.

O agente preparou PHP CLI e um autoload Twig isolado para QA. A versão Twig coincide com a do lock; não se instalou o CMS, servidor ou banco, não se clonou o projeto e não se alterou o vendor/Composer dele.

**Não chamar esses resultados de 52 E2Es reais aprovados.** Não houve navegador, PHP-FPM/Apache, HTTP da aplicação, MariaDB, persistência real, papéis de usuário reais, deploy ou Actions nesta preparação.

## 5. E2E HTTP no laboratório

### 5.1 Segurança e pré-requisitos

`--http` grava e remove **uma página de teste própria**. Exige instalação local descartável já funcional, banco **reamur_test**, conta fictícia com access/modify em `cms/page` e os idiomas en/pt-BR/es cadastrados. Os IDs dos idiomas são descobertos nas tabs, não se presume que pt/es são 2/3. O ID da página é descoberto no link de edição, nunca fixado em 1.

As guardas F8-05 já versionadas são reutilizadas como funções, **sem chamar o fluxo Blog ou scripts de reset**. Exigem B/FUI/MIRROR explícitos, loopback, configs admin/catalog literais do wizard com DB_DATABASE=reamur_test e DB_HOSTNAME loopback, HTTP_SERVER correspondente, marcador de descarte e igualdade de assets/fontes. O Tool também compara os nove arquivos CMS entre MIRROR e FUI.

O marcador `.f805-disposable` mantém o nome por compatibilidade com o preflight existente; não é um novo ajuste/subgrupo. Não crie o marcador para contornar uma instalação real. Ele não transforma um banco em descartável.

**Não usar em produção, dados reais, proxy/túnel para produção ou ambiente misto.** Configs/nonce/hashes não atestam o banco que um proxy/PHP mal configurado realmente usa; o operador deve confirmar que FUI é o runtime real desse B. O probe temporário prova a cópia estática servida, não a configuração dinâmica de um proxy.

### 5.2 Executar

Ajuste os caminhos reais no terminal do laboratório. Nunca envie credenciais pelo chat ou as versione:

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd)/dev"

# Somente após conferir que o banco/configs são descartáveis:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F806_HTTP='YES_WRITE_REAMUR_TEST'

read -r -p 'Usuário fictício do laboratório: ' F806_USERNAME
read -r -s -p 'Senha fictícia: ' F806_PASSWORD; printf '\n'
export F806_USERNAME F806_PASSWORD
export F806_REPORT='/tmp/novo-resultado-f806.json'  # arquivo ainda inexistente

bash tools/e2e/e2e_f806.sh --http
unset F806_USERNAME F806_PASSWORD ALLOW_F806_HTTP
```

Não desative TLS, altere configs reais ou remova guardas em caso de bloqueio. Se o runtime usa config PHP dinâmico ou banco Docker por hostname não loopback, o preflight pode recusar; informe o caso para avaliação pontual.

O modo HTTP primeiro exige todos os checks locais verdes. Depois:

1. Cria `FUI/admin/view/js/f806-probe-<nonce>.txt` com nonce aleatório e confere seu conteúdo por B **antes de enviar credenciais**.
2. Faz uma tentativa de login com CSRF. Cookies/tokens ficam em memória, sem `.jar` ou storage state; não repete login após erro.
3. Verifica form/i18n/modify/CSRF e descobre os language_id nas tabs; confere inexistência do marcador próprio.
4. Tenta add sem token e exige recusa sem criar a página.
5. Cria página desabilitada com corpo HTML/Unicode e SEO/metadados nos três idiomas; exige filtro que traga somente a linha própria e descobre seu ID.
6. Reabre por GET e confere todos os campos e ordenação.
7. Tenta edit com token incorreto e exige que o salvo permaneça igual.
8. Edita o mesmo ID para v2, habilita, reabre e confere os filtros de status/título.
9. Exige que GET delete e POST sem token válido não excluam. O 403 esperado do GET negativo é tratado explicitamente, sem esconder erro 500.
10. Revalida os dados/ID próprios, exclui com CSRF e confere ausência.

Não há SQL direto, drop/recreate, instalação, upgrade, seed, upload, remoção ampla ou chamada das suítes legadas. A infraestrutura normal de login/sessão/rate limit do CMS funciona normalmente no banco de laboratório.

Redirect externo não recebe cookies/novo request; POST 307/308 não é repetido; falha/timeout não dispara retry de mutação. Em falha após tentativa de POST, a página é preservada para inspeção. Um timeout pode ocorrer depois de o servidor ter gravado: **não reenvie automaticamente**.

### 5.3 Relatório e limpeza

O resultado JSON tem `kind: "http-workflow"`, `status`, dez etapas, identificador `f806-…`, ID descoberto e `cleanup`. Só `status=passed`, dez etapas pass e `cleanup=complete` comprovam esse fluxo HTTP. O relatório não inclui HTML completo, senhas, cookies ou tokens de autenticação/CSRF. Não confundir o JSON real com os testes de helpers simulados.

`F806_REPORT` é opcional; se definido, precisa ser novo, absoluto e em diretório existente. O arquivo é criado com permissão 0600, sem sobrescrever relatório anterior. Se o preflight bloquear antes do início, a saída é 2 e não há relatório de execução.

O probe próprio é removido no `finally` se conteúdo/dono não tiverem sido alterados. Se o processo for morto ou a remoção for recusada, inspecione **somente o arquivo exato** `f806-probe-<nonce>.txt`; não use wildcard ou limpeza de `view/js`.

Em falha com página deixada, busque o marcador do relatório na UI, confira título/ID/keywords e remova apenas essa página de teste. Não adivinhe ID, remova conteúdo alheio ou use SQL direto para forçar verde. A sequência auto-increment pode avançar, como em qualquer criação real de laboratório.

## 6. Aceite visual e funcional

Após aplicação/cache, no navegador do laboratório:

- [ ] Lista/form usam shell e cards, sem layout `.dash/.panel` antigo, com status/ordenação/feedback legíveis.
- [ ] Desktop e mobile sem overflow; tabela responsiva e paginação visível; temas claro/escuro e skins sem perda de contraste.
- [ ] Filtros título/status, reset, total correspondente, página 2 e recuo de página além do fim. A bateria local cobre 45 registros em fixture; volume de 21+ páginas no servidor requer dados fictícios/evidência própria.
- [ ] Os 15 IDs/names multilíngues e sort/status permanecem; criar/editar/salvar/reabrir preserva conteúdo/HTML/acentos/metadados/SEO.
- [ ] **EN vazio enquanto outra tab está ativa:** ao salvar, o navegador abre a tab EN e foca o campo obrigatório, sem erro de “campo não focalizável”. O script real foi testado só com DOM simulado; validar o foco real aqui.
- [ ] Tabs/contadores/hint de status funcionam com os assets locais carregados. Sem Bootstrap, as tabs não são homologadas; não enfraquecer CSP para contornar falha.
- [ ] Delete abre o modal global; cancelar não remove, confirmar envia POST com CSRF.
- [ ] Conta fictícia sem modify não vê Add/Delete/Save e seus POSTs são recusados; teste real com outro perfil não está coberto pelo E2E HTTP positivo.
- [ ] en-us/pt-br/es-eu traduzidos; corpo continua textarea e não carrega CKEditor/mídia.
- [ ] Blog/biblioteca de capas e Appearance continuam inalterados.

Concluir a Tarefa 06 somente depois de aplicação/homologação pelo usuário; isso não encerra automaticamente a Fase 8.

## 7. Rollback

Com backup, patch original e alvos sem mudanças posteriores:

```bash
git apply -R --check "$ENTREGA/F8-06.patch"
git apply -R "$ENTREGA/F8-06.patch"
```

Se não aplicar, pare e revise; não use `git reset --hard` ou limpeza ampla. A reversão remove os sete arquivos novos (script CMS, quatro tools, guia e ADR) e restaura os onze arquivos modificados, sem reverter a Tarefa 05. Limpe previamente qualquer probe/página própria de teste: rollback de código não apaga artefatos ou relatórios do laboratório.

Sincronize apenas os arquivos revertidos ao runtime e invalide CSS/cache normal. O CMS antigo volta a não exigir CSRF; isso é a limitação conhecida da base, não um mecanismo recomendado de operação.

## 8. Limites

- Aplicação/GitHub/deploy não foram alterados remotamente pelo agente; esta é entrega incremental para o usuário aplicar.
- Não houve banco real, servidor, navegador, contraste computado, performance de consultas, Actions ou E2E HTTP real executado nesta preparação. Não foi rodada a matriz de todas as versões PHP.
- A normalização e o CSS da paginação são locais; aria-labels internos da library compartilhada continuam como antes. Saneamento global do componente é tarefa separada.
- HTML de conteúdo segue o contrato antigo; isso não é sanitização ou solução de XSS armazenado.
- CKEditor 4.22.1 e sua dívida de segurança/licença não são alterados e seu uso não é ampliado.
- Não se infere deploy/dados/resultados de backend a partir do código publicado. A conclusão da Tarefa 05 foi informada pelo usuário, sem inventar detalhes de sua execução.

Referências: [ADR 0050](../agents-ia/decisoes/0050-f8-06-paginas-cms-no-shell.md), [ADR 0048](../agents-ia/decisoes/0048-f8-04-sale-marketing-filtros-paginacao-csrf.md), [guia F8-05](F8-05-Validacao-Integrada-Blog.md) e [base pública](https://github.com/davidcreator/davidcms/commit/98a035f2007e627815f57993ac0125182cdaf5f8).
