# ReamurCMS

> CMS open source em **PHP 8+** com arquitetura **MVCL**, inspirado no OpenCart, criado para ser a nova base do portfólio e do ecossistema de serviços de **[davidalmeida.xyz](https://davidalmeida.xyz)**.

![Versão](https://img.shields.io/badge/vers%C3%A3o-v1.0.0--dev-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-777BB4?logo=php&logoColor=white)
![Template Engine](https://img.shields.io/badge/Template-Twig%203-green)
![Licença](https://img.shields.io/badge/licen%C3%A7a-GPL--3.0-orange)
![Status](https://img.shields.io/badge/status-em%20desenvolvimento-yellow)

---

## Índice

- [ReamurCMS](#reamurcms)
  - [Índice](#índice)
  - [Sobre o projeto](#sobre-o-projeto)
    - [Por que "inspirado no OpenCart"?](#por-que-inspirado-no-opencart)
  - [Principais recursos](#principais-recursos)
  - [Stack técnica](#stack-técnica)
  - [Estrutura do repositório](#estrutura-do-repositório)
  - [Requisitos](#requisitos)
  - [Instalação](#instalação)
  - [Fluxo de desenvolvimento](#fluxo-de-desenvolvimento)
  - [Documentação](#documentação)
  - [Roadmap](#roadmap)
  - [Segurança](#segurança)
  - [Licença](#licença)
  - [Autor](#autor)
  - [Créditos](#créditos)

---

## Sobre o projeto

O **ReamurCMS** é um sistema de gerenciamento de conteúdo (CMS) desenvolvido em PHP moderno (8+), com arquitetura **MVCL** (*Model – View – Controller – Language*) inspirada no OpenCart, mas reescrito com namespaces, componentes via Composer e boas práticas atuais.

O projeto nasceu como a base da reformulação do site **[davidalmeida.xyz](https://davidalmeida.xyz)** — portfólio e plataforma de serviços de **David L. Almeida**, designer e desenvolvedor — cujo CMS atual organiza conteúdos, portfólio, ferramentas digitais e pedidos/orçamentos de clientes. A meta é substituir esse CMS simples por um sistema robusto, extensível e multi-idioma **sem perder a lógica de negócio já consolidada**, mantendo a estrutura base e o nome **ReamurCMS** em todo o núcleo (namespaces `Reamur\*`, constante `DIR_REAMUR`, sessão `RMSSESSION`).

### Por que "inspirado no OpenCart"?

O OpenCart provou, ao longo de anos, que a separação em quatro aplicações (`catalog`, `admin`, `install` e `system`) com um núcleo leve baseado em **Registry + Loader + Events** é simples de entender, hospedar e estender. O ReamurCMS aproveita esse desenho arquitetural — familiar para quem já programou no sistema legado — e o atualiza para PHP 8+, Composer e Twig 3.

---

## Principais recursos

| Área | Recursos |
| --- | --- |
| 🧱 **Arquitetura** | MVCL, multi-aplicação (`catalog`, `admin`, `install`, `webhook`), Registry, Autoloader PSR-like, Router/Action, Events, Proxy para lazy-loading de models |
| 🎨 **Templates** | **Twig 3** como engine padrão + adaptadores para Mustache, Latte, Blade, Handlebars (hbs), TPL, PHP e PHTML |
| 🗄️ **Banco de dados** | Drivers MySQLi, MariaDB, PDO, PostgreSQL e NoSQL |
| ⚡ **Cache & Sessão** | Cache APCu, Arquivo, Mem, Memcached e Redis · Sessão em Arquivo, Banco de dados ou Redis (`RMSSESSION`) |
| 🌐 **Multi-idioma** | Estrutura de idiomas `pt-br`, `en-us` e `es-eu` em todas as aplicações |
| 🔗 **Webhooks** | Engine própria de webhooks (`system/engine/webhook.php`) com triggers para **pedidos** e **produtos** |
| 🤖 **Integrações** | Biblioteca de IA (`system/library/ai.php`), Mercado Pago Marketplace, Google Shopping, OpenBay (Amazon/eBay/Etsy/FBA) e SquareUp |
| 🛡️ **Segurança** | Validação de upload (`upload_security.php`), Encryption, Sessão com SameSite, bloqueio de IP de API, helpers de SEO e slug |
| 📦 **Ecosistema** | Extensões (namespace `Reamur\Extension`), Marketplace, sistema de instalação/upgrade com wizard |
| 🔍 **SEO** | Helpers de SEO e geração de slugs, URLs amigáveis preparadas para o catálogo e o portfólio |

---

## Stack técnica

| Camada | Tecnologia |
| --- | --- |
| Linguagem | **PHP >= 8.0** (tipagem estrita, union types, `mixed`, arrow functions) |
| Gerenciador de dependências | **Composer 2** (`dev/system/storage/composer.json`) |
| Template engine | **Twig ^3.3** (padrão), Mustache ^3.0, Latte ^3.0 |
| Front-end / 3D | **Three.js** (experiências 3D do novo site), HTML5, CSS3 (SCSS via `scssphp`), JavaScript |
| HTTP / APIs | Guzzle 7, AWS SDK PHP |
| Banco de dados | MySQL / MariaDB (recomendado), PostgreSQL, PDO |
| CI/CD | GitHub Actions (`.github/workflows/php.yml`) |
| Documentação de API | phpDocumentor |

---

## Estrutura do repositório

```text
davidcms/
├── .github/workflows/      # CI (composer ✔ + php -l ✔ + link check ✔)
├── .github/pull_request_template.md   # DoD obrigatório por PR
├── .editorconfig           # Formatação unificada (humanos + agentes)
├── CONTRIBUTING.md · SECURITY.md · CHANGELOG.md
├── agents-ia/              # 🤖 Agentes de IA: regras globais + agentes por aplicação
│   ├── regras/             #   Constituição (contexto, convenções, padrões, anti-alucinação)
│   ├── agentes/            #   orquestrador, system, install, admin, catalog
│   ├── modelos/            #   Esqueletos canônicos de arquivo (copiar e preencher)
│   └── decisoes/           #   Registro de decisões (ADR 0001–0004)
├── docs/                   # 📚 Documentação do projeto (este guia e manuais)
│   ├── Analise-DavidAlmeida.md   # Análise do site atual → plano de migração
│   ├── Arquitetura-MVCL.md       # Como o MVCL do ReamurCMS funciona
│   ├── Auditoria-Fundacao.md     # Auditoria: o que faltava amarrar (L1–L10)
│   ├── Contrato-Bootstrap.md     # Contrato dos pontos de entrada (index.php)
│   ├── Dicionario-de-Dados.md    # Schema canônico do banco de dados
│   ├── Guia-CMS.md               # Conceitos de CMS aplicados ao projeto
│   ├── Guia-PHP8.md              # PHP 8+ no ReamurCMS
│   ├── Guia-Composer.md          # Composer, dependências e CI
│   ├── Guia-Twig.md              # Twig 3 e adaptadores de template
│   ├── Guia-ThreeJS.md           # Three.js no front-end do novo site
│   ├── Implementacao-ThreeJS.md  # Plano de implementação do front 3D
│   ├── Webhooks.md               # Sistema de webhooks (pedidos e produtos)
│   └── Estrutura-de-Pastas.md    # Referência completa da árvore de diretórios
├── dev/                    # Código-fonte da aplicação
│   ├── admin/              # Painel administrativo (controller, model, view, language)
│   ├── catalog/            # Loja/site público (front-end do CMS)
│   ├── install/            # Wizard de instalação/upgrade + SQL
│   ├── system/             # Núcleo do framework (engine, library, helper, config,
│   │                       #   storage, webhook, vendor.php, framework.php, startup.php)
│   └── tests/              # Suíte PHPUnit (ativa na Fase 1 — ADR 0004)
└── README.md               # Este arquivo
```

> 📖 A referência detalhada de cada pasta e módulo está em [`docs/Estrutura-de-Pastas.md`](docs/Estrutura-de-Pastas.md).

---

## Requisitos

- **PHP >= 8.0** (recomendado 8.2+), com extensões `curl`, `mbstring`, `openssl`, `json`, `zip` e `gd`
- **Composer 2**
- **MySQL 5.7+ / MariaDB 10.3+** (ou PostgreSQL / PDO)
- **Apache** (mod_rewrite) ou **Nginx**
- Permissão de escrita em `dev/system/storage/` (`cache`, `logs`, `session`, `temp`)

---

## Instalação

> ⚠️ O projeto está em fase de **esqueleto estrutural** (diretórios preenchidos com `index.html` de placeholder). Os passos abaixo refletem a instalação alvo do ReamurCMS e serão validados conforme o núcleo for implementado.

```bash
# 1. Clonar o repositório
git clone https://github.com/davidcreator/davidcms.git
cd davidcms

# 2. Instalar dependências do núcleo (Twig, Guzzle, etc.)
composer install --working-dir=dev/system/storage

# 3. Apontar o virtual host para a pasta dev/ e acessar o wizard
#    http://localhost/install  → segue o passo a passo (banco, idioma, admin)

# 4. Conferir permissões de escrita do storage
chmod -R 755 dev/system/storage
```

---

## Fluxo de desenvolvimento

| Branch | Propósito |
| --- | --- |
| `main` | Versão estável/etiquetada (tag atual: **v1.0.0**) — protegida, recebe merges via PR |
| `staging` | Homologação: tudo que vai para produção passa por aqui |
| `feature/*` | Desenvolvimento de funcionalidades (ex.: `feature/system`) |

**Convenções:**

- Commits em português, no padrão *Conventional Commits* (ex.: `feat(webhook): adicionar sistema de webhook para pedidos e produtos`).
- Namespaces sempre sob `Reamur\...`; constante raiz `DIR_REAMUR`; prefixo de sessão `RMS`.
- Todo push/PR para `main` dispara o workflow de CI que valida o `composer.json` e instala as dependências.

---

## Documentação

Toda a documentação do projeto vive na pasta [`docs/`](docs/README.md):

| Documento | Conteúdo |
| --- | --- |
| [Análise — DavidAlmeida.xyz](docs/Analise-DavidAlmeida.md) | Inventário do site atual, diagnóstico e plano de migração para o ReamurCMS |
| [Arquitetura MVCL](docs/Arquitetura-MVCL.md) | Ciclo de vida da requisição, engine, Registry, Events e convenções |
| [Guia CMS](docs/Guia-CMS.md) | Módulos do CMS, conteúdo, usuários, SEO e segurança |
| [Guia PHP 8+](docs/Guia-PHP8.md) | Recursos do PHP 8 usados no núcleo e boas práticas |
| [Guia Composer](docs/Guia-Composer.md) | Dependências, autoload, comandos e integração com o CI |
| [Guia Twig](docs/Guia-Twig.md) | Sintaxe, herança de templates e integração com o núcleo |
| [Guia Three.js](docs/Guia-ThreeJS.md) | Cenas 3D para o novo front-end do site |
| [Implementação Three.js](docs/Implementacao-ThreeJS.md) | Plano prático do front 3D: módulos de cena, budgets e critérios de aceite |
| [Webhooks](docs/Webhooks.md) | Engine de webhooks para pedidos e produtos |
| [Estrutura de Pastas](docs/Estrutura-de-Pastas.md) | Mapa completo e anotado do diretório `dev/` |

**Desenvolvimento assistido por IA:** os agentes (`agente-system`, `agente-install`, `agente-admin`, `agente-catalog` + orquestrador), as regras globais (contexto, convenções, padrões e protocolo anti-alucinação) e o registro de decisões vivem em [`agents-ia/`](agents-ia/README.md).

---

## Roadmap

- [x] **Fase 0 — Fundação**: estrutura de pastas MVCL, `composer.json`, CI inicial, tag `v1.0.0`
- [x] **Fase 0.1 — Webhooks**: engine + triggers de pedidos e produtos
- [x] **Fase 0.2 — Documentação**: `README.md` completo + pasta `docs/` *(este repositório)*
- [ ] **Fase 1 — Núcleo**: port/refino de `engine`, `library` e `framework.php` para PHP 8 estrito
- [ ] **Fase 2 — Instalador**: wizard `install/` funcional com migrações SQL
- [ ] **Fase 3 — Catalog**: tema do novo davidalmeida.xyz (Twig + Three.js) e migração do conteúdo
- [ ] **Fase 4 — Admin**: painel para conteúdos, clientes, pedidos/orçamentos e ferramentas (Fluxa, Quotia, Vertex, Solis)
- [ ] **Fase 5 — Integrações**: Mercado Pago, webhooks externos, Google Shopping
- [ ] **Fase 6 — Qualidade & Lançamento**: testes, hardening, SEO/redirects e deploy — ciclo **2026 → 2027** ("Projeto 2027")

---

## Segurança

Encontrou uma vulnerabilidade? **Não abra uma issue pública.** Entre em contato diretamente pelo e-mail [contato@davidalmeida.xyz](mailto:contato@davidalmeida.xyz) descrevendo o problema, e ele será tratado com prioridade.

Recomendações gerais e checklist de hardening estão em [`docs/Guia-CMS.md`](docs/Guia-CMS.md) e [`docs/Webhooks.md`](docs/Webhooks.md).

---

## Licença

Distribuído sob a licença **GPL-3.0-or-later**. Veja mais em [opensource.org/licenses/GPL-3.0](https://opensource.org/licenses/GPL-3.0).

---

## Autor

**David L. Almeida** — *Creative Developer*
Designer e desenvolvedor: identidade visual, web design, e-commerce, CMS e SEO.

- 🌐 Site: [davidalmeida.xyz](https://davidalmeida.xyz)
- 🐙 GitHub: [@davidcreator](https://github.com/davidcreator)
- ✉️ E-mail: [contato@davidalmeida.xyz](mailto:contato@davidalmeida.xyz)
- 💬 WhatsApp: [+55 11 98393-8347](https://wa.me/+5511983938347)
- 📷 Instagram: [@davidalmeidades](https://www.instagram.com/davidalmeidades/)
- 👍 Facebook: [davidalmeidadesigner](https://www.facebook.com/davidalmeidadesigner/)
- 📌 Pinterest: [davidalmeidades](https://br.pinterest.com/davidalmeidades/)
- ✖️ X: [@DavidAlmeidaDes](https://x.com/DavidAlmeidaDes)
- 🎨 RedBubble: [DavidCreator](https://www.redbubble.com/people/DavidCreator/)

---

## Créditos

- Arquitetura inspirada no **[OpenCart](https://www.opencart.com/)** (MVCL: Model-View-Controller-Language)
- Template engine **[Twig](https://twig.symfony.com/)** (Symfony)
- Renderização 3D **[Three.js](https://threejs.org/)**
- Todas as demais bibliotecas listadas em [`dev/system/storage/composer.json`](dev/system/storage/composer.json)

---

<p align="center">© 2025–2026 David L. Almeida · ReamurCMS — feito com dedicação em São Paulo, Brasil 🇧🇷</p>
