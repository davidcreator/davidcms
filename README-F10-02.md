# Fase 10 — Tarefa 02: Temas (incremento local)

Base remota consultada: `2e54f831f54b76fa47fc0870b8240f0d0f7c1dab` (confirmada pelo usuário como base atual).

## Incluído
- Ferramenta Admin separada em `design/theme`, ACL `access`/`modify`, menu próprio.
- Edição persistente de nome/chave, cores e cena declarativa allowlisted; sem editor JavaScript.
- Persistência de tema e snapshots auditáveis; publicação explícita via POST/CSRF.
- Migration `dev/install/sql/upgrade_f10_02_design_themes.sql`; schema inicial e schema-complete atualizados localmente.
- ADR 0079 e documentação de estrutura/CMS.

## Pendências impeditivas do aceite final da F10-02
- Editor avançado Twig restrito ao diretório de tema, preview sandboxado e rollback pela interface ainda não implementados.
- O tema publicado não está ligado ao loader/runtime do Catalog: `status` e tokens são persistidos, mas não mudam ainda a aparência pública.
- Verificar compatibilidade dos métodos de sessão/ACL e revisar segurança com runtime; não foi possível executar PHP CLI neste ambiente.

## Segurança / limites da execução
- Nenhuma migration/SQL executada. Sem push, staging ou deploy.
- Não foi acessado localhost nem estado de sessão.
- Alterações apenas locais; esta é uma entrega parcial, não declarar a tarefa pronta para produção.
