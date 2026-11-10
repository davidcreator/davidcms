# Fase 9 · Tarefa 27 — Plano E2E de staging e release gate

**Situação: BLOQUEADO / NOT RUN.** F9-27 é a etapa de testes funcionais de release; não executar até autorização explícita para o ambiente descartável. Os limites atuais da conversa proíbem PHP, SQL, navegador, app e E2E runtime, então este pacote não os tenta contornar.

**HEAD consultado:** `448dd61f467e8ce2b4a0d5bce481345dace4ca77` (F9-26, 2026-10-25). **Base F9-12:** não confirmada; não houve acesso ao banco. O roadmap exige confirmar F9-12 antes de E2E DB. Não marque `ready` por inferência.

## Condições para abrir o gate

Todas devem estar explicitamente confirmadas pelo responsável antes de execução:

1. Autorização por escrito para as verificações PHP/SQL/browser/app/E2E necessárias e para usar o staging identificado.
2. Staging isolado, descartável e sem conexão/credenciais/rotas para produção; snapshots/backups sintéticos e rollback definidos.
3. Commit/tag alvo fixado, artefatos e migrations revisados pelo responsável. Confirmar formalmente F9-12 já instalada no staging ou plano aprovado para instalá-la; não aplicar automaticamente.
4. Conta de teste e mídia sintética sem dados pessoais; usuários/ACL de teste sem privilégios excessivos.
5. Política de logs sem payload sensível, URL assinada ou credencial. Coletar só resultados/timestamps e hashes dos artefatos.
6. Critérios de parada e responsável presente definidos; não continuar após divergência de schema, dados persistidos inesperadamente, saída para produção ou validação fail-open.

Se qualquer condição falhar: **ABORTAR**. Nenhum ambiente foi verificado ou preparado nesta tarefa.

## Matriz de casos — planeada, não executada

| Grupo | Casos / aceite | Estado |
|---|---|---|
| Contrato/compatibilidade | V1 permanece válido e sem rewrite ao abrir; v2 text marks, outline_label, image/gallery/cover; entrada futura/shape inválida falha fechada. | NOT RUN |
| Validação de payload | Fixture de texto/bytes, limite total/depth/nodes, cap 4 MiB; cliente UX e servidor autoritativo concordam; payload acima do limite nunca salva. | NOT RUN |
| Segurança de render | Caption/outline XSS como texto; sem HTML/style arbitrário; paths locais only; links/embed allowlisted e iframe sandboxed. | NOT RUN |
| Persistência Blog | CSRF, ACL access/modify, transação e snapshot; save válido; postback inválido preserva legado; sem perda de conteúdo/metadata. | NOT RUN |
| Media/blocks | Gallery filhos image; cover allowlist; picker mantém paths locais; remoção/duplicação preserva constraints; desktop/tablet/mobile. | NOT RUN |
| Editor e formulário | Undo/redo, nested outline, labels, aviso beforeunload, submit explícito e formulário multi-idioma; payload sincroniza no submit. | NOT RUN |
| Acessibilidade | Keyboard-only, leitor de tela, foco após popover/toolbar, zoom/reflow, reduced motion e estados de erro. | NOT RUN |
| Integração catálogo | Página publicada com v1/v2; injeção de preview de draft negada; responsive media e host policy. | NOT RUN |
| Regressão | CRUD post, CKEditor legado, cover image, SEO, permissões e traduções PT-BR/EN-US/ES-EU. | NOT RUN |
| DB gate | F9-12 confirmado pelo responsável; migration/schema e rollback conferidos no staging. Nenhum SQL destrutivo. | BLOCKED — F9-12 unknown |

## Evidências mínimas caso venha autorização

- Manifesto do ambiente: hostname classificado `staging`, commit SHA e confirmação de isolamento; sem tokens/segredos.
- Matriz preenchida como PASS/FAIL/NOT RUN por cenário e descrição do bug, sem payloads de conteúdo ou dados reais.
- Relatório de versões de browser/OS e configuração de idioma. Sem screenshots com dados identificáveis.
- Hash de arquivo do relatório, versão da fixture, versão da migration (se houver) e trilha de rollback.
- Decisão explícita de release (aprovado/reprovado); resultado não altera GitHub automaticamente.

## O que esta preparação executa

Apenas `node --check` nos scripts estáticos, parsing de JSON e um checker que confirma campos/estados e fixtures. Ele **não** abre conexão, executa validação PHP, consulta DB, inicia browser/app ou simula E2E.
