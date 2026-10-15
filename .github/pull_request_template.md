<!--
Template de PR do ReamurCMS — espelha o DoD dos agentes (agents-ia/regras/03 §6).
Apague as seções que não se aplicam. Commits em pt-BR, Conventional Commits.
-->

## 📌 O que este PR faz

<!-- 1–3 linhas objetivas. Ex.: feat(catalog): exibir galeria na página de projeto -->

## 🎯 Escopo

- Aplicação(ões) tocada(s): `catalog` · `admin` · `install` · `system` · `docs` · `agents-ia`
- Arquivos renomeados/movidos? <!-- se sim, justificar -->
- Toca o núcleo compartilhado (`dev/system/`)? ☐ Não ☐ Sim → **plano de impacto declarado abaixo**

## 📋 Plano / decisões

<!-- Se tocou núcleo ou criou pasta/rota/entidade nova: cole o plano aprovado e/ou link do ADR -->
- ADR relacionado: <!-- ex.: agents-ia/decisoes/0004-...md -->
- Novas tabelas/colunas conforme `docs/Dicionario-de-Dados.md`? ☐ N/A ☐ Sim ☐ **Diverge → ADR obrigatório**

## ✅ Checklist (Definition of Done)

- [ ] Li todos os arquivos antes de modificá-los
- [ ] Cada classe/método que chamo **existe** em arquivo lido ou criado neste PR
- [ ] Pastas/rotas/arquivos seguem `docs/Estrutura-de-Pastas.md` (ou aprovados em ADR)
- [ ] Nenhum texto de interface fora da camada **Language** (`en-us` completo — ADR 0001)
- [ ] Nenhum SQL fora de Model · colunas conforme Dicionário de Dados
- [ ] `php -l` passou em todos os arquivos PHP
- [ ] CSRF/permissão verificados em ações do admin
- [ ] Templates: escape intacto · `json_encode|raw` só para dados → JS
- [ ] 3D (se houver): fallback + lazy-init + `prefers-reduced-motion` + budgets
- [ ] Docs impactados atualizados no **mesmo** commit
- [ ] Incertezas declaradas (ou "nenhuma")

## 🧪 Como testar

<!-- passos manuais ou automação -->

## ⚠️ Incertezas / pendências

<!-- modelo em agents-ia/regras/03 §5 — escrever "Nenhuma" se não houver -->
