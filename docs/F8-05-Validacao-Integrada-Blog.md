# Fase 8 · Tarefa 05 — Validação integrada do Blog

> Objetivo: homologar editor multilíngue, biblioteca de capas, upload/seleção, criação/edição, salvar e reabrir no navegador real do laboratório, sem instalar dependências nem recriar banco.

**Data:** 2026-10-23 · **Agente:** admin · **Base pública:** `f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a`.

**Estado:** os dois passos de correção anteriores foram incorporados na `main` e sua aplicação foi confirmada pelo usuário. Este pacote prepara a validação integrada; **o fluxo real de navegador/PHP/banco ainda não foi executado pelo agente e a Tarefa 05 não está encerrada**.

## Sumário

1. [Escopo e organização](#1-escopo-e-organização)
2. [Arquivos e aplicação](#2-arquivos-e-aplicação)
3. [Verificações locais](#3-verificações-locais)
4. [Preparar o laboratório](#4-preparar-o-laboratório)
5. [Executar no navegador](#5-executar-no-navegador)
6. [Cobertura do fluxo](#6-cobertura-do-fluxo)
7. [Relatório e decisão de aceite](#7-relatório-e-decisão-de-aceite)
8. [Limpeza e rollback](#8-limpeza-e-rollback)
9. [Limites e evidências](#9-limites-e-evidências)

## 1. Escopo e organização

A partir desta entrega, a identificação do trabalho permanece somente **Fase 8 / Tarefa 05**. Não existe novo “Ajuste 03”, nova fase ou subgrupo de aplicação. Nomes A01/A02 já versionados ficam como histórico/compatibilidade; não são renomeados nem reaplicados.

A consulta pública confirmou o commit `f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a` (`fix(admin): isolate legacy CKEditor config for blog RTE fields`). O `admin.js` desse commit é idêntico ao entregue anteriormente. Isso confirma o código publicado, **não prova deploy, execução visual ou persistência**.

Este ZIP contém apenas ferramentas e documentação. Não altera arquivos `dev/**`, controllers/models, Twig da aplicação, schema, endpoints, permissões, CSRF, importador, assets vendor ou versão/licença do CKEditor. Não clona o projeto nem inclui imagens/conteúdo do site.

O novo Tool usa o **navegador nativo já autenticado do operador**, em uma janela própria de laboratório. Não adiciona Playwright, npm, Composer, CDN, extensão de navegador ou serviço externo. A execução é assistida: o operador prepara a permissão e executa conscientemente os comandos no Console.

## 2. Arquivos e aplicação

| Caminho | Função |
| --- | --- |
| `tools/e2e/e2e_f805.sh` | Entrada única da tarefa: `--check`, `--self-test`, `--prepare-browser`, `--clean-browser`. |
| `tools/e2e/e2e_f805.cjs` | Guardas locais, 32 casos de teste e preparação/limpeza de dois arquivos QA temporários. |
| `tools/e2e/e2e_f805_browser.js` | Tool de navegador real; carregar não inicia o fluxo. Também permite importar seus validadores nos testes Node. |
| `docs/F8-05-Validacao-Integrada-Blog.md` | Este guia de aplicação, execução, evidência, limpeza e limites. |
| `docs/README.md` | Entrada no índice. |
| `agents-ia/decisoes/0049-f8-05-blog-media-library.md` | Registro de continuidade da tarefa e estado da validação. |

Auxiliares: `F8-05.patch`, `MANIFESTO-F8-05.json`, `SHA256SUMS`. Não são dependências do site.

### Aplicar por patch

1. Confirme `git rev-parse HEAD` na base indicada e ausência de alterações concorrentes nos alvos. Se a base divergir, revise o diff/hashes antes de aplicar.
2. Extraia `F8-05.zip` em diretório vazio fora do repositório.
3. Na extração, execute `sha256sum -c SHA256SUMS`.
4. Na raiz do repositório, usando `ENTREGA` como caminho absoluto da extração:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-05.patch"
git apply --whitespace=error "$ENTREGA/F8-05.patch"
git diff --check
git status --short
bash tools/e2e/e2e_f805.sh --check
```

Alternativa manual: copiar apenas os seis arquivos da tabela, preservando caminhos. Não substituir pastas, reaplicar os pacotes históricos, executar build/deploy integral ou usar `--delete`.

Sugestão de commit após revisão:

```text
test(admin): preparar validação integrada do blog na tarefa 05
```

## 3. Verificações locais

Requisitos: **Node.js 20+ e Bash**. Sem PHP/banco/navegador para estes comandos:

```bash
# Padrão seguro: guardas, contratos locais e regressões históricas.
bash tools/e2e/e2e_f805.sh --check

# Somente os 32 casos novos de guardas/modelos.
bash tools/e2e/e2e_f805.sh --self-test

bash tools/e2e/e2e_f805.sh --help
```

O `--check` verifica sintaxe, executa os 32 casos do Tool, inspeciona contratos do JS/Twig local e reutiliza `e2e_f805_a02_ckeditor.sh --regression`: **18 casos RTE + 10 casos mídia**. As mensagens históricas desses scripts mantêm seus nomes; não são novas subdivisões do projeto.

**Resultados desta preparação:**

| Grupo | Resultado | Natureza |
| --- | --- | --- |
| Sintaxe dos novos JS/CJS/Bash | Aprovada | Estática. |
| Guardas/modelos do Tool | **32 PASS / 0 FAIL** | Node, fixtures pequenas temporárias; sem core/editor/servidor. |
| RTE existente | **18 PASS / 0 FAIL** | JS real com DOM/CKEDITOR simulados. |
| Mídia existente | **10 PASS / 0 FAIL** | JS real com DOM/HTTP/Bootstrap simulados. |
| Entrada `--check` completa | Aprovada em árvore parcial | Somente fontes pontuais da base, verificadas byte a byte. |
| Controle negativo com `admin.js` anterior (`c2df689`) | Recusado, saída **2** esperada | O inicializador anterior não satisfaz o contrato aplicado. |
| CLI de preparação/limpeza e bloqueios | Aprovados em fixtures de filesystem **não servidas** | Inclui colisão/hash/nonce/host; core é apenas uma fixture de hash nesses testes, não um editor executado. |
| Fluxo E2E nativo descrito abaixo | **Não executado pelo agente** | Exige laboratório e sessão autenticada do operador. |

Não interpretar a soma dos testes locais como “60 testes E2E reais aprovados”. PHP, banco, navegador, iframe do CKEditor e persistência não participaram desses resultados. Nenhuma dependência foi instalada. A suíte legada destrutiva `e2e_f805_blog_media.sh` **não é chamada** pelo novo Tool; `--backend` não é um modo válido.

## 4. Preparar o laboratório

### 4.1 Condições obrigatórias

Use somente uma instalação **local descartável**, com dados fictícios e banco **`reamur_test`**. Não usar produção, cópia com dados reais, túnel para produção, proxy que encaminhe PHP a outro ambiente ou sessão de usuário real.

O Tool não instala/reinstala o CMS, não aplica SQL e não cria/dropa bancos. O laboratório já deve estar funcional, atualizado com o commit-base, com as tabelas/configurações necessárias e conta de teste com `modify` em `blog/blog`.

As verificações conferem os configs locais e a identidade dos assets servidos. **Não são atestação remota do banco que um proxy/PHP mal configurado efetivamente usa.** Cabe ao operador garantir que FUI é a aplicação real desse B e que não há encaminhamento para outro ambiente.

Exemplo de variáveis — ajuste os caminhos reais antes de executar:

```bash
# Na raiz do repositório, em terminal do próprio laboratório:
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd)/dev"

# Só depois de conferir que o ambiente é de fato descartável:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F805_BROWSER='YES_WRITE_REAMUR_TEST'

bash tools/e2e/e2e_f805.sh --prepare-browser
```

O marcador **não transforma um banco em descartável**. Não o crie apenas para vencer uma guarda e não altere configurações reais para contornar o teste.

### 4.2 O que é conferido antes de publicar o Tool

- B HTTP(S) somente em `localhost`, `127.0.0.1` ou `[::1]`, na raiz do `dev`, sem usuário/senha, query ou fragmento.
- FUI e MIRROR explícitos, absolutos, existentes e sem symlink no caminho.
- Marcador `FUI/.f805-disposable` com `reamur_test`.
- `admin/config.php` e `catalog/config.php`: `DB_DATABASE` literal `reamur_test`; `DB_HOSTNAME` loopback; `HTTP_SERVER` correspondente a B para cada aplicação.
- O parser aceita o formato plano `define(...);` gerado pelo wizard. Recusa PHP dinâmico, include/condicionais, ambiguidades e comentários/strings usados como falsos defines. **Não executa nem altera o PHP/config.**
- MIRROR/FUI com JS RTE, mídia, core CKEditor e template do form idênticos.
- Assinaturas dos boots já aplicados e contrato real `blog_description[language_id][description]` no Twig.

Se usa config dinâmico ou formato diferente, o preflight pode recusar por segurança; não remova guardas. Informe o bloqueio para avaliação pontual.

### 4.3 Dois arquivos QA temporários

A preparação cria, **sem sobrescrever arquivos existentes**:

```text
FUI/admin/view/js/f805-<nonce>.js
FUI/admin/view/js/f805-<nonce>.json
```

São uma cópia do Tool e uma permissão com nonce aleatório, prazo de **15 minutos**, base, identificador do ensaio e hashes SHA-256 dos três scripts servidos. Não contêm senha, cookie, token CSRF ou conteúdo dos posts. O CLI não faz HTTP nem inicia o navegador/fluxo.

Não são novas rotas/arquitetura nem assets permanentes. **Não versionar, fazer deploy ou deixar esses arquivos publicados.** Se FUI e MIRROR forem a mesma pasta, aparecerão como untracked até a limpeza; revise `git status` antes de qualquer `git add`.

Uma colisão é recusada. Se a escrita do segundo arquivo falhar, a cópia recém-criada do primeiro é desfeita sem sobrescrever/remover o arquivo que causou a colisão.

## 5. Executar no navegador

1. No seu navegador moderno (recomendado Chrome/Edge), abra o **mesmo B/admin**, na sua máquina de laboratório — não no preview do Arena nem no GitHub. Faça login manual com a conta fictícia de homologação.
2. Libere popup **somente para esse host local**. WebCrypto, DataTransfer, `AbortSignal.timeout` e evento nativo `formdata` devem estar disponíveis; em localhost/contexto seguro os navegadores atuais os oferecem.
3. Abra DevTools → Console. Leia os **dois comandos impressos pela preparação**.
4. Execute o primeiro: ele carrega o Tool local, mas não abre o fluxo/grava banco. Aguarde `Tool local carregado`.
5. Execute o segundo: `ReamurF805.run(...)`, com base/nonce/confirmação gerados. Essa é a autorização explícita para gravar os dados fictícios do ensaio.
6. Deixe a janela própria do ensaio aberta e não navegue/edite nela durante a execução. Não clique repetidamente em salvar/upload e não reenvie comandos depois de timeout.
7. Quando terminar, veja o relatório no Console da janela original e execute:

```js
ReamurF805.download()
```

Isso baixa `F8-05-resultado-<identificador>.json`. O relatório contém status das etapas, versão/idioma do editor, hashes e identificadores dos artefatos do teste; **não exporta cookies, senhas, tokens, HTML completo ou credenciais**.

Não é necessário informar credenciais ao agente. O Tool usa a sessão existente, nunca efetua login automático, altera ACL, cria administrador ou exporta storage state.

### Bloqueios esperados

- Permissão vencida → preparar novo nonce e limpar o par anterior; não prolongar o JSON manualmente.
- Hash servido divergente → conferir cópia servida/cache dos passos já aplicados. O Tool não muda `ASSET_V` e não executa deploy.
- Popup bloqueado → liberar apenas o host local e executar conscientemente outra vez antes de qualquer gravação.
- Sessão/modify ausente, editor não pronto, asset/skin ausente, seletor inesperado ou CSP bloqueando o script → parar e registrar o erro. Não enfraquecer CSP/ACL para obter verde.
- A navegação deve manter relação com a janela original. Políticas COOP/origin/redirect incompatíveis podem bloquear; são falhas de homologação a analisar, não motivo para remover as restrições de origem.

## 6. Cobertura do fluxo

Quando executado de fato pelo operador, o Tool pretende verificar estas **14 etapas**:

1. Permissão, origem, validade e hashes de `admin.js`, `blog-media.js` e core CKEditor **realmente recebidos do servidor**.
2. Sessão/contratos e três instâncias reais do CKEditor (`instanceReady`), idioma e toolbar, sem solicitar config/plugins legados.
3. Preencher en/pt-BR/es, título/keyword/metadados e HTML com acentos, h2, negrito, link/lista. Abrir **Source pela toolbar**, editar e retornar ao modo visual.
4. Criar uma pasta exclusiva `view/image/blog/f805-<identificador>/` pelo modal.
5. Tentar criar a subpasta `csrf-probe` **sem CSRF**, exigir recusa e conferir que ela não existe.
6. Fazer upload de um PNG RGBA 2×2 de teste pelo controle real (`File`/`DataTransfer`), conferir `input/change`, capa e carregamento do preview.
7. Reabrir o modal e escolher o **mesmo arquivo pela listagem**, comprovando o preenchimento/fechamento/preview.
8. Salvar um novo rascunho pelo botão nativo e observar o evento **`formdata`** para comprovar o HTML corrente. **Não chama `updateElement()` manualmente para mascarar uma falha do submit.**
9. Encontrar o post pelo título exclusivo, descobrir seu ID pelo link real de edição, reabrir e fazer nova carga GET; conferir persistência dos três idiomas, HTML/acentos/metadados, capa e status.
10. Editar o **mesmo ID**, trocar conteúdo/keywords para v2 e salvar nativamente.
11. Reabrir/recarregar e conferir a versão v2, inclusive edição por Source, sem aceitar apenas valores ecoados pelo POST.
12. Conferir a referência da capa e exigir que o servidor bloqueie a exclusão do arquivo enquanto estiver em uso.
13. Excluir **somente o post recém-criado**, com propriedade/ID comprovados, pelo modal de delete da própria aplicação; conferir sua ausência na lista filtrada.
14. Depois de liberar a referência, excluir **somente o PNG e a pasta desse ensaio** pelos endpoints existentes, com CSRF e confirmação de ausência.

Não usa ID fixo `1`, SQL direto, remoção recursiva, limpeza global de mídia, reinstalação, reset ou drop do banco. O nome único da pasta/post é dado temporário de teste, não um novo subgrupo do projeto.

**Em falha após tentativa de gravação, não há limpeza destrutiva automática nem retry de POST.** Os itens ficam para inspeção, com identificador/caminhos/ID conhecido no relatório. Se o ID não foi comprovado, não se tenta adivinhá-lo. A criação do marcador/arquivos QA e o sucesso local do preflight não provam que estas etapas reais passaram.

## 7. Relatório e decisão de aceite

Envie o JSON baixado para registrar o resultado. Ele deve indicar:

- `kind: "native-browser-workflow"`;
- `status: "passed"`;
- todas as 14 etapas com `status: "pass"`;
- `cleanup: "complete"`;
- hashes/versão/idioma e identificador coerentes com a execução.

`blocked`, `failed`, limpeza pendente ou ausência do JSON significam **homologação pendente**, não conclusão da tarefa. Um print de testes Node verdes ou a confirmação de que o ZIP foi aplicado não substitui esse resultado.

Além do fluxo assistido, conferir manualmente no laboratório:

- [ ] Layout/tema/sidebar/tabs/counters/status e preview sem regressão visual.
- [ ] Interface do painel em en-us/pt-br/es-eu; o idioma do editor acompanha a interface sem traduzir o corpo.
- [ ] Fallback textarea com o request do CKEditor bloqueado antes do reload, sem usar dados reais para teste.
- [ ] Conta de teste **sem modify** não consegue operar a biblioteca. O fluxo positivo do Tool não é uma bateria negativa de ACL com outro perfil.
- [ ] Eventuais casos adicionais do plano de mídia (MIME inválido, limite 2 MiB, traversal/symlink, busca/paginação) com evidência própria, se ainda não homologados. Não executar automaticamente o script legado que recria o banco.

Depois do resultado real e dos itens aplicáveis, avaliar o aceite da **Tarefa 05**. Não foi criada/adiantada outra tarefa/fase nesta entrega.

## 8. Limpeza e rollback

### 8.1 Limpeza dos dois arquivos QA

Mantenha as variáveis explícitas do laboratório e use o nonce impresso na preparação:

```bash
F805_NONCE='<nonce de 32 caracteres hex>' \
  bash tools/e2e/e2e_f805.sh --clean-browser
```

Esse comando confere novamente o ambiente, o dono do par e o hash do Tool copiado; aceita limpar uma permissão já vencida. **Remove somente os dois arquivos QA**, não posts, imagens, pastas de mídia, configs, marcador ou banco.

Se os arquivos foram modificados, o ambiente mudou ou o nonce é inválido, a limpeza é recusada. Revise os dois caminhos exatos antes de eventual remoção manual; não use `rm -rf`, wildcard ou limpeza de `view/js`.

### 8.2 Se o fluxo deixou dados de teste

Na UI do próprio laboratório, localize o identificador do relatório e confira título/ID/capa. Remova **post próprio → arquivo próprio → pasta própria**. Se o teste CSRF falhou e criou `csrf-probe`, inspecione essa subpasta também. Não remova itens não identificados nem altere o banco diretamente para forçar sucesso.

Depois limpe o par QA pelo comando acima e remova o marcador quando não precisar mais da autorização. Não compartilhe sessão/cookies e não mantenha a permissão publicada como mecanismo permanente.

### 8.3 Rollback do pacote

Com os alvos sem modificações posteriores e o patch original na extração:

```bash
git apply -R --check "$ENTREGA/F8-05.patch"
git apply -R "$ENTREGA/F8-05.patch"
```

Antes de reverter, limpe os temporários QA enquanto os tools ainda existem. O rollback remove os quatro arquivos novos (três tools e este guia) e restaura índice/ADR, sem reverter os passos anteriores. Não apaga dados/temporários que uma execução real tenha deixado; faça a limpeza específica descrita acima. Nenhum `dev/**` é revertido por este pacote.

## 9. Limites e evidências

### Executado nesta preparação

- Node **20.20.2**, Bash e testes pontuais em árvore parcial temporária.
- Sintaxe dos novos arquivos; 32 testes de guardas/modelos, preparo/limpeza em fixtures pequenas e carregamento inerte do módulo de navegador.
- Regressões simuladas 18 RTE + 10 mídia; fontes aceitas comparadas com a base pública.
- Inspeção do Twig, rotas, payload `blog_description`, CSRF, modal global e APIs locais efetivamente existentes.

### Não executado pelo agente

Navegador/DOM nativo, core/iframe/skin/plugins reais do CKEditor, PHP/HTTP/banco do projeto, upload/persistência, papéis ACL, deploy/produção ou GitHub Actions. **O Tool nativo novo ainda precisa da primeira execução no ambiente do usuário**; seus validadores testados não garantem que todas as interações visuais passarão.

Os 14 passos usam DOM/eventos nativos e a aplicação real, mas são automação assistida programática: não substituem teste visual/acessibilidade ou todas as interações físicas de mouse/teclado/diálogos. Em erro de seletor/tempo/ambiente, registrar a etapa em vez de declarar sucesso.

O CKEditor permanece **4.22.1**, sem atualização de segurança/licença nesta tarefa. Não se suprime o aviso/verificação de versão. Os assets de edição continuam locais; eventuais verificações de versão do próprio core não são uma nova dependência adicionada pelo Tool. `allowedContent: true` continua sendo compatibilidade, **não sanitização de HTML**. O fallback de ausência/falha síncrona não garante recuperação de todo erro assíncrono de asset/plugin.

A permissão/nonce são guardas operacionais para um laboratório confiável, não proteção criptográfica contra quem controla esse servidor. As fixtures Node tampouco são sandbox de segurança para executar código arbitrário não confiável.

### Referências

- [ADR 0049 — biblioteca de capas](../agents-ia/decisoes/0049-f8-05-blog-media-library.md).
- [ADR 0046 — shell/CKEditor](../agents-ia/decisoes/0046-f8-02-blog-no-shell-filtros-paginacao-ckeditor-e-import-reestilizado.md).
- [Guia histórico de boot da mídia](F8-05-A01-Inicializacao-Biblioteca-Midia.md) e [guia histórico de configuração do editor](F8-05-A02-Configuracao-Isolada-CKEditor.md).
- [Base pública verificada](https://github.com/davidcreator/davidcms/commit/f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a).
