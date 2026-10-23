# ADR 0056 — F8-12: Consolidação e validação integrada do painel

**Data:** 2026-10-23 · **Agente:** orquestrador / QA.
**Status:** entrega preparada, consolidação local aprovada; aceite final do operador pendente.
**Base:** `7301dc143ab5c936b44ce9efc7172b23c1472b0b`.

## Contexto

O usuário declarou a Tarefa11 concluída. Seus15 arquivos conferem com a entrega por blob Git na base7301dc1. As tarefas aceitas não são reabertas; faltava uma entrada única que consolidasse regressão local, escopo de cobertura, evidências e checklist transversal antes de decidir o encerramento da Fase8.

A inspeção de templates mostrou que classes como panel/btn ghost no dashboard/login são compatibilidade intencional F8-01, não prova de regressão. Usuários/Webhooks/Marketplace já têm Bootstrap/F8-03; confirms inline remanescentes são pendência delimitada, não motivo para refazer CRUDs. Idiomas foi migrado na Tarefa11. Não se conclui a fase por regex, publicação de código ou testes simulados.

## Decisão

1. Pacote **somente tools/docs/ADR/índice**. Zero alterações em dev/**, schema, ACL, CSRF, versões/dependências, dados/defaults, JavaScript de runtime, deploy ou integrações.
2. `tools/e2e/e2e_f812.sh` chama o runner Node com três modos: check local, self-test e review de JSON local. Sem http/backend/browser/prepare/clean ou bootstrap de aplicação.
3. Check usa whitelist exata das sete entradas F8-05..11, sempre único argumento **--check**, spawn sem shell, timeout por suíte e sem retry. Nunca chama baterias destrutivas antigas ou modos reais. Filhos recebem MIRROR fixo deste projeto, mesmo Node/PHP e ambiente mínimo; credenciais, autorizações HTTP e overrides de fontes não são repassados.
4. Requer PHPCLI8.1+/mbstring e Twig igual ao composer.lock, já instalado no projeto ou autoload local de QA explícito/confiável. Não instala pacotes. Preflight bloqueia Mirror de outro projeto, dependência ausente/divergente e relatório existente antes de executar suítes.
5. Fingerprint SHA256 de **95 entradas relevantes** (alvos/cores necessários e tools) antes/depois do lote. Relatório só resume status/exit/duração/sinais previstos, runtime e hashes; não exporta logs brutos, configs, credenciais ou dados de mensagens. Só passa se sete suítes e32guardas forem verdes e as fontes não mudarem.
6. Não somar grupos repetidos como testes distintos/E2Es reais. Contratos PHP/Twig executam de fato, mas DB/serviços/IO de sessão/DOM/fetch são fixtures conforme cada Tool; isso não prova HTTP/persistência/browser/ACL nativos/deploy. Outras áreas F8-01..04/Appearance dependem do aceite/checklist transversal, não entram por inclusão automática de scripts perigosos.
7. Entregar `docs/F8-12-Aceite-Painel.json` **pendente**. Operador copia fora do repo, preenche20checks transversais/ambiente/dados fictícios e decisão explícita. Confirmações anteriores de conclusão05..11 são registradas como operator-confirmed, sem exigir repetir tarefa aceita ou inventar evidência detalhada.
8. JSON nativo opcional pode substituir confirmação em cada tarefa; review confere tipo/tarefa/status/quantidade/estado/labels únicos/cleanup. Idiomas exige operation nondefault-sort-and-restore, restoration complete e defaultsUnchanged true. Fixture-data-only, etapas duplicadas/falhas, cleanup pendente ou relatório de tarefa diferente não são evidência aprovada.
9. Review exige relatório local passado compatível com o fingerprint atual, checklist completo e decisão do operador. Resultado máximo **ready-for-operator-closure**, sempre independentlyVerified=false, phaseClosed=false e doesNotAuthorizeDeploy=true. JSON é validação estrutural/local + declaração humana, não assinatura/autenticação de execução nem certificação independente.
10. Outputs opcionais são arquivos JSON novos/absolutos/0600, sem sobrescrever; progresso check vai a stderr e JSON a stdout. Review lê somente JSONs locais explicitamente referidos, com limite/arquivo regular/sem symlink, nunca executa conteúdos/URLs ou replica PII.

## Evidências executadas

- **32PASS/0FAIL** Node: whitelist/modos/ambiente/hash/timeout/propagação de falha, dependência, arquivos novos, formatos nativos, checklist pendente, diferença de fonte e ausência de encerramento/deploy automático.
- **7/7 entradas locais passaram** na mesma árvore parcial de fontes reais da base, com PHP8.4.26/Twig3.30.0/Node20.20.2.95 hashes estáveis antes/depois. PHP/Twig reais; DB/DOM/fetch em fixtures. Nenhum HTTP/DB do CMS, navegador, produção ou deploy.
- IO do relatório0600/sem segredo de ambiente, review pendente, review positivo **somente com modelo sintético de checklist em fixture**, fonte alterada recusada e wrapper de teste exit1 impedindo lote verde. Esses modelos foram removidos, não são aceites de operador reais nem acompanham o ZIP como homologação.
- Sintaxe Bash/CJS e checksums/patch apply/reverse/CRC verificados na embalagem. Não há PHP alterado nesta tarefa.

## Cobertura e pendências separadas

O checklist inclui shell/login/dashboard, Blog/RTE/capa/importador, portfólio, CMS, ferramentas, inbox, idiomas, usuários/grupos/perfil, settings/site-info, Marketplace/Webhooks somente UI/proteções sem operação externa, temas/mobile/contraste/i18n, outro perfil/CSRF, invariantes/limpeza, smoke público somente leitura, cache/console e revisão das dívidas/privacidade.

Permanecem decisões separadas: CKEditor4.22.1 EOL/licença/sanitização; upload compartilhado/órfãos/referências/symlinks; saneamento da paginação global/aria-labels; confirms inline em telas F8-03; avaliação/roadmap visual do catalog; F9 custom fields como fase própria (ADR0047). Não declarar resolvidos por check local.

Baterias históricas que instalam/dropam banco, alteram grants/defaults ou fazem integrações não são incluídas. F801/F802 citados historicamente não estão como entradas canônicas versionadas na base; não se inventa sua execução. Nenhuma fase seguinte é iniciada automaticamente.

## Consequências

Fica disponível regressão local repetível e gate de revisão transparente, sem reexecutar mutações de tarefas aceitas. Aplicação do ZIP/check verde não é homologação visual nem encerramentoF8. Após revisão, o usuário decide explicitamente fechamento ou pendências; deploy/esquema/fase9 continuam autorização separada.

Guia e operação: [F8-12 — Consolidação](../../docs/F8-12-Consolidacao-Validacao-Painel.md).
