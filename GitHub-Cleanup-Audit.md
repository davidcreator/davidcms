# Auditoria estática de limpeza do GitHub

**HEAD consultado:** `478fd8af2ae01d3b36dbfe845c69d6eb112e6762` — `feat(cms/pages): add private preview for unsaved pages`  
**Data:** 2026-10-28  
**Escopo:** inventário completo de paths da árvore Git recursiva (`3125` entradas, sem truncamento), inspeção seletiva de arquivos de raiz/documentação/ferramentas e checagem de referências e aplicabilidade reversa de patches. Sem clone integral e sem apagar/alterar arquivos no GitHub.

## Resumo

Há um conjunto claro de artefatos transitórios na raiz que já foram aplicados ou ficaram obsoletos: patches de tarefas concluídas, uma cópia `implementation/` byte-idêntica à fonte `dev/`, snapshots de hashes/manifests de tarefas antigas, um README de handoff no lugar de um README de projeto e logs runtime versionados.

**Nada foi removido.** A lista abaixo separa remoções de alta confiança das que exigem decisão para não quebrar testes, dependências ou rastreabilidade.

## Remoção de alta confiança — após autorização de limpeza

### Patches já integrados

Os seguintes patches não são dependências do runtime e as alterações correspondentes estão nos arquivos de produto. A checagem `git apply --reverse --check` passou exatamente para os patches marcados “exato”; para patches tocados por commits posteriores, passou ao excluir somente os arquivos que receberam mudanças posteriores.

| Arquivo na raiz | Evidência | Sugestão |
|---|---|---|
| `CMS-Pages-Safe-Draft-Preview.patch` | Reverse-check passou integralmente no HEAD atual; commit `478fd8a` adiciona patch e código. | Remover da raiz após manter o patch no histórico Git. |
| `Pages-Editor-Next.patch` | Reverse-check passou integralmente; funcionalidade já está no código. | Remover. |
| `F10-Pages-Routes.patch` | Reverse-check passou integralmente; alterações estão no código atual. | Remover. |
| `admin-css-fix.patch` | Reverse-check passou integralmente; alterações estão no código atual. | Remover. |
| `CMS-Pages-Image-Library.patch` | Conteúdo foi integrado no commit `c3efce5`; reverse-check passa ao excluir o controller, idiomas e template, posteriormente alterados pela prévia. | Remover após confirmar a cópia integrada em `dev/` (o usuário já confirmou a feature concluída). |
| `F10-03.patch` | Reverse-check passa ao excluir apenas `docs/Estrutura-de-Pastas.md`, `docs/Guia-CMS.md` e `dev/admin/controller/design/theme.php`, alterados depois; demais hunks estão integrados. | Remover depois de preservar/atualizar o registro da tarefa F10-03. |

### Cópia de implementação redundante

- **`implementation/`**: 12 arquivos, aproximadamente 334 KB. Os 12 hashes Git são idênticos aos respectivos arquivos `dev/...`; não encontrei consumidor de produto/teste para essa cópia. O único uso encontrado é o README de handoff na raiz.
- Sugestão: remover `implementation/` inteira e deixar `dev/` como fonte única.

### Artefatos antigos de tarefa

- `README.txt`: instrução antiga para aplicar `Pages-Editor-Next.patch` sobre o HEAD `3e79ba0`; patch já integrado. Removível.
- `DELETE_FILES.txt`: todas as 10 rotas/arquivos listados já estão ausentes da árvore atual. Removível; a migração do runtime Three.js para System/Catalog já está refletida no código.
- `manifest.json`: manifesto de entrega da tarefa F9-11 com `base_head` antigo, não manifesto operacional do CMS; não encontrei consumidor.
- `remote_hashes.json`: snapshot de F9-19 apontando para HEAD `60ee6cc`, anterior ao estado atual; não encontrei consumidor.
- `SHA256SUMS` da raiz: parece artefato de pacote antigo e não é autoconsistente no checkout atual — inclui `MANIFEST.txt`, que não existe no HEAD. Recomendo remover ou arquivar junto do pacote a que pertencia, não mantê-lo como checksum da árvore atual.

### Logs de runtime versionados

- `dev/system/storage/logs/error.log`
- `dev/system/storage/logs/migration_error.log`

São arquivos de log versionados; o `.gitignore` já ignora `dev/system/storage/logs/*` e preserva `index.html`. Sugestão: remover os logs do Git e manter apenas os arquivos de guarda. **O conteúdo dos logs não foi lido.**

## Candidatos condicionais — decisão antes de remover

- **`node_modules/`**: 182 arquivos e ~18,7 MB, Playwright/playwright-core versionados; não há `package.json` nem `package-lock.json` na raiz, e `.gitignore` não ignora `node_modules`. Alguns runners E2E requerem Playwright e indicam instalação local sob demanda. Sugestão: retirar o vendor versionado e adicionar `/node_modules/` ao `.gitignore`, mas antes documentar uma instalação reproduzível (ou criar manifesto/lock se esse for o padrão aceito).
- **`apply-patch.sh`**: não é consumido pelo CMS. A única instrução de uso encontrada está em `README.txt`, que é obsoleto. Pode ser removido junto dos patches/README de handoff se a raiz deixar de ser um pacote de aplicação de patches; manter se esse ainda for o fluxo manual desejado.
- **`README-F10-03.md` e `README-F10-Pages-Routes.md`**: são notas de tarefa no nível raiz. Melhor mover para `docs/`/arquivo histórico ou consolidar no ADR/relatório correspondente; não descartar sem preservar decisões e status.
- **`dev/admin/view/js/jquery/flot/build.log`**: arquivo isolado com cara de log de build dentro de biblioteca vendorizada. Candidato após conferir que o pacote Flot não o documenta nem o utiliza.
- **`CHANGELOG.md` da raiz**: está parado no scaffold/versão 1.0.0, enquanto `docs/CHANGELOG.md` é o histórico ativo bem mais recente. Não apagar um deles às cegas: escolher um canônico e consolidar/ligar o outro.
- **`README.md` da raiz**: não remover. Está atualmente substituído por um README de pacote da prévia CMS, referencia patch local já aplicado e baseia a nota no HEAD anterior (`c3efce5`). Recomendo restaurar/criar um README de projeto; mover o conteúdo de entrega para `docs/` se ainda for útil.

## Preservar por enquanto

- **`preview/`** (3 arquivos, ~160 KB): demonstração anterior ao produto, mas é referenciada por `tools/e2e/check_preview_import.cjs` e `docs/BDREditor-Modern-Interface.md`. Só remover junto do checker e da documentação que a declara como demo.
- **`reference/`** (7 arquivos, ~85 KB): snapshots seletivos; alguns são usados por `tools/e2e/e2e_f919_static.cjs` e `docs/F9-20-Verificacao-Estatica-Remediacao.md`. Não remover sem reescrever o teste e guardar sua base.
- **`tools/preview/`**: os previews F9-14/F9-15 são explicitamente referenciados nos contratos e checkers correspondentes.
- **`tests/`, `tools/e2e/`, `dev/tests/`**: checkers e fixtures não são lixo só por já terem sido executados; vários permanecem documentados como contrato/validação. Não propus remoção em lote.
- **`docs/` e `agents-ia/decisoes/`**: contêm decisões, contratos, auditorias e evidência histórica. Recomendo reorganizar arquivos antigos por fase, não apagá-los em lote.
- **Migrations/SQL em `dev/install/sql/`**: são fontes de instalação/upgrade e não podem ser apagadas só porque já foram aplicadas a um banco.

## Problemas adjacentes encontrados

1. `CONTRIBUTING.md` aponta para `docs/README.md`, mas esse path não existe na árvore atual. Sugestão: restaurar um índice ou corrigir o link.
2. O README da raiz diz que o patch de prévia é local e não publicado, mas o patch e o produto estão no HEAD `478fd8a`. A nota está obsoleta e conflita com o propósito de um README de projeto.
3. Há duplicação de changelog na raiz e em `docs/`; deve haver uma fonte canônica.

## Sequência de limpeza sugerida

1. Restaurar um README de projeto e remover/mover o README de handoff (`README.txt` e conteúdo de pacote hoje em `README.md`).
2. Remover patches concluídos, `implementation/`, `DELETE_FILES.txt`, snapshots `manifest.json`/`remote_hashes.json` e logs versionados.
3. Decidir política para dependências de testes (`node_modules`) e para `apply-patch.sh`.
4. Arquivar notas F10 e consolidar changelog/índice de docs, atualizando links.
5. Só então avaliar protótipos e snapshots `preview/`/`reference/` junto com seus consumidores.

## Limites

Auditoria estática baseada no HEAD `478fd8a`; árvore Git completa via API, sem clone, execução do CMS, browser, banco, logs ou mutações. Nenhum arquivo remoto ou local do produto foi apagado/alterado durante a auditoria.
