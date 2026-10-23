# Fase 8 · Tarefa 10 — Mensagens de contato

> Objetivo: migrar a caixa/lista/detalhe de contato ao shell e proteger leitura automática/status/delete, mantendo o CRUD assimétrico, sem editar conteúdo ou enviar e-mails.

**Data:** 2026-10-23 · **Base:** `653fb677b6068af28d706d766064a47ef0aefffe`.
**Estado:** usuário escolheu Ler automático por POST protegido; implementação preparada/testada localmente. Aplicação e homologação real pendentes. Tarefa09 concluída conforme usuário; seus17 arquivos públicos conferem com a entrega, sem inventar backend/deploy.

## Sumário

1. [Decisão de leitura e contratos](#1-decisão-de-leitura-e-contratos)
2. [Arquivos, aplicação e cache](#2-arquivos-aplicação-e-cache)
3. [Tool local e evidências](#3-tool-local-e-evidências)
4. [Fixture fictícia: somente dados](#4-fixture-fictícia-somente-dados)
5. [E2E HTTP controlado](#5-e2e-http-controlado)
6. [Aceite no navegador](#6-aceite-no-navegador)
7. [Falhas, limpeza e rollback](#7-falhas-limpeza-e-rollback)
8. [Limites](#8-limites)

## 1. Decisão de leitura e contratos

Esta tarefa é só a caixa `communication/contact_message`. Sem criar mensagem no admin, editar conteúdo, upload, SEO, editor ou alteração do formulário público.

**Escolha expressa do usuário:** manter “Ler” automático com POST protegido.

- **GET `.read|ID` apenas visualiza**, mesmo Novo; readonly e links diretos nunca alteram status.
- Com modify, botão **Ler da lista é POST+CSRF** na rota existente; Novo0 viraLido1 uma vez e redireciona paraGET detalhe. Lido1/Respondido2 não são regravados/alterados pelo Ler.
- Readonly usa linkGET e não vê botões status/delete. Backend nega os três POSTs sem modify.
- `.status|ID` e `.delete|ID` exigem POST+modify+CSRF, sem bypass.
- Status aceita somente0/1/2 canônicos; x/arrays/01/bools/floats não viramNovo por cast.
- `from=read` preservado. MarcarNovo agora permaneceNovo ao voltar ao detalhe; GET não desfaz a ação.
- Resposta continua **mailto manual**, assunto Re:/corpo citado codificados; marcarRespondido não envia e-mail. O Tool nunca segue mailto.

### Lista e detalhe

Busca por nome/e-mail/assunto, não por corpo/telefone/IP. Filtro status, contadores das abas acompanhando busca, paginação20/total/recuo, mesma ordem **date_added DESC, contact_message_id DESC**. `getMessages(?int)` antigo sem opções segue completo; `getCounts()` segue global sem argumentos. Filtro direto inválido inteiro no model preserva sua semântica anterior, não vira “todas”.

Cards/tabela responsiva/badges temáticos/modal delete, sem cores inline/confirm(). Corpo é **texto escapado** com pre-wrap/quebras preservadas, nunca HTML ou editor. Nome/e-mail/telefone/assunto/mensagem/IP/data continuam dados recebidos somente leitura. Não há política nova de retenção ou exportação.

Rotas mantidas: base, `.read|ID`, `.status|ID`, `.delete|ID`. IDs reais, campos de ações status/from e rótulos0Novo/1Lido/2Respondido preservados. Sem `.add`/`.edit`/`.upload`, _description, schema/DDL/grants, `system/**`, `catalog/**`, `install/**`, SMTP, outros CRUDs, Appearance/Localization, admin.js/ASSET_V/Composer/Blog alterados. Sem JavaScript runtime novo; forms nativos e modal global existente.

## 2. Arquivos, aplicação e cache

**15 arquivos de projeto** (dez modificados/cinco novos):

- Runtime8: `dev/admin/controller/communication/contact_message.php`, model correspondente, templates `contact_message_list.twig`/`contact_message_read.twig`, idiomas en-us/pt-br/es-eu e `dev/admin/view/css/admin.css`.
- Tools3 novos: `tools/e2e/e2e_f810.sh`, `e2e_f810_contracts.php`, `e2e_f810_http.cjs`.
- `dev/tests/e2e/e2e_f215.sh`: só compatibilidade CSRF login/status/delete, verificação GET puro e POSTLer. Setup/fixtures SQL/grants/reset permanecem destrutivos e **não são executados pelo Tool novo**; não se declara auditoria completa dessa bateria.
- Este guia, índice docs/README.md (linha21), [ADR0054](../agents-ia/decisoes/0054-f8-10-mensagens-contato-no-shell.md).

Acompanhantes: F8-10.patch, MANIFESTO-F8-10.json, SHA256SUMS. Sem projeto inteiro/vendor/configs/credenciais ou mensagens reais. O script antigo mantém suas fixtures públicas preexistentes.

1. Conferir base com `git rev-parse HEAD`:653fb677b6068af28d706d766064a47ef0aefffe. Se alvos mudaram depois, revisar hashes/diff; não sobrescrever trabalho concorrente.
2. Branch/PR para staging e backup, não produção direta.
3. Extrair ZIP fora do repositório e conferir `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, `ENTREGA` é extração absoluta:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-10.patch"
git apply --whitespace=error "$ENTREGA/F8-10.patch"
git diff --check
bash tools/e2e/e2e_f810.sh --check
```

Alternativa: copiar só os15 alvos. Sem árvore inteira/vendor/--delete/reaplicar ZIP anterior/upgradeSQL. Sincronizar apenas alvos e invalidar CSS/PHP/Twig em homologação. **ASSET_V não muda**: conferir cache de admin.css. Código publicado/dev alterado não prova deploy.

CSS novo só `.contact-inbox-list`/`.contact-message-read`, prefixo anterior completo intacto; paginação normaliza só `&amp;amp;` no HTML gerado com queryURL-encoded. Library/aria-labels globais não mudam. Corpo usa variáveis do tema, não fundo/cor fixos.

Commit sugerido:

```text
feat(admin): migrar inbox com leitura POST protegida e paginação
```

## 3. Tool local e evidências

```bash
bash tools/e2e/e2e_f810.sh --check
bash tools/e2e/e2e_f810.sh --help
```

Requer PHP CLI8.1+/mbstring/Twig3 do Composer do projeto, Node20+ com fetch/Headers.getSetCookie e Bash. Helpers F8-05/F8-06 já publicados são usados sem seus fluxos. Sem instalação automática/dependência nova da aplicação.

Autoload Twig padrão: dev/system/storage/vendor/autoload.php. TWIG_AUTOLOAD absoluto permite QA isolada; MIRROR escolhe dev testado. Apenas fontes/autoload confiáveis: não é sandbox para código desconhecido. Não carrega config/framework da aplicação nos testes locais.

| Grupo executado pelo agente | Resultado | Natureza |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP runtime +runner. |
| Contratos PHP/Twig | **40 PASS/0FAIL** | PHP8.4.26/Twig3.30.0 reais, DB/serviços/model do controller/IO sessão em fixtures. |
| Auxiliares HTTP novos | **8 PASS/0FAIL** | Parser/marker/identidade/privacidade/formulários em simulação. |
| Auxiliares F8-06 compartilhados | **16 PASS/0FAIL** | Respostas/fetch simulados. |
| Negativo na caixa antiga | **16 PASS/24FAIL**, saída1 | Falha esperada em PHP/Twig. |
| Sintaxe JS/Bash | Aprovada | f215 somente bash-n, não executado. |

Regressões locais F8-09/08/07/06/05 também passaram sem falhas; não reabre seus aceites nem comprova backend/browser real.

Classes reais/controller/model são executados e Twig strict/autoescape compila/renderiza templates. SQL capturado, não enviado a DB; Session real sem construtor, só dados em memória. Fixtures não contêm mensagens pessoais, nem há chamada de mailer/formulário público/SMTP.

**Não chamar os64 casos de E2Es reais aprovados.** Nenhum servidor/DB/navegador, mensagem real, HTTP/e-mail real, GitHub write/deploy/Actions/PHPStan/PHPUnit completo/matrizPHP executados aqui. Sem JS runtime novo: comportamento visual/modal precisa do navegador.

## 4. Fixture fictícia: somente dados

Por privacidade, o Tool **não cria nem envia contato público**, não envia e-mail e não semeia DB. O endpoint público existente `dev/catalog/controller/common/contact.php` pode notificar o dono do site: e-mail@example.invalid da fixture não impede essa notificação.

Primeiro, gerar somente os dados:

```bash
bash tools/e2e/e2e_f810.sh --fixture > /tmp/f810-dados-ficticios.json
```

Requer sóNode/helpers, nãoPHP, B/FUI/MIRROR ou banco. Saída kind=fixture-data-only, marker f810-<16hex>, campos name/email/telephone/subject/message e aviso de que nada foi salvo/enviado. Pode usar F810_MARKER previamente gerado para reproduzir os mesmos dados.

**Preparar manualmente uma mensagem correspondente somente no laboratório isolado autorizado**, pelo procedimento local já aprovado. Se usar o formulário público local, antes confirmar notificação em **captura/offline** e integrações produtivas desligadas. Se não puder garantir isso, não submeter o formulário; o pacote não configura/verifica SMTP/captura remotamente e não autoriza envio externo. Nunca executar f215 inteiro para obter uma fixture: ele recria o banco.

Copiar os cinco campos exatos do JSON, sem dados/autofill reais; telefone vazio; e-mail @example.invalid; corpo inclui texto/acentos e `<b>` literal para verificar escape. No formulário público, não preencher honeypot company; CSRF da própria UI é normal.

Obter ID real pela lista/detalhe, nunca presumir1. Se clicou Ler para ver a URL/ID, marcar a fixture como **Novo** pelo botão e confirmar statusNovo antes do Tool; o novo GET não voltará a marcá-la. Não clicar Ler novamente antes do ensaio. A fixture deve começar em0Novo e não ter sido vinculada/alterada por outro operador.

O Tool verifica nome/e-mail/assunto/ID na linha antes de buscar detalhe, depois telefone/corpo exatos antes de qualquer POST. Se marker/ID/dados/status/permissão não conferirem, bloqueia sem operar conteúdo desconhecido. Não substitua por mensagem real ou cópia de conteúdo pessoal.

## 5. E2E HTTP controlado

Laboratório com reamur_test, conta fictícia access/modify communication/contact_message, fixture exata preparada acima. Guardas herdadas F8-06/F8-05: B/FUI/MIRROR explícitos loopback, configs literais wizard com DB_DATABASE=reamur_test/hostname loopback/HTTP_SERVER coerente, `.f805-disposable`, fontes/assets CMS/inbox MIRROR=FUI sem symlinks. Marker do laboratório mantém nome por compatibilidade; não transforma DB real em descartável.

**Proibido produção/dados reais/ambiente misto/túnel/proxy produção/concorrência/integrações produtivas.** Operador confirma runtime/PHP/DB reais; config/nonce/hash não atesta proxy/backend mal configurado.

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd -P)/dev"
# Só depois de confirmar laboratório/DB/configs/fixture:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F810_HTTP='YES_WRITE_REAMUR_TEST'
read -r -p 'Usuário fictício: ' F810_USERNAME
read -r -s -p 'Senha fictícia: ' F810_PASSWORD; printf '\n'
read -r -p 'Marker exato do JSON: ' F810_MARKER
read -r -p 'ID REAL da mensagem fictícia: ' F810_MESSAGE_ID
export F810_USERNAME F810_PASSWORD F810_MARKER F810_MESSAGE_ID
export F810_REPORT='/tmp/novo-resultado-f810.json'  # novo/inexistente
bash tools/e2e/e2e_f810.sh --http
unset F810_USERNAME F810_PASSWORD ALLOW_F810_HTTP
```

Não enviar senhas/tokens nem mensagens pessoais ao chat. Report opcional absoluto novo/diretório existente,0600, sem sobrescrever evidência. Preflight bloqueado sai2 sem relatório de workflow; detalhes são omitidos por privacidade. Consultar requisitos sem remover guardas/desativar TLS/alterar configs reais para forçar verde. Config dinâmica/DBDocker não-loopback pode exigir avaliação pontual.

### Fluxo de12etapas preparado, NÃO executado pelo agente contra backend

1. Probe nonce `FUI/admin/view/js/f810-probe-<nonce>.txt`, conferido por B antes de credenciais.
2. Login único CSRF/cookies só em memória, sem jar/storage state.
3. Localizar somente fixture própria: busca marker, nome/e-mail/assunto/ID exatos, statusNovo e botão Ler POST/modify.
4. GET detalhe valida corpo/telefone exatos e permaneceNovo.
5. GET nos três idiomas sem alterar estado.
6. POST Ler sem token não marcaLido.
7. POST Ler com token marcaLido, redirecionaGET e reload mantémestado.
8. Status sem token e valores x/9/array não mutam.
9. MarcarRespondido persiste e filtros refletem; nenhum e-mail enviado ou mailto seguido.
10. MarcarNovo permaneceNovo apósGET; LerPOST válido volta aLido.
11. GETstatus/delete e POSTdelete sem token não removem/mudam.
12. Revalidar fixture inteira e excluir somente o ID próprio; conferir ausência na busca.

Sem formulário público, SQL/reset/recreate/seed/upgrade, e-mail/SMTP, upload, mailto ou acesso a mensagem por ID adivinhado. POSTs não são repetidos após timeout/307/308 e redirects externos não recebem novo request/cookies. Falha após tentativa de mutação preserva a fixture para inspeção, sem reset para obter verde.

JSON real: kind=http-workflow, marker/fixtureId/status/12etapas/cleanup. **Aceite:** passed +12etapas pass +cleanup complete. Aprova sóHTTP, não navegador. Não inclui corpo/nome/e-mail/telefone/IP/assunto/mailto, HTML completo, credenciais/cookies/tokens. Erros do workflow têm descrição genérica para não vazar contato/backend; veja qual etapa falhou e inspecione localmente.

Cleanup corresponde somente à fixture/probe próprios; não remove mensagens alheias, dados normais de login/sessão/rate limit ou sequências auto-increment.

## 6. Aceite no navegador

- [ ] Lista/detalhe no shell, desktop/mobile/skins/claro/escuro legíveis, sem overflow/cor fixa inadequada; corpo preserva quebras como texto.
- [ ] Busca nome/e-mail/assunto e status, contagem de abas coerente com busca, página2/recuo e reset inclusive0. Volume21+ no servidor exige dados fictícios próprios;45 foram só fixture.
- [ ] GET direto de mensagemNova não grava, inclusive perfilreadonly; botãoLer do modify usaPOST+CSRF e marcaNovo→Lido uma vez/PRG.
- [ ] POSTLer emLido/Respondido não muda estado; readonlyPOST énegado.
- [ ] Novo/Lido/Respondido viaPOST token; marcarNovo no detalhe permaneceNovo apósredirectGET; x/arrays/9/sem token não mutam.
- [ ] Cancelar modaldelete preserva; confirmar enviaPOST+CSRF; GET ou token inválido nunca excluem. Apenas fixture fictícia nesta homologação.
- [ ] Conteúdo/nome/assunto potencialmente maliciosos são escapados, sem input de edição/editor/upload/SEO/add/edit no admin.
- [ ] Reply permanece mailto manual pré-preenchido; não clicar/enviar no ensaio automático, marcarRespondido não dispara correio.
- [ ] Três idiomas e readonly sem status/delete; outro perfil real não foi testado automaticamente.
- [ ] Outros CRUDs/Blog/Appearance/site/e-mail/configs intactos. Não concluirFase8 automaticamente.

ConfirmarTarefa10 somente após aplicação/homologação do usuário.

## 7. Falhas, limpeza e rollback

Timeout pode ocorrer depois da gravação. Não repetirPOST àscegas. Report omite dados pessoais; conferir fixture localmente por marker/ID/campos exatos. Fixture errada, real ou não comprovada não deve ser lida/alterada/excluída pelo Tool. Não adivinharID ou usar wildcard/limpezaSQL/reset para forçar verde.

Se o fluxo falhar, status pode ter mudado: preservar para inspeção. Só nova fixture fictícia/reinício consciente após esclarecer o resultado; não pegar mensagem de cliente para substituir. Nenhuma mensagem extra é criada pelo Tool.

Probe removido emfinally só se regular/conteúdo igual. Processo morto/probe alterado pode deixá-lo; inspecionar somente f810-probe-<nonce>.txt exato, não limpar view/js amplamente. Falha sinalizafailed/pending.

Rollback com patch original/backup e alvos sem alterações posteriores:

```bash
git apply -R --check "$ENTREGA/F8-10.patch"
git apply -R "$ENTREGA/F8-10.patch"
```

Parar/revisar se não aplicar; sem reset--hard. Restaura dez/remove cinco novos, sem reverter tarefas aceitas. Limpar antes somente fixture/probe próprios: rollback não desfaz dados/relatórios. Sincronizar apenas alvos revertidos/invalidação normal de cache.

A base antiga volta a marcarLido emGET e sem CSRF/modify, além das limitações antigas de status; risco conhecido, não recomendação de operação.

## 8. Limites

Sem mensagens pessoais, backend/DB/HTTP/browser/e-mail/produção/deploy/GitHubwrite ouActions executados aqui. Sem PHPStan/PHPUnit completo/matrizPHP. Não houve runtimeJS novo nem prova de modal/contraste/foco nativos.

F215 antigo permanece destrutivo/não auditado integralmente: só compatibilidade login/CSRF/contratoLer, nunca executado peloTool; não se prometem15checks reais verdes. Nenhuma configuraçãoSMTP/captura/publiccontact alterada, nem política nova de retenção/exportação.

Core/aria-labels globais de paginação intactos. Sem editor/upload/HTMLativo ou bibliotecaBlog. Guardas dependem de laboratório confiável; não atestam DB/PHP/proxy incorreto. Código publicado não comprova deploy; conclusãoT09 informada pelo usuário, igualdade17fontes conferida independentemente.

Referências: [ADR0054](../agents-ia/decisoes/0054-f8-10-mensagens-contato-no-shell.md), [F8-09](F8-09-Ferramentas-Conectadas.md), [F8-06](F8-06-Paginas-CMS.md), [base pública](https://github.com/davidcreator/davidcms/commit/653fb677b6068af28d706d766064a47ef0aefffe).
