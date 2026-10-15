# Auditoria de Fundação — docs/ + agents-ia

> **Pergunta respondida:** a documentação e os agentes criados são suficientes para construir o ReamurCMS de forma sólida, sem problemas de desenvolvimento — ou ainda faltam amarrações?

| | |
| --- | --- |
| **Data** | 15/10/2026 |
| **Escopo verificado** | [github.com/davidcreator/davidcms](https://github.com/davidcreator/davidcms) (branch `main`, até commit `6213122`) |
| **Veredito** | 🟡 **Fundação sólida (~80%), com 4 lacunas críticas a fechar ANTES de avançar** |

---

## 1. O que foi verificado no repositório

- ✅ `README.md`, `docs/` (11 documentos) e `agents-ia/` (11 arquivos) publicados na `main` conforme o pacote entregue;
- ✅ Branches `main` / `staging` / `feature/system` e tag `v1.0.0` mantidos;
- ⚠️ Evidência de **desenvolvimento já em curso**: merge de `hotfix/login` em 15/10 — ou seja, código novo está entrando **agora** e ainda não existem contratos de bootstrap/schema;
- ⚠️ Nenhum commit após `0d47ad8` tocou `.github/workflows/php.yml` → **CI continua quebrado** (valida `composer.json` na raiz, onde ele não existe);
- ⚠️ `.gitignore` continua sem cobrir `dev/system/storage/vendor/`.

## 2. O que já está sólido ✅

| Área | Avaliação |
| --- | --- |
| Arquitetura e convenções | Documentadas de forma verificável (MVCL, nomes, Registry, ciclo de vida) |
| Estrutura de pastas | Mapa anotado completo + guia "onde criar o quê" |
| Agentes por aplicação | Escopos com "pode/nunca", DoD próprios, orquestrador com ADR |
| Anti-alucinação | Hierarquia da verdade + protocolo por tarefa + checklist |
| Padrões de código | PHP 8, SQL, Twig, JS, segurança, commits |
| Plano de projeto | Análise do site, inventário, fases, riscos, métricas |

Isso coloca o projeto **muito à frente** de repositórios típicos nesta fase. Porém, há 4 lacunas que causam divergência **mesmo com agentes disciplinados**.

## 3. Lacunas críticas 🔴 (causam divergência real no código)

### L1 — Sem contrato de bootstrap (pontos de entrada)
Hoje **nenhum `index.php` existe** e nenhum documento define exatamente: quais constantes cada aplicação define (`APPLICATION`, `DIR_*`), em que ordem `startup.php`/`framework.php` são carregados e o que cada entry point retorna. Resultado provável: **4 agentes escreverão 4 front controllers diferentes** (o `hotfix/login` talvez já seja o primeiro caso).
**Amarração:** `docs/Contrato-Bootstrap.md` (especificação) + modelo de `index.php` em `agents-ia/modelos/`.

### L2 — Sem dicionário de dados / schema canônico
As regras dizem "não invente colunas", mas **não existe schema de referência** para as entidades núcleo (user, customer, conteúdo/página, projeto, categoria, order_, webhook, setting, seo_url, language). O `agente-admin` e o `agente-catalog` vão necessariamente assumir colunas diferentes para o mesmo dado (ex.: `name` × `title`, `image` × `cover`).
**Amarração:** `docs/Dicionario-de-Dados.md` (tabelas, colunas, tipos, índices, relações) — alimenta `agente-install` e vira fonte da verdade consultada por todos.

### L3 — Sem modelos de arquivo (templates prontos para copiar)
O melhor antídoto contra deriva entre sessões de IA não é a regra escrita — é o **esqueleto pronto**. Hoje cada CRUD/cena/front controller é gerado "de memória da regra".
**Amarração:** pasta `agents-ia/modelos/` com: front-controller, controller CRUD admin, model, arquivo de language, templates `_list.twig`/`_form.twig`, cena Three.js. Agentes passam a **copiar e preencher**, não inventar formato.

### L4 — Pendências conhecidas ainda *indecisas* (não apenas pendentes)
`en-gb` × `en-us/pt-br`, CI quebrado, `.gitignore`, blog interno × externo estão registrados como "não corrija sem tarefa". Indecisão registrada vira **decisão improvisada por sessão**.
**Amarração:** 4 ADRs rápidos em `agents-ia/decisoes/` (o humano decide em minutos; os agentes executam a decisão única).

## 4. Lacunas importantes 🟡 (evitam regressão/retrabalho)

| # | Lacuna | Risco | Amarração |
| --- | --- | --- | --- |
| L5 | CI só valida composer.json (e quebrado) | Erros de sintaxe PHP passam direto | Corrigir `working-directory` + job `php -l` em todo `dev/**` + link-check dos docs |
| L6 | Sem análise estática/testes | "Chamar método inexistente" (a alucinação clássica) não é pega automaticamente | `require-dev`: **PHPStan** (nível 0–2 sobre `dev/system` já pega chamada inválida) e PHPUnit — ativa na Fase 1 |
| L7 | Sem `.editorconfig` | tabs×espaços entre sessões → diff noise (o núcleo hoje mistura os dois) | `.editorconfig`: 4 espaços em arquivos novos |
| L8 | Sem `PR template`/`CONTRIBUTING`/`SECURITY`/`CHANGELOG` | DoD dos agentes não espelha no GitHub (repo público, GPL) | `.github/pull_request_template.md` com o DoD + 3 arquivos padrão |
| L9 | Protocolo não cobre "IA sem acesso a arquivos" | Em chat sem leitura do repo, o agente tende a *assumir* o conteúdo | Patch na regra 03: sem acesso ao arquivo, **exigir que o humano cole o conteúdo** antes de propor mudança |
| L10 | Quirks não explicitados | Agente "normalizar" `localisation`×`localization` ou o vendor commitado | Seção "Peculiaridades (não corrigir)" no `00-contexto-global.md` |

## 5. Quirks do repositório (registrar como intencionais)

- `catalog/controller/localisation/` (sem "z") × `admin/controller/localization/` (com "z") — herança do OpenCart: **manter como está**;
- `config/default.php` com `en-gb` enquanto as pastas são `en-us/es-eu/pt-br` — corrigir **só** via ADR da L4;
- `dev/system/storage/vendor/` commitado — aceito até a limpeza planejada (L4/CI);
- `hotfix/login` já mergeado — revisar se seguiu MVCL e incorporar eventuais desvios ao checklist.

## 6. Plano de amarração (ordem de execução sugerida)

| Ordem | Entrega | Dependência |
| --- | --- | --- |
| 1 | **ADR 0001** idioma padrão (`pt-br`) + alinhar `default.php` | decisão humana (5 min) |
| 2 | **ADR 0002** blog interno (`cms/`) × manter davidcreator.com | decisão humana (5 min) |
| 3 | **ADR 0003** correção CI + `.gitignore` (e limpeza futura do vendor) | — |
| 4 | `docs/Contrato-Bootstrap.md` + modelo `index.php` | — |
| 5 | `docs/Dicionario-de-Dados.md` (entidades núcleo) | alinhar com Fase 2 |
| 6 | `agents-ia/modelos/` (6 esqueletos) + patch regra 03 (L9) + quirks (L10) | — |
| 7 | CI reforçado (php -l + links) + `.editorconfig` + PR template | ADR 0003 |
| 8 | PHPStan/PHPUnit em `require-dev` (ativar na Fase 1) | — |

## 7. Conclusão

**Os agentes e a documentação sustentam a disciplina do processo** — nenhum desvio de *comportamento* é esperado. O que eles **não** conseguem sustentar sozinhos é a **coerência estrutural** do que está prestes a ser escrito: entry points, schema de banco e formato dos arquivos. São lacunas de *contrato*, não de *disciplina*.

> ✅ **Resposta direta:** sim, precisa amarrar — **4 itens críticos (L1–L4)** antes da próxima feature, e **6 importantes (L5–L10)** até o fim da Fase 1. Com isso fechado, o risco de problemas estruturais cai para o nível "baixo e administrável".

---

<p align="center">Auditoria registrada em Outubro/2026 · ReamurCMS</p>
