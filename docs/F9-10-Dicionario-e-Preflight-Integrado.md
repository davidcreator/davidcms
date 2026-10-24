# Fase 9 · Tarefa 10 — Dicionário de dados e preflight integrado do MVP

**Data:** 2026-10-24 · **Base verificada:** `davidcreator/davidcms`, `main`  
**HEAD inicial:** `2c39f444a78fbc01c8586871f780a09e9be4a91f`  
**Estado:** documentação e gates locais; sem conexão ou alteração de banco.

## Por que esta tarefa

A F9-02 declarou que as cinco tabelas deveriam retornar ao Dicionário de Dados quando o schema fosse efetivamente aprovado/aplicado. O operador confirmou agora que verificou/aplicou as tabelas ausentes após o erro do formulário F9-07. O ciclo F9 do MVP CMS/page tem schema, administração, valores, saída pública opt-in e navegação, mas o dicionário canônico ainda não registrava suas colunas.

## Entrega

1. Atualiza `docs/Dicionario-de-Dados.md` com as cinco tabelas F9-02, tipos, índices, semântica de locale/visibility, ownership e lembretes sobre migração sem FK.
2. Atualiza o gate histórico F9-05 para não exigir que controllers/templates futuros permaneçam ausentes. O teste continua validando o contrato daquela subtarefa, mas agora é forward-compatible ao rodar no repositório atual.
3. Adiciona `tools/e2e/e2e_f910.sh --check [--root /caminho/davidcms]`, que confere dicionário/schema e encadeia gates estáticos F9-02 e F9-05 a F9-09.

## O gate faz / não faz

**Faz:** verifica a forma publicada do schema, documentação e os contratos estáticos já entregues nas subtarefas; falha se um arquivo necessário estiver ausente.

**Não faz:** consulta se tabelas existem em algum banco, conecta ao DB, executa PHP, altera dados, aplica migration, abre browser ou valida deploy. A verificação realizada pelo usuário diretamente no banco segue sendo a evidência de existência real das tabelas.

## Validação local

O gate foi exercitado em uma árvore temporária mínima montada apenas com os arquivos pontuais das tarefas F9 — não houve clone/cópia integral do repositório. Resultado: **PASS** nos seis gates encadeados e no contrato integrado. PHP, banco, navegador e produção não foram executados pelo agente.

## Arquivos

- `docs/Dicionario-de-Dados.md` (atualizado)
- `tools/e2e/e2e_f905.cjs` (asserção temporal corrigida)
- `tools/e2e/e2e_f910.sh`
- `tools/e2e/e2e_f910.cjs`
- `docs/F9-10-Dicionario-e-Preflight-Integrado.md`
- `docs/README.md`
- `agents-ia/decisoes/0066-f9-10-dicionario-preflight-integrado.md`
- `manifest.json` e `sha256sum.txt`
