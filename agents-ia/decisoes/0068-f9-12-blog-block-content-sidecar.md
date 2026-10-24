# ADR 0068 — F9-12: sidecar versionado para conteúdo de blocos do Blog

**Data:** 2026-10-24 · **Agente:** install / blog editor.  
**Status:** migration preparada para revisão; DB/apply/conversão não autorizados.  
**Base verificada:** `davidcreator/davidcms` @ `d0f8706402a722d24ab76077974d765542999673`.

## Contexto

O Blog mantém descrição multilíngue em `blog_post_description.description MEDIUMTEXT`, tratada como HTML pelo admin e pelo catálogo. O contrato F9-11 escolheu editor em blocos, conversão ao editar e recuperação do HTML original. Sobrescrever a coluna existente com JSON quebraria o catálogo e perderia o fallback legado.

## Decisão

1. Criar sidecar `blog_post_content`, uma linha por `post_id + language_id`, com formato/versão JSON e fonte legada recuperável.
2. Deixar intocada `blog_post_description.description`. Ela continua como HTML legado e, nos salvamentos futuros de blocos, será snapshot HTML compatível gerado pelo renderer confiável.
3. Guardar `legacy_html_backup` somente como snapshot de primeira conversão, sem sobrescrever em edições seguintes; `source_hash` associa a cópia ao conteúdo inicial.
4. Não criar FK, seguindo a forma atual do schema Blog; aplicação garante integridade/cascata. Todas as datas são explicitamente mantidas.
5. Fazer migration aditiva `CREATE TABLE IF NOT EXISTS`, prefixo configurável pelo operador; nenhuma migração de linha ou ACL.
6. O rollback padrão é rollback de código para leitura HTML e **retenção** da tabela/conteúdo. Script não executa DROP; a remoção física futura depende de export/backup e autorização separada.
7. Não executar nem criar a migração base `schema.sql`; upgrade e rollback permanecem nomeados e versionados.

## Alternativas consideradas

- **Trocar `description` para JSON:** rejeitado; quebra leitor, excerpt, templates e histórico legado.
- **Guardar só JSON em novo campo e não gerar snapshot HTML:** rejeitado; dificulta compatibilidade gradativa do catálogo existente.
- **Edição com FKs para post/idioma:** rejeitado por consistência com schema Blog atual sem FKs e necessidade de rollback aditivo.
- **Rollback que dá DROP imediato:** rejeitado; pode apagar conteúdo estruturado e backup da conversão.
- **Converter todos os posts na migration:** rejeitado; F9-12 cria apenas estrutura; parsing e conversão terão fixtures e subtarefas próprias.

## Consequências

- O código legado continua compatível antes de implementar o editor.
- A nova estrutura pode ser deployada sem converter registros; só o editor futuro criará linhas sidecar.
- Rollback do código antigo preserva snapshots HTML; os documentos JSON permanecem disponíveis na sidecar até export/cleanup manual.
- `description` HTML já usa `|raw` no leitor público atual. Esta ADR não amplia nem declara seguro esse trust boundary; o renderer de blocos deve ser allowlisted em tarefa posterior.

## Validação

`tools/e2e/e2e_f912.sh --check` analisa migration/rollback/contract estaticamente. Nenhum banco, PHP, conteúdo ou ambiente foi acessado ou alterado.
