# 🤖 agents-ia — Agentes de IA do ReamurCMS

> **Missão desta pasta:** definir os **agentes de IA** responsáveis por desenvolver e manter o ReamurCMS — cada um especializado em uma aplicação (`system`, `install`, `admin`, `catalog`) — garantindo que **qualquer IA** que trabalhe no projeto **entenda o sistema inteiro, siga rigorosamente a arquitetura MVCL e as convenções do núcleo `Reamur\*`, sem alucinar nem se perder.**

---

## Por que esta pasta existe

O ReamurCMS é um monólito multi-aplicação com convenções próprias (nomes de classe, rotas, Registry, languages, pastas). Uma IA sem contexto tende a:

- ❌ Inventar pastas, rotas e métodos que não existem;
- ❌ Usar padrões de outro framework (Laravel/Symfony) em vez do MVCL do projeto;
- ❌ Colocar SQL em controller, texto solto em template, lógica na View;
- ❌ Quebrar uma aplicação ao mexer no núcleo compartilhado.

Cada arquivo em [`agentes/`](agentes/) é um **system prompt completo e autossuficiente** que transforma uma IA genérica em um membro disciplinado da equipe — leia as regras em [`regras/`](regras/), respeitar o checklist dele e declarar incerteza em vez de supor.

---

## Estrutura

```text
agents-ia/
├── README.md                       # Este arquivo
├── regras/                         # 📜 CONSTITUIÇÃO — leitura obrigatória de TODOS os agentes
│   ├── 00-contexto-global.md       # O que é o ReamurCMS: fatos do sistema (fonte da verdade)
│   ├── 01-arquitetura-e-convencoes.md  # Regras MVCL, nomes, pastas — o que pode e o que não pode
│   ├── 02-padroes-de-codigo.md     # PHP 8, Twig, JS, segurança, Git/commits
│   └── 03-protocolo-anti-alucinacao.md # Protocolo contra alucinação + checklist obrigatório
├── agentes/                        # 🤖 Os agentes (system prompts prontos para uso)
│   ├── agente-orquestrador.md      # Direciona tarefas, resolve conflitos, guarda as regras
│   ├── agente-system.md            # Núcleo: engine, library, helper, config, framework
│   ├── agente-install.md           # Instalador: wizard, SQL, migrações
│   ├── agente-admin.md             # Painel: CRUDs, ACL, settings, relatórios
│   └── agente-catalog.md           # Site público: tema Twig, Three.js, rotas do front
└── decisoes/                       # 🗂️ Registro de decisões (ADR) alimentado pelos agentes
    └── README.md
```

---

## Como usar

### 1. Com Arena / Claude Code / Copilot / Cursor ou qualquer agente de IA

1. Abra o arquivo do agente desejado em [`agentes/`](agentes/) e **cole o conteúdo como prompt de sistema** (ou referencie os arquivos no contexto, se a ferramenta suportar leitura de pastas);
2. As 4 regras de [`regras/`](regras/) são **pré-requisito**: o agente as referencia, mas para contextos curtos cole também o `00` e o `03`;
3. Descreva a tarefa em linguagem natural. O agente deve responder com o **cabeçalho padrão** (ver abaixo) antes de propor código.

### 2. Fluxo recomendado de trabalho

```
Tarefa humana
    │
    ▼
Agente ORQUESTRADOR ──► classifica a tarefa e escolhe o(s) agente(s) dono(s)
    │
    ├──► agente-system    (núcleo)
    ├──► agente-install   (setup/SQL)
    ├──► agente-admin     (painel)
    └──► agente-catalog   (site público)
              │
              ▼
     Cada agente: plano → (confirmação se impacto > 1 arquivo) → implementação → validação → relatório
              │
              ▼
     Mudança nas regras/decisão arquitetural? → registrar em decisoes/ via orquestrador
```

### 3. Cabeçalho padrão de resposta (obrigatório para todos os agentes)

```
[AGENTE: system] TAREFA: <resumo em 1 linha>
ARQUIVOS ALVO: dev/system/engine/loader.php (+1 novo)
IMPACTO: núcleo compartilhado — requer confirmação
PLANO: (1) ler loader.php atual (2) adicionar método X (3) php -l (4) atualizar doc
STATUS: aguardando confirmação | em execução | concluído
```

---

## Tabela de roteamento (qual agente acionar)

| Tipo de tarefa | Agente |
| --- | --- |
| Engine, autoloader, registry, router, events, webhooks engine | `agente-system` |
| Libraries (db, cache, session, mail, template), helpers, configs | `agente-system` |
| Wizard de instalação, `install/sql/`, migrações de banco | `agente-install` |
| CRUDs do painel, ACL, settings, relatórios, integrações admin | `agente-admin` |
| Tema/temas, templates Twig, controllers do site, Three.js, SEO do front | `agente-catalog` |
| Tarefa envolvendo 2+ aplicações (ex.: CRUD admin + exibição no front) | `agente-orquestrador` coordena os dois |
| Nova regra, exceção ou mudança de convenção | `agente-orquestrador` (registra ADR) |

---

## Princípios invioláveis (resumo — detalhe em `regras/`)

1. **A fonte da verdade é o código + a pasta `docs/`.** Se a dúvida existir, LER o arquivo — nunca supor.
2. **MVCL é lei:** Model = dados, View = Twig, Controller = orquestração, Language = todo texto de interface.
3. **Nada de pastas novas** fora da estrutura documentada em [`docs/Estrutura-de-Pastas.md`](../docs/Estrutura-de-Pastas.md) sem aprovação explícita.
4. **Núcleo compartilhado (`system/`)** só muda com plano declarado e validação dos 3 consumidores (catalog, admin, install).
5. **Incerteza = pergunta.** Jamais preencher lacunas com suposições plausíveis.
6. **Toda entrega termina no checklist** do agente (Definition of Done).

---

<p align="center">agents-ia v1.0 · Outubro/2026 · ReamurCMS — disciplina primeiro, código depois.</p>
