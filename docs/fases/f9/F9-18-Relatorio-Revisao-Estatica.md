# Fase 9 · Tarefa 18 — Revisão estática e prontidão para release

**Data:** 2026-10-24  
**Repositório:** `https://github.com/davidcreator/davidcms` · branch `main`  
**HEAD lido antes desta tarefa:** `72a8b927c0d2b96b7d3df41114969aa620bb9602` (`feat(e2e): add F9-17 blog blocks E2E prep tooling`)  
**Escopo:** revisão estática das entregas F9-12–F9-17 e checklist de prontidão. Sem alteração de código do produto.

Cinco arquivos críticos de catálogo/admin/biblioteca foram comparados byte a byte com `raw` no HEAD acima (somente leitura); todos coincidiram com os arquivos pontuais do workspace. A lista e SHA-256 está em `docs/F9-18-Source-Hashes.json`.

## Veredito

**Não liberar ainda o fluxo de blocos do Blog.** A revisão encontrou dois riscos bloqueadores a resolver antes de um E2E aprovado, mais duas questões de consistência/atomicidade. As conclusões abaixo são análise de fonte; nenhuma é resultado de execução funcional.

### F918-01 — P1: falha ao consultar metadata pode reabrir o fallback legado público

`dev/catalog/model/blog/blog.php:110–124` transforma qualquer exceção na consulta a `information_schema` em `$cache = false`. Nesse caso, `getPostBlockContent()` devolve `table_available=false`; o controller usa `content_mode=legacy` (`dev/catalog/controller/blog/blog.php:86–100`) e o template mantém `description|raw` para esse ramo. Portanto, quando o sidecar existe mas a detecção falha (permissão/indisponibilidade de metadata), o sistema pode mostrar HTML legado sem passar pelo renderer allowlist. Isso diverge do fail-closed descrito em F9-15.

**Correção recomendada:** diferenciar “consulta bem-sucedida, tabela ausente” de “erro ao detectar”. Só a primeira condição pode usar fallback legado. Erro de detecção deve propagar erro ou retornar estado explícito de consulta inválida para o controller bloquear o conteúdo. Adicionar caso de regressão para erro de metadata.

### F918-02 — P1: caminho de imagem da Biblioteca é emitido como URL relativa à página

O validador agora aceita `view/image/blog/...` (`dev/system/library/blog/block_document.php:245–252`). O renderer emite o valor de `src` sem resolver base (`dev/system/library/blog/block_renderer.php:62–66`). Em URLs públicas aninhadas/rewrite, o navegador pode resolver `view/image/blog/...` em relação ao caminho da página e requisitar uma URL inexistente. O caminho vindo da Biblioteca precisa continuar local/allowlisted, mas ser resolvido a partir da base pública do catálogo (incluindo instalação em subdiretório).

**Correção recomendada:** passar uma base de assets confiável ao renderer/controller e resolver o path local com essa base, sem aceitar host/esquema fornecido pelo documento. Incluir casos para página raiz, URL SEO aninhada e instalação em subdiretório.

### F918-03 — P2: seleção do idioma público depende de HTML não vazio, não da linha sidecar

`dev/catalog/model/blog/blog.php:68–79` escolhe `content_language_id` com base em `description` não vazia. O controller usa esse idioma para consultar sidecar (`dev/catalog/controller/blog/blog.php:86`). Um documento de blocos válido e intencionalmente vazio pode ter snapshot HTML vazio; nesse caso, o conteúdo do idioma ativo pode ser trocado pelo documento inglês mesmo que exista sidecar do idioma ativo.

**Correção recomendada:** definir explicitamente o fallback de idioma para blocos, priorizando existência de documento sidecar no idioma ativo e só recorrendo ao inglês quando não houver documento. Manter separado o fallback de título/SEO/descrição legado.

### F918-04 — P2: exclusão do post e do sidecar não é atômica

`dev/admin/model/blog/blog.php:300–309` apaga sidecar, post, descrições e SEO em consultas sucessivas sem transação. Como F9-12 não cria FK/cascade, uma falha posterior pode deixar exclusão parcial ou órfãos.

**Correção recomendada:** envolver todas as exclusões relacionadas numa transação InnoDB, com rollback em erro; cobrir a falha entre remoção do sidecar e do post em ambiente descartável autorizado.

## Cobertura e limites da validação

- A auditoria estática F9-17 verifica marcadores de fonte/contrato, idiomas e fixtures; não executa PHP, SQL, Twig, navegador, app nem banco, e não prova a semântica das queries.
- Os cenários F9-17 planejam transação, ACL, idioma e mídia, mas ainda não foram executados em ambiente E2E.
- A migration F9-12 permanece **não presumida e não aplicada por esta revisão**. Não há confirmação de schema em ambiente algum.
- O teste de E2E/ambiente continua bloqueado pelos limites ativos até autorização explícita e ambiente descartável.

## Checklist de prontidão — antes de integração operacional

1. Corrigir F918-01 e F918-02; decidir e documentar o comportamento de F918-03.
2. Tornar a exclusão transacional (F918-04) ou documentar formalmente por que a consistência parcial é aceitável — recomendação: não aceitar parcial.
3. Acrescentar fixtures/guardrails para: erro de metadata com sidecar presente; URL local resolvida em rota aninhada/subdiretório; sidecar vazio no idioma ativo com EN preenchido; rollback de exclusão.
4. Rever o delta contra o HEAD atual antes de qualquer implementação; não aplicar patch remoto nesta tarefa.
5. Só com autorização explícita: criar ambiente de teste isolado, backup, confirmar versão/prefixo, aplicar migration F9-12 manualmente pelo responsável e executar a matriz E2E sintética sem dados reais.
6. Rejeitar release se houver qualquer fallback para `description|raw` após erro de schema/query sidecar.

## Ações não executadas

Nenhuma migration, query ou conexão de banco foi executada. Não foram executados testes PHP, SQL, browser ou app, nem deploy. Nenhuma escrita no GitHub. Não foram usados dados/dumps/logs runtime.
