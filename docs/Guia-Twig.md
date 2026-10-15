# Guia Twig — ReamurCMS

> **Objetivo:** ensinar o essencial de **Twig 3** para construir os temas do ReamurCMS (catalog e admin) e explicar como o adaptador `system/library/template/twig.php` conecta a engine ao núcleo.

---

## Sumário

1. [Por que Twig](#1-por-que-twig)
2. [Instalação e configuração](#2-instalação-e-configuração)
3. [O adaptador do ReamurCMS](#3-o-adaptador-do-reamurcms)
4. [Sintaxe essencial](#4-sintaxe-essencial)
5. [Herança de templates](#5-herança-de-templates)
6. [Filtros e funções úteis](#6-filtros-e-funções-úteis)
7. [Escape e segurança](#7-escape-e-segurança)
8. [Estrutura do tema do novo site](#8-estrutura-do-tema-do-novo-site)
9. [Cache e performance](#9-cache-e-performance)
10. [Debug](#10-debug)
11. [Boas práticas e erros comuns](#11-boas-práticas-e-erros-comuns)

---

## 1. Por que Twig

| Benefício | Como ajuda o ReamurCMS |
| --- | --- |
| **Escape automático de saída** | XSS evitado por padrão em todo o site |
| **Sintaxe limpa** | Designer e dev editam templates sem escrever PHP |
| **Herança de templates** | Layout único para o site inteiro (header/footer em 1 lugar) |
| **Compilação + cache** | Templates viram PHP nativo — rápido com OPcache |
| **Ecossistema Symfony** | Documentação e estabilidade de longo prazo |

O ReamurCMS mantém **adaptadores para outros engines** (Mustache, Latte, Blade, Handlebars, TPL, PHP, PHTML em `system/library/template/`), mas **Twig é o padrão oficial** do projeto — novos temas devem usar `.twig`.

---

## 2. Instalação e configuração

Twig já é dependência do projeto:

```json
// dev/system/storage/composer.json
"require": {
    "twig/twig": "^3.3.7"
}
```

```bash
composer install --working-dir=dev/system/storage
```

Config do núcleo:

```php
// dev/system/config/default.php
$_['template_engine']    = 'twig';
$_['template_directory'] = '';
$_['template_cache']     = false;   // true em produção
$_['template_extension'] = '.twig';
```

---

## 3. O adaptador do ReamurCMS

O arquivo `system/library/template/twig.php` encapsula o Twig para o framework:

```php
namespace Reamur\System\Library\Template;

class Twig {
    protected string $root;
    protected object $loader;   // \Twig\Loader\FilesystemLoader
    protected string $directory;
    protected array  $path = [];

    public function __construct() {
        $this->root   = rtrim(str_replace('\\', '/', DIR_REAMUR), '/');
        $this->loader = new \Twig\Loader\FilesystemLoader('/', $this->root);
    }

    // Registra um namespace de templates (ex.: tema ativo)
    public function addPath(string $namespace, string $directory = ''): void { ... }

    // Normaliza caminhos absolutos para o root do loader (usa str_starts_with - PHP 8)
    protected function normalizeLoaderPath(string $path): string { ... }

    // render($route, $data) → HTML compilado
}
```

Como o controller usa (transparência total — o controller nem sabe que é Twig):

```php
// catalog/controller/common/home.php
$this->response->setOutput(
    $this->load->view('common/home', $data)
    // resolve: catalog/view/template/common/home.twig (respeitando o tema ativo)
);
```

Também existe `template/twig_cache.php` — variante com cache de compilação ativo, usada quando `template_cache = true`.

---

## 4. Sintaxe essencial

```twig
{# comentário — não vai pro HTML #}

<h1>{{ heading_title }}</h1>            {# imprimir variável (escapado) #}

{% if projects %}                       {# controle #}
  <ul>
  {% for project in projects %}         {# loop #}
    <li class="{{ loop.first ? 'first' }}">
      <a href="{{ project.href }}">{{ project.title|upper }}</a>
      {% if project.image %}
        <img src="{{ project.image }}" alt="{{ project.title }}">
      {% endif %}
    </li>
  {% else %}                            {# coleção vazia #}
    <li>Nenhum projeto publicado.</li>
  {% endfor %}
  </ul>
{% endif %}

{% set year = "now"|date("Y") %}        {# atribuição #}
<p>© {{ year }} {{ author }}</p>
```

| Delimitador | Função |
| --- | --- |
| `{{ ... }}` | Imprime (com autoescape) |
| `{% ... %}` | Executa (if, for, set, block...) |
| `{# ... #}` | Comentário |

Testes úteis: `{% if project.image is defined %}`, `{% if projects is empty %}`, `{% if loop.index is odd %}`.

---

## 5. Herança de templates

A base do novo site (layout único):

```twig
{# catalog/view/template/layout/base.twig #}
<!DOCTYPE html>
<html lang="{{ lang }}">
<head>
  <meta charset="utf-8">
  <title>{% block title %}{{ site_name }}{% endblock %}</title>
  <meta name="description" content="{% block description %}{{ meta_description }}{% endblock %}">
  <link rel="stylesheet" href="catalog/view/css/main.css">
  {% block head_extra %}{% endblock %}
</head>
<body>
  {% include 'partial/header.twig' %}

  <main>
    {% block content %}{% endblock %}
  </main>

  {% include 'partial/footer.twig' %}

  {% block scripts %}{% endblock %}
</body>
</html>
```

Página filha:

```twig
{# catalog/view/template/cms/portfolio_view.twig #}
{% extends 'layout/base.twig' %}

{% block title %}{{ project.title }} · {{ site_name }}{% endblock %}
{% block description %}{{ project.summary }}{% endblock %}

{% block content %}
  <article class="project">
    <h1>{{ project.title }}</h1>
    <p>{{ project.description }}</p>

    <div class="gallery">
      {% for image in project.images %}
        <img src="{{ image.thumb }}" alt="{{ image.alt }}" loading="lazy">
      {% endfor %}
    </div>
  </article>
{% endblock %}

{% block scripts %}
  <script src="catalog/view/js/project-3d.js" defer></script>
{% endblock %}
```

Includes para seções reutilizáveis:

```twig
{% include 'partial/testimonials.twig' with { items: testimonials } only %}
```

> `only` evita vazar variáveis para o partial — preferível.

---

## 6. Filtros e funções úteis

| Twig | Resultado |
| --- | --- |
| `{{ title|upper }}`, `|lower`, `|capitalize` | Caixa do texto |
| `{{ text|length }}` | Tamanho |
| `{{ price|number_format(2, ',', '.') }}` | `1.299,90` (pt-BR) |
| `{{ date|date("d/m/Y") }}` | Data local |
| `{{ list|join(', ') }}` | Junção |
| `{{ list|first }}` / `|last` | Extremos |
| `{{ list|sort((a, b) => a.sort_order <=> b.sort_order) }}` | Ordenação com arrow fn |
| `{{ items|filter(i => i.status == 'published') }}` | Filtro |
| `{{ items|map(i => i.title) }}` | Projeção |
| `{{ html|raw }}` | ⚠️ Sem escape — somente HTML 100% confiável |
| `{{ json|json_encode|raw }}` | Serializa p/ `<script>` (ver §7) |
| `{{ 'pt-br'|trans }}` | Tradução (se a extensão i18n for adicionada) |

Criando filtro/função própria no adaptador:

```php
$twig->addFilter(new \Twig\TwigFilter('whatsapp', fn(string $phone): string =>
    'https://wa.me/' . preg_replace('/\D/', '', $phone)
));
```

```twig
<a href="{{ contact.phone|whatsapp }}">WhatsApp</a>
```

---

## 7. Escape e segurança

- **Autoescape HTML ligado por padrão** — `{{ comment }}` é seguro.
- `|raw` é o único "desarmador": usar exclusivamente em HTML gerado pelo próprio CMS (ex.: corpo de página vindo do editor sanitizado).
- **Dados para JavaScript:** sempre `json_encode`:

```twig
<script>
  window.portfolioData = {{ projects|json_encode|raw }};
</script>
```

- **Atributos:** o autoescape já cobre; em URLs, prefira `|url_encode` para parâmetros manuais.
- Não concatenar HTML em controller para burlar o template — se precisa de HTML custom, crie um bloco/partial.

---

## 8. Estrutura do tema do novo site

Proposta alinhada às seções atuais do davidalmeida.xyz (ver [Análise §2](Analise-DavidAlmeida.md#2-estado-atual-do-site)):

```text
dev/catalog/view/
├── theme/                     # temas instaláveis (default = novo site)
├── css/  js/                  # assets compilados
└── template/
    ├── layout/
    │   └── base.twig
    ├── partial/
    │   ├── header.twig
    │   ├── footer.twig
    │   ├── hero-3d.twig        # hero com <canvas> do Three.js
    │   ├── about.twig          # Who I Am + processo em 6 etapas
    │   ├── testimonials.twig
    │   ├── skills.twig
    │   ├── services.twig
    │   ├── tools.twig          # Fluxa, Quotia, Vertex, Solis
    │   ├── clients.twig
    │   ├── contact.twig
    │   └── newsletter.twig
    ├── common/
    │   └── home.twig           # compõe os partials da one-page
    ├── cms/
    │   ├── portfolio.twig      # grid + filtros (Visual Identity, Brand, Illustration)
    │   └── portfolio_view.twig
    ├── tool/
    │   └── tool_page.twig
    ├── information/
    │   └── page.twig           # Cookies, Políticas Internas...
    └── error/
        └── not_found.twig
```

`home.twig` minimalista:

```twig
{% extends 'layout/base.twig' %}
{% block content %}
  {% include 'partial/hero-3d.twig' %}
  {% include 'partial/about.twig' %}
  {% include 'partial/testimonials.twig' %}
  {% include 'partial/skills.twig' %}
  {% include 'partial/services.twig' %}
  {% include 'partial/tools.twig' %}
  {% include 'partial/clients.twig' %}
  {% include 'partial/contact.twig' %}
{% endblock %}
```

---

## 9. Cache e performance

| Ambiente | `template_cache` | Efeito |
| --- | --- | --- |
| Dev | `false` | Recompila a cada request |
| Produção | `true` | PHP compilado em `storage/cache/template/` + OPcache |

```php
$_['template_cache'] = true; // produção
```

Limpe o cache ao deployar templates novos:

```bash
rm -rf dev/system/storage/cache/template/*
```

Outras práticas: carregar JS 3D com `defer`, imagens com `loading="lazy"`, CSS crítico inline no `head` do `base.twig`.

---

## 10. Debug

Modo debug com `dump()` (ambiente de desenvolvimento):

```php
// ao montar o ambiente Twig no adaptador (config de dev)
$twig->addExtension(new \Twig\Extension\DebugExtension());
```

```twig
{{ dump(project) }}        {# inspeciona a variável #}
```

Erros comuns e mensagens-chave:
- `Unknown "x" filter` → filtro não registrado / typo
- `Template "x.twig" is not defined` → caminho errado ou namespace de tema não registrado via `addPath()`
- Variáveis indefinidas em modo estrito → garanta `$data` completo no controller ou use `is defined`

---

## 11. Boas práticas e erros comuns

✅ **Fazer**
- Layout único + partials pequenos e especializados
- Todo texto de interface via variável vinda da camada **Language**
- `|json_encode|raw` para dados → JS
- `{% ... %}` multilinha com indentação, como em HTML
- Cache ligado em produção

❌ **Evitar**
- Lógica de negócio no template (cálculos, regras — isso é do Model/Controller)
- `|raw` em conteúdo fornecido por usuário
- Queries disfarçadas via funções custom que consultam banco
- Templates gigantes (>300 linhas) — divida em partials

---

## Veja também

- [Twig 3 — documentação oficial](https://twig.symfony.com/doc/3.x/)
- [Arquitetura MVCL §8](Arquitetura-MVCL.md#8-views-e-template-engines) — adaptadores de template
- [Guia Three.js](Guia-ThreeJS.md) — integração do canvas 3D com templates
- [Guia CMS §4](Guia-CMS.md#4-gestão-de-conteúdo) — conteúdo que alimenta os templates
