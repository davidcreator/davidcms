# 📚 Documentação do ReamurCMS

Bem-vindo à central de documentação do **ReamurCMS** — o CMS em PHP 8+ com arquitetura MVCL que sustenta a nova geração do [davidalmeida.xyz](https://davidalmeida.xyz).

Estes documentos foram escritos para servir como referência durante todo o ciclo de desenvolvimento do projeto: da fundação do núcleo até o lançamento do novo site.

---

## 🗺️ Mapa da documentação

| # | Documento | Propósito |
| --- | --- | --- |
| 1 | [Analise-DavidAlmeida.md](Analise-DavidAlmeida.md) | **Comece por aqui.** Análise completa do site atual (conteúdo, stack, CMS legado) e o plano de migração faseado para o ReamurCMS. |
| 2 | [Arquitetura-MVCL.md](Arquitetura-MVCL.md) | Como funciona o padrão **MVCL** (Model-View-Controller-Language), o ciclo de vida da requisição e as convenções do núcleo `Reamur\*`. |
| 3 | [Guia-CMS.md](Guia-CMS.md) | Conceitos essenciais de **CMS** e como cada módulo do ReamurCMS resolve conteúdo, usuários, SEO, segurança e extensões. |
| 4 | [Guia-PHP8.md](Guia-PHP8.md) | Recursos do **PHP 8+** aplicados no núcleo, padrões de código, segurança e performance. |
| 5 | [Guia-Composer.md](Guia-Composer.md) | **Composer**: anatomia do `composer.json` do projeto, autoload, comandos do dia a dia e integração com o GitHub Actions. |
| 6 | [Guia-Twig.md](Guia-Twig.md) | **Twig 3**: sintaxe, herança de templates, cache e o adaptador `system/library/template/twig.php`. |
| 7 | [Guia-ThreeJS.md](Guia-ThreeJS.md) | **Three.js**: como criar as experiências 3D do novo front-end (hero, portfólio, ferramentas). |
| 8 | [Webhooks.md](Webhooks.md) | O sistema de **webhooks** do ReamurCMS para pedidos e produtos: arquitetura, API e como criar novos gatilhos. |
| 9 | [Estrutura-de-Pastas.md](Estrutura-de-Pastas.md) | Referência anotada de **toda a árvore de diretórios** do `dev/` — consulta rápida do que vai em cada pasta. |

---

## 🧭 Ordem de leitura sugerida

1. **[Análise do site](Analise-DavidAlmeida.md)** → entenda *o que* estamos migrando e *por quê*.
2. **[Arquitetura MVCL](Arquitetura-MVCL.md)** → entenda *como* o sistema está organizado.
3. **[Estrutura de Pastas](Estrutura-de-Pastas.md)** → saiba *onde* cada coisa mora.
4. Guias técnicos conforme a tarefa do dia: [PHP 8](Guia-PHP8.md) · [Composer](Guia-Composer.md) · [Twig](Guia-Twig.md) · [Three.js](Guia-ThreeJS.md) · [Webhooks](Webhooks.md).
5. **[Guia CMS](Guia-CMS.md)** → visão de produto: módulos, conteúdo, usuários e go-live.

---

## ✍️ Convenções desta documentação

- **Idioma:** Português (pt-BR). Termos técnicos consolidados permanecem em inglês (*controller*, *template*, *webhook*…).
- **Nomes de arquivo:** `kebab-case` com inicial maiúscula (ex.: `Guia-Twig.md`).
- **Código:** sempre em blocos cercados (\`\`\`) com indicação de linguagem (`php`, `bash`, `json`, `twig`, `js`).
- **Cabeçalho de cada doc:** título, objetivo de uma linha e sumário local.
- **Atualização:** toda mudança estrutural no `dev/` deve vir acompanhada de updates nos documentos [Arquitetura-MVCL.md](Arquitetura-MVCL.md) e/ou [Estrutura-de-Pastas.md](Estrutura-de-Pastas.md) no mesmo commit.

---

## 🔗 Referências externas essenciais

- [PHP 8 — Manual oficial](https://www.php.net/releases/8.0/pt_BR.php)
- [Composer — Documentação](https://getcomposer.org/doc/)
- [Twig 3 — Documentação](https://twig.symfony.com/doc/3.x/)
- [Three.js — Documentação](https://threejs.org/docs/)
- [OpenCart — arquitetura que inspira o ReamurCMS](https://docs.opencart.com/)

---

<p align="center">ReamurCMS · Documentação v1.0 · Outubro/2026</p>
