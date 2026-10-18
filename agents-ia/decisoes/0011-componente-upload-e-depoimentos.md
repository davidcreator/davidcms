# ADR 0011 — Componente de upload (regra dos três) + CRUD Depoimentos (F2 Tarefa 11)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Quarto CRUD no molde-ouro. Todos os módulos restantes com
  mídia no schema têm coluna de imagem; a chegada do TERCEIRO módulo pedindo
  arquivo (`marketing/testimonial`, depois de `catalog/project` e
  `catalog/category`) disparou a "regra dos três" — restrição explícita do
  mantenedor: o componente de upload só se extrai quando o 3º módulo pedir.

## Decisões

1. **`system/library/media_upload.php`** — `Reamur\System\Library\MediaUpload`
   (autoload por convenção snake_case do Autoloader, zero registro).
   API: `store($_FILES entry, $targetDir): {ok,path,error}`,
   `guard($path,$targetDir)`, `absolute($relative)`, `delete($relative)`.
   Consts `MIME` (jpeg/png/webp/gif) e `MAX` (2 MB) migram para a library.
   **O componente não sabe de HTTP nem idioma**: devolve códigos simbólicos
   (`no_file|size|type|move`) e cada controller traduz para as suas
   mensagens — saída byte-idêntica dos endpoints preservada.
2. **Refatoração comportamentalmente invisível** dos `.upload` de
   `catalog/project` e `catalog/category`: rotas, JSON `{success,path,error}`,
   ACL `modify`, mensagens por módulo — tudo idêntico; internamente delegam
   (inclui `imageGuard`/`imagePath`/deleção física −31 linhas em project,
   −32 em category). Prova: baterias f28/f29 seguem 17/17 sem qualquer
   edição, mais checks dedicados de regressão na f211 (upload real + fake
   rejeitado em ambos).
3. **Rota `marketing/testimonial`** — primeiro módulo do grupo `marketing/`
   (Dicionário-de-Dados: testimonial+client_logo → marketing/, consumidor
   "home"). Sem SEO/keyword: depoimento não é página própria.
4. **Multi-idioma**: `author` VARCHAR(128) — **autor en-US obrigatório**;
   `company` VARCHAR(128), `text` TEXT. **Sem `date_modified`** — schema só
   prevê `date_added`; grade mostra "Added".
5. **Foto via componente**: `marketing/testimonial.upload` (JSON, mesmas
   mensagens), `guard` no payload/validação, deleção física best-effort no
   delete (troca de foto NÃO remove a antiga — housekeeping futuro, mesmo
   comportamento legado de project/category).
6. **Seed/migração padrão**: seedRoutes do wizard += `marketing/testimonial`;
   `install/sql/upgrade_f2_11_marketing_testimonial_grant.sql` idempotente
   (JSON_SEARCH — nunca JSON_CONTAINS no MariaDB 11.8.6).
7. **Correção retroativa de traduções (38 keys)**: o gerador inline de pt/es
   dos F2-8..F2-10 substituía só a 1ª ocorrência de valores repetidos —
   keys `entry_*`/`column_*` gêmeas seguiam em inglês em project/category/
   page. Fixador por léxico explícito + auditoria automatizada ("valor ==
   inglês fora da whitelist") agora limpa; scripts de idioma futuros devem
   REPLACE-ALL + auditoria (lição registrada). Arquivos corrigidos vão no
   mesmo pacote e precisam ser re-aplicados sobre os zips antigos.
8. **Erro corrigido a quente na esteira**: model escrito inicialmente com
   `get_object_vars()` quebrou no wrapper stdClass (error.log + bateria
   apontaram); reescrito no vocabulário exato do model `cms/page`
   (`$query instanceof \stdClass`, `(array)$query->rows`, helper `t()`,
   currentLanguageId com `language_directory` + cache estático). Regra
   reforçada: models novos nascem CLONANDO um model-de-ouro existente.

## Deltas não-objetivos

- Thumb redimensionada: a grade serve a imagem original via URL do catalog
  (mesma decisão do F2-8); redimensionamento fica para o trabalho de tema.
- Orfãos de upload (arquivo trocado que ninguém referencia) — varredura
  futura de housekeeping.
- Nenhum módulo `marketing/client_logo` ainda — candidato F2-12, reutilizando
  diretamente o MediaUpload já extraído.

## Validação

- `/tmp/e2e_f211.sh` — **14 checks, 0 falha**, do zero (layout XAMPP):
  seed-grant no install · login · lista vazia · card dashboard · form
  3 idiomas com upload e **sem keyword** · validação autor en-US ·
  upload real via componente (path do módulo + PNG fake rejeitado + GET
  → JSON "must be a POST") · create multilíngue com foto persistida ·
  grade com thumb + flash · edição repovoa + swap de foto + toggle status ·
  delete GET-sem-mutar + POST com cascata **e remoção do arquivo físico** ·
  pt-BR/es-EU · ACL deny → migração ×2 (idempotente) → 200 ·
  **regressão do componente em project.upload e category.upload**
  (real ok + fake rejeitado).
- Regressões no mesmo sandbox: **f27 22/22 · f28 17/17 · f29 17/17 ·
  f210 13/13** (total 83 asserts verdes).
- PHPStan level-5 limpo; `php -l` em todos os arquivos; clone+diff contra
  `@06e31fc` antes de escrever (detectados 2 arquivos-órfãos legados no
  remoto — `step_5.twig`/`wizard.css`, sem referência ativa, não tocados).

## Entrega

- `reamurcms-fase2-tarefa11.zip` — novos arquivos do módulo + componente +
  refatoração dos controllers catalog + dashboard/seed/migração + as 6
  lang-files corrigidas (§7).
