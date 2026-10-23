# Fase 8 · Tarefa 12 — Consolidação e validação integrada do painel

> Objetivo: consolidar checks locais, cobertura, evidências e aceite transversal sem modificar runtime ou repetir automaticamente operações de tarefas aceitas.

**Data:** 2026-10-23 · **Base pública:** `7301dc143ab5c936b44ce9efc7172b23c1472b0b`.
**Estado:** pacote preparado; consolidação local verde; checklist/homologação final do operador pendentes. Tarefa11 concluída conforme informado,15fontes públicas iguais à entrega.

## Sumário

1. [Escopo e fronteiras](#1-escopo-e-fronteiras)
2. [Arquivos e aplicação](#2-arquivos-e-aplicação)
3. [Check local consolidado](#3-check-local-consolidado)
4. [Checklist e confirmações anteriores](#4-checklist-e-confirmações-anteriores)
5. [Revisão de evidências](#5-revisão-de-evidências)
6. [Cobertura e pendências](#6-cobertura-e-pendências)
7. [Verificação, rollback e próximo passo](#7-verificação-rollback-e-próximo-passo)

## 1. Escopo e fronteiras

**Só QA/documentação, zero dev/**. Não altera telas/SQL/schema/ACL/CSRF/idiomas/defaults/uploads/editor/core/dependências/versionamento, não instala o CMS, não envia contato/e-mail/webhook, não conecta Marketplace, não faz OAuth/pagamento ou deploy.

Entrada nova: `tools/e2e/e2e_f812.sh`. Ela consolida apenas as entradas **F8-05..11 em --check**, sem reabrir seu aceite. Não executa --http, browser, prepare/clean, instalador ou suítes legadas que recriam DB/grants/defaults. Não há login ou armazenamento de cookies.

Check verde significa **contratos locais**, não persistência/browser/ambiente real. Review de JSON é estrutural + declaração do operador, **não comprova autenticidade ou execução independente**. Nenhum comando encerra fase/autoriza deploy ou iniciaF9 automaticamente.

## 2. Arquivos e aplicação

**Seis arquivos de projeto:**

- Novos `tools/e2e/e2e_f812.sh` e `e2e_f812.cjs`.
- Este guia e [modelo pendente de aceite](F8-12-Aceite-Painel.json).
- [ADR0056](../agents-ia/decisoes/0056-f8-12-consolidacao-validacao-painel.md).
- `docs/README.md`, apenas linha23.

Cinco novos/um modificado, acompanhados de F8-12.patch, MANIFESTO-F8-12.json, SHA256SUMS. Sem árvore completa/vendor/configs/mensagens/credenciais ou relatórios falsos de homologação no ZIP.

1. Conferir `git rev-parse HEAD`: base7301dc143ab5c936b44ce9efc7172b23c1472b0b. Revisar se índice/alvos mudaram; não sobrescrever trabalho concorrente.
2. Branch/PR de trabalho para staging conforme projeto, backup do índice.
3. Extrair fora do repo e conferir `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, ENTREGA é extração absoluta:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-12.patch"
git apply --whitespace=error "$ENTREGA/F8-12.patch"
git diff --check
bash tools/e2e/e2e_f812.sh --self-test
```

Alternativa manual: copiar só seis arquivos nos caminhos do manifesto. Sem copiar dev/vendor/árvore inteira ou reaplicar ZIP anterior. Não há upgradeSQL, sincronização de runtime web ou invalidação de CSS a fazer nesta tarefa; publicar tools não comprova deploy/homologação.

Commit sugerido:

```text
test(e2e): consolidar validação local e aceite do painel F8
```

## 3. Check local consolidado

```bash
# Arquivo novo absoluto, em diretório já existente:
export F812_REPORT='/tmp/novo-f812-local.json'
bash tools/e2e/e2e_f812.sh --check
```

Padrão sem argumento é --check. Progresso PASS/FAIL vai a stderr; stdout é um JSON. F812_REPORT é opcional, escrito0600/sem sobrescrever. Preferir essa variável a redirecionar para arquivo antigo. Em preflight bloqueado, sai2 sem criar relatório de lote.

### Requisitos

Node20+/Bash, PHPCLI8.1+/mbstring e **Twig igual ao composer.lock da árvore testada**, já instalado no projeto. Por padrão usa dev/system/storage/vendor/autoload.php; TWIG_AUTOLOAD absoluto local de QA confiável é permitido, com a mesma versão do lock. PHP_BIN é opcional; todos os filhos usam esse PHP e o mesmo Node do consolidador. Nenhuma instalação automática.

Somente fontes/autoload confiáveis: **não é sandbox de segurança** para executar código desconhecido. Não inicializa config/framework do CMS; os runners PHP existentes injetam fixtures e usam Session sem construtor.

MIRROR é fixado ao **dev deste projeto**. Se a variável estiver exportada para outra cópia, o consolidador bloqueia em vez de misturar fontes; desexportar/revisar conscientemente. B/FUI/ALLOWs/credenciais/overrides de JS e preloads de shell/Node não são repassados aos filhos. Nenhum segredo/valor desses é impresso.

### Whitelist executada

| Entrada | Escopo local (sem somar duplicações) |
| --- | --- |
| e2e_f805.sh --check |32guardas/modelos +18RTE +10mídia simulados; contratos locais. |
| e2e_f806.sh --check |30PHP/Twig +6DOM +16auxiliaresHTTP em fixtures. |
| e2e_f807.sh --check |40PHP/Twig +9DOM +9auxiliares novos e16compartilhados. |
| e2e_f808.sh --check |45PHP/Twig +12DOM +10novos e25compartilhados. |
| e2e_f809.sh --check |36PHP/Twig +9DOM +8novos e25compartilhados. |
| e2e_f810.sh --check |40PHP/Twig +8novos e16compartilhados. |
| e2e_f811.sh --check |40PHP/Twig +8novos e16compartilhados. |

Spawn sem shell; argumento único --check, timeout90s por suíte e **sem retry**. Uma falha impede lote verde, mas registra as demais. Sinais previstos precisam aparecer além de exit0, evitando “ok” vazio. stdout bruto/HTML/erros de filhos não são incluídos no relatório; executar o Tool canônico localmente se precisar diagnosticar falha sem compartilhar PII.

Não somar grupos compartilhados para divulgar centenas de E2Es distintos. PHP/Twig executam de fato, mas DB/serviços/IO sessão/DOM/Bootstrap/fetch são fixtures conforme cada runner; diretórios/PNGs privados criados nesses testes são removidos, não arquivos reais do CMS.

### Integridade e saída

SHA256 de **95 entradas relevantes**: alvos/tools das suítes e classes/lock necessários, sem config.php, credenciais, vendor/CKEditorcore, todos os arquivos do sistema ou runtime inteiro. Fingerprint antes/depois deve coincidir. Isso detecta mudança nas entradas verificadas, não prova que um código arbitrário não teve efeito externo nem que o mesmo conteúdo foi implantado.

JSON kind=local-contracts-batch, status=passed/failed, versões PHP/Node/Twig/lock,32selfTests, sete suítes com status/exit/duração/sinais, sourceFingerprint/sourceUnchanged e lista relativa de hashes. nativeWorkflowsExecuted=false e phaseClosed=false sempre. Não inclui cookies, senhas, configs ou logs de contato.

Saídas:0 lote local passou;1 falha local;2 bloqueio/requisito/IO. Nenhum deles concede homologação de servidor, navegador ou produção.

## 4. Checklist e confirmações anteriores

Copiar o modelo fora do repositório para não versionar aceite/PII inadvertidos:

```bash
cp docs/F8-12-Aceite-Painel.json /tmp/f8-12-aceite-operador.json
```

**O arquivo entregue é pendente, não prova de aprovação.** Preencher somente depois de verificar os20checks transversais no laboratório/staging com dados fictícios. Não inserir mensagens, telefones/e-mails reais, tokens, cookies, senhas, IDs de clientes ou URLs privadas.

Para uma revisão completa:

- kind passa de operator-checklist-template para operator-checklist;
- status=passed e finalDecision=approved-for-phase-closure, somente quando essa for a decisão real do operador;
- environment.kind=disposable-local ou staging, syntheticData=true;
- localReport é caminho do JSON real do --check, absoluto ou relativo à cópia do checklist;
- todos os checks listados status=pass; não apagar IDs, duplicar ou declarar pass para dívida “resolvida” quando só foi registrada;
- manter referenceBase do modelo, que identifica o contrato desta entrega. HEAD pode ter o commit desta ferramenta/documentação; o fingerprint liga a revisão às entradas atuais, não a uma alegação de deploy.

### Tarefas05..11 já aceitas

O modelo já registra suas conclusões como **operator-confirmed**, status=accepted, reference da confirmação do usuário nesta conversa e path=null. Isso não inventa execução detalhada e **não exige repetir tarefa aceita**.

Se já existe um JSON nativo/HTTP real e quiser anexá-lo, trocar somente essa tarefa para method=native-json e indicar path local `.json`; manter accepted. O review verificará sua estrutura. Não criar JSON sintético como prova. Uma fixture-data-only ou relatório de auxiliares simulados não serve.

Confirmação humana e JSON nativo validado continuam com assurance explícita; nenhum é autenticado criptograficamente ou verificado de forma independente pelo review. Manter path de evidência falha junto com operator-confirmed não a mascara: é recusado. O programa não impede alteração/omissão maliciosa de um arquivo, por isso nunca certifica autenticidade.

## 5. Revisão de evidências

```bash
# Leitura de JSONs locais; não executa fluxos:
bash tools/e2e/e2e_f812.sh --review /tmp/f8-12-aceite-operador.json

# Opcional arquivo NOVO para resultado:
export F812_REVIEW_REPORT='/tmp/novo-f812-review.json'
bash tools/e2e/e2e_f812.sh --review /tmp/f8-12-aceite-operador.json
```

F812_REPORT pertence apenas ao check; pode continuar exportado sem sobrescrever o relatório local na revisão. F812_REVIEW_REPORT tem regra novo/absoluto/0600. Inputs são JSONs regulares locais/sem symlink, até512KiB, sem URLs remotas/eval/require. O conteúdo não é executado nem copiado inteiro para a saída.

### Contratos opcionais de JSON real

| Tarefa | Tipo/etapas esperados | Condições adicionais |
| --- | --- | --- |
| F8-05 |native-browser-workflow /14 |statuspassed e cleanupcomplete. |
| F8-06 |http-workflow /10 |idem. |
| F8-07 |http-workflow /14 |idem. |
| F8-08 |http-workflow /15 |idem. |
| F8-09 |http-workflow /13 |idem; URL externa não é teste de integração. |
| F8-10 |http-workflow /12 |idem; não expor conteúdo/PII de mensagem. |
| F8-11 |http-workflow /10 |operation=nondefault-sort-and-restore, restorationcomplete, defaultsUnchangedtrue; não en-us. |

Task string correspondente, labels únicas, todas statuspass e quantidade exata; cleanup/probe pendentes e estado failed/blocked/running são recusados. Esses arquivos **não precisam existir para manter a confirmação anterior do usuário**. Não rodar --http/--browser de novo automaticamente para preencher a tabela; qualquer repetição real é decisão/ambiente/autorização separados.

Relatório local precisa ser passed, sete suítes/32guardas verdes, dependência conforme lock e fingerprint igual ao atual. Se fontes mudaram depois, repetir **apenas a regressão local** conscientemente e atualizar localReport, não fingir que o antigo continua válido.

### Resultado e códigos

- status=pending, saída1: decisão/check/requisito/evidência ainda não permite revisão pronta. Template pendente permanece assim.
- status=ready-for-operator-closure, saída0: estrutura local e declaração/checklist completos; **somente pronto para decisão explícita do usuário**.
- status=blocked, saída2: formato/schema/arquivo/requisito inválido. errorCode não replica dados do input.

Sempre independentlyVerified=false, requiresExplicitPhaseClosure=true, phaseClosed=false, doesNotAuthorizeDeploy=true. Não são alterados arquivos do checklist, DB, runtime, GitHub ou deploy. Saída contém só status/códigos/assurance/tipos/quantidades e hashes das entradas locais/evidências; não labels arbitrárias, notas ou PII dos relatórios.

## 6. Cobertura e pendências

Os20checks cobrem: shell/login/dashboard; Blog/RTE/capa/importador; portfólio; CMS; ferramentas; inbox; idiomas; usuários/grupos/perfil; settings/site-info; Marketplace/Webhooks UI/proteções sem operações externas; temas/mobile/contraste/i18n; outro perfil/CSRF; invariantes/limpeza; smoke público **somente leitura**; assets/cache/console; revisão de dívidas/privacidade.

**Não há nova homologação automática de F8-01..04/Appearance.** Parte das baterias históricas citadas (F801/F802) não está como entrada canônica versionada na base; outras instalam/dropam banco, mudam grants/defaults ou acionam integrações. Elas são deliberadamente excluídas, não consideradas “passadas”. Painel de Marketplace não configurado pode ser revisado como UI nesse estado, sem alegar pagamento/OAuth aprovado. Logs webhooks não exigem disparar entrega. Smoke do site não inclui enviar contato/e-mail nem retrabalho visual/Three.js.

Pendências conhecidas, para decisão separada e não “resolvidas” por check verde:

- CKEditor4.22.1 EOL, suporte/licença e sanitização de HTML;
- órfãos/arquivo compartilhado/referências/symlinks no upload compartilhado;
- saneamento global da paginação/aria-labels;
- confirms inline remanescentes em listas F8-03 (usuários/grupos/webhooks/Marketplace), sem refazer CRUDs aqui;
- roadmap/aceite visual próprio do catalog; F9 custom fields tipoACF é fase própria (ADR0047), não iniciada por este pacote.

Dashboard/login mantêm classes próprias F8-01; um grep por panel/btn ghost não é prova de tela não migrada. Nenhuma tela é reaberta por detector genérico.

## 7. Verificação, rollback e próximo passo

Executado pelo agente, PHP8.4.26/Twig3.30.0/Node20.20.2:

- **32PASS/0FAIL** guardas/modelos de CLI/whitelist/ambiente/fingerprint/timeout/saída/tipos JSON/revisão pendente/não fechamento.
- **7/7 suítes locais passadas** na mesma árvore parcial de fontes reais,95hashes estáveis, sem DB/HTTP do CMS/browser/deploy.
- Relatório0600/sem segredo de ambiente; template pendente recusado; revisão IO positiva apenas com **modelo sintético de checklist em fixture temporária**, removido. Não é aceite de operador real e nenhum relatório nativo falso é entregue.
- Fonte alterada invalida relatório anterior; uma suíte exit1 impede lote verde, sem retry, registrando as outras seis.
- Bash/CJS, links, hashes, patchcheck/apply/reverse e CRC verificados na embalagem. Não há PHP ou runtime alterado nesta entrega.

Não houve produção/DB/browser/SMTP/webhook/pagamento, escrita GitHub, deploy, Actions, PHPStan/PHPUnit de todo projeto ou fechamento de fase executados aqui. Não houve clone/vendor/árvore de arquivos inteira; só fontes necessárias aos checks e dependências CLI de QA isoladas.

Rollback de código, patch original e índice sem mudanças posteriores:

```bash
git apply -R --check "$ENTREGA/F8-12.patch"
git apply -R "$ENTREGA/F8-12.patch"
```

Restaura o índice/remove cinco novos arquivos de tools/docs/ADR; não reverte tarefas05..11 nem dados/runtime. Se não aplicar, parar/revisar, sem reset--hard/limpeza ampla. Relatórios/checklists fora do repo ficam como evidência do operador; não são apagados automaticamente.

**Próximo:** aplicar o pacote, rodar check local, preencher/revisar checklist conforme o que realmente foi verificado e confirmar explicitamente o aceite/conclusão ou listar pendências. Só depois decidir encerramentoF8 e próxima fase. Não se infere fechamento ou deploy de confirmação genérica de aplicação/GitHub.

Referências: [ADR0056](../agents-ia/decisoes/0056-f8-12-consolidacao-validacao-painel.md), [modelo pendente](F8-12-Aceite-Painel.json), [F8-11](F8-11-Idiomas-no-Painel.md), [F8-10](F8-10-Mensagens-Contato.md), [base pública](https://github.com/davidcreator/davidcms/commit/7301dc143ab5c936b44ce9efc7172b23c1472b0b).
