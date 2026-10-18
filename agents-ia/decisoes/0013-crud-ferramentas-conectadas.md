# ADR 0013 — CRUD de Ferramentas Conectadas (`tool/tool`, F2 Tarefa 13)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Sexto CRUD no molde-ouro (project → category → page →
  testimonial → client_logo → tool). Fecha o grupo de conteúdo "site
  institucional" do Dicionário-de-Dados. O grupo `tool/` já existia no repo
  como esqueleto de guards — o módulo apenas o completou. Zero DDL novo
  (6º módulo consecutivo — o DDL-first da Fase 1 se provou exaustivamente).

## Decisões

1. **Rota `tool/tool`** — gramática idêntica (.add/.edit|N/.delete|N POST/
   .upload POST→JSON, ACL base-route).
2. **Multi-idioma**: `name` VARCHAR(128) — **nome en-US obrigatório**;
   `description` VARCHAR(255) curta. **Sem SEO/keyword**: ferramenta é item
   da página catalog `tool/`, não página própria (mesma decisão do
   testimonial — apenas módulos-página têm keyword: project, category e page).
3. **`vendor` VARCHAR(64)** e `url` com a validação `http(s)://` herdada do
   F2-12 (agora padrão-ouro de URL do admin).
4. **Ícone via MediaUpload** — 4º consumidor do componente (project,
   category, testimonial, tool, client_logo: 5 módulos no total);
   guard/deleção física idênticos.
5. **Template do esqueleto**: os guards pré-existentes (controller/tool,
   model/tool, language/en-us/tool index.html) foram mantidos; criados os
   de view/template/tool, language pt/es tool e catalog/view/image/tool.
6. **Seed/migração padrão**: seedRoutes do wizard += `tool/tool` (8 rotas
   nativas); `upgrade_f2_13_tool_grant.sql` idempotente.
7. **Sandbox rebuild agora é script**: `dev/tests/e2e/setup_sandbox.sh`
   (idempotente: apt → mariadb user → /tmp/fui sync → composer → Apache
   :8090 bind-mount). Sandbox resetou 2× nesta sessão; o rebuild passou de
   "receita de memória" para ferramenta comitável.
8. **Lição de harness**: `json_encode` escape de URL (`tool\/tool.upload`)
   — assertions de endpoint em HTML/JS devem grep-pelo-sufixo (`tool.upload`)
   ou prever o `\/`.

## Deltas não-objetivos

- Ícone pode ficar vazio (grade mostra "(no icon)") — mesmo comportamento
  editorial de logo/foto; a listagem pública decide exibir.
- Troca de ícone não remove o antigo (housekeeping futuro, legado).
- `description` é VARCHAR(255) por schema — input de texto simples, sem
  textarea (o campo já nasce com maxlength=255 no form).

## Validação

- `dev/tests/e2e/e2e_f213.sh` — **14 checks, 0 falha**: seed-grant · login ·
  lista vazia · card dashboard · form 3 idiomas (name/description) com
  vendor/url/upload e **sem keyword** · validação nome · validação URL
  (ftp rejeitado) · upload ícone via componente (+fake, +GET→JSON) ·
  create multilíngue com 3 descrições persistidas · lista com thumb,
  vendor e flash · edição repovoa + swap ícone + toggle status (vendor
  "esvaziável") · delete GET-sem-mutar + POST cascata + arquivo físico ·
  pt-BR/es-EU · ACL deny → migração ×2 → 200.
- Regressão + f212 (mesmas rodadas): **10 + 14** — total **38 asserts verdes**
  (regressão agora valida 8 grants de seed e 6 cards de dashboard).
- PHPStan level-5 limpo na base inteira; clone+diff contra `@de0063d` antes
  de escrever (repo ≡ espelho; nota de higiene avisada ao mantenedor: o
  commit @7335cae versionou acidentalmente uma linha de
  `system/storage/logs/error.log` — sugerido gitignore/revert fora do escopo).

## Entrega

- `reamurcms-fase2-tarefa13.zip` — módulo completo + dashboard/seed/
  migração + guards + baterias (`e2e_f213.sh` novo, `e2e_regressao.sh`
  atualizada, `setup_sandbox.sh` novo) em `dev/tests/e2e/`.
