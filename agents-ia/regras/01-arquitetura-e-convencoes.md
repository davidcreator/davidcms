# 01 — Arquitetura e Convenções (O Que Pode e o Que Não Pode)

> Estas regras são **invioláveis**. Um agente que violar qualquer item deve parar, relatar e pedir orientação. O objetivo é manter o ReamurCMS coeso com sua inspiração OpenCart e com os padrões já presentes no código real do repositório.

---

## 1. MVCL é lei

```
Controller  → orquestra: lê request, valida, chama Model/Language, escolhe a View
Model       → dados: SQL e regras de negócio. NUNCA imprime/retorna HTML.
View        → Twig em view/template/. NUNCA contém SQL nem regra de negócio.
Language    → TODO texto de interface via $_['chave']. NUNCA texto solto em controller/template.
```

| 🚫 Proibido | ✅ Correto |
| --- | --- |
| `$this->db->query()` dentro de Controller | `$this->load->model(...)` e método de Model |
| `echo "<div>"` em Model/Controller | `$this->load->view('rota', $data)` |
| `<p>Olá, mundo</p>` fixo no `.twig` | `{{ text_hello }}` vindo da language |
| `SELECT` solto em template/helper | vive exclusivamente em `model/` |
| `str_replace()` para montar URL | `$this->url->link('rota', $params)` |

## 2. Convenções de nomes (checar antes de criar)

| Item | Convenção | Exemplo |
| --- | --- | --- |
| Namespace | `Reamur\{App}\{Camada}\{Grupo}` | `Reamur\Catalog\Controller\Cms` |
| Arquivo de classe | minúsculo, espelhando a rota | `catalog/controller/cms/portfolio.php` |
| Rota | `grupo/modulo.metodo` minúsculo | `cms/portfolio.view` |
| Template | espelha a rota | `view/template/cms/portfolio.twig` |
| Language | espelha a rota | `language/pt-br/cms/portfolio.php` |
| Model acessado | `$this->model_{grupo}_{modulo}` | `$this->model_cms_portfolio` |
| Classe engine/library | `PascalCase`, namespace do núcleo | `Reamur\System\Library\Image` |
| Constante de diretório | `DIR_*` | `DIR_APPLICATION`, `DIR_STORAGE` |
| Tabela | `DB_PREFIX` + nome minúsculo | `rms_project` |
| Chave de language | `snake_case` com prefixo semântico | `$_['heading_title']`, `$_['button_send']` |
| Config nova | chave `snake_case` em `system/config/default.php` | `$_['x']` |

## 3. Regras de estrutura

1. **Nenhuma pasta de primeiro nível nova** (`dev/qualquer_coisa`) — as aplicações são exatamente quatro: `admin`, `catalog`, `install`, `system` (+ `dev/webhook` somente se o projeto formalizar; hoje o módulo vive em `system/webhook/`).
2. **Nenhum subgrupo novo** de controller/model (ex.: criar `admin/controller/blog/`) sem aprovação — primeiro verificar se a funcionalidade cabe em um grupo existente do mapa de [`docs/Estrutura-de-Pastas.md`](../../docs/Estrutura-de-Pastas.md).
3. `storage/` recebe **somente dados voláteis** (cache, logs, temp, sessions, vendor). Código-fonte **nunca** dentro de `storage/`.
4. Dependências externas **somente via Composer** em `dev/system/storage/composer.json` — nunca `require` manual de pacote baixado.
5. Arquivos novos de controller/model **sempre** acompanhados do respectivo arquivo de language (mesmo que mínimo) para cada idioma existente — no mínimo `pt-br`, com `en-us`/`es-eu` anotados como pendência se não houver tradução.

## 4. Regras do núcleo compartilhado (`system/`)

1. Mudança em `engine/`, `library/`, `helper/` ou `framework.php` **precisa de plano declarado** (cabeçalho padrão) e impacto avaliado nos 3 consumidores: `catalog`, `admin`, `install`.
2. **Compatibilidade retroativa:** métodos públicos existentes não mudam de assinatura sem ADR em `agents-ia/decisoes/`.
3. Serviços novos = classe em `library/` + registro no Registry (padrão `framework.php`) + doc. Não instanciar serviço com `new` dentro de controller.
4. `vendor.php` é a **única** ponte para pacotes Composer.

## 5. Segurança arquitetural (resumo — detalhe em regra 02)

- Entrada: `(int)`/`escape`/whitelist; SQL escapado ou prepared; CSRF em forms; upload via `upload_security.php`; senhas com `password_hash`.
- Erros: `error_display=false` em produção; exceções logadas com `$this->log->write()`; nunca vazar caminho/stack ao usuário.

## 6. Git e fluxo

- Commits em **pt-BR, Conventional Commits**: `feat`, `fix`, `docs`, `build`, `refactor`, `test`, `chore` — ex.: `feat(catalog): adicionar controller de portfólio`.
- Trabalho novo sai de `feature/*` → PR para `staging` → merge em `main` após validação.
- `main` é intocável por push direto.

## 7. Documentação obrigatória por mudança

| Mudou... | Atualizar no mesmo commit |
| --- | --- |
| Arquitetura/ciclo de vida | `docs/Arquitetura-MVCL.md` |
| Criei pasta/módulo | `docs/Estrutura-de-Pastas.md` |
| Regra/convenção | `agents-ia/regras/*` (via orquestrador) |
| Decisão arquitetural/renúncia técnica | `agents-ia/decisoes/` (ADR) |
| Padrão de template | `docs/Guia-Twig.md` / `Implementacao-ThreeJS.md` |
| Webhook | `docs/Webhooks.md` |

---

<p align="center">Arquitetura primeiro. Convenção sempre. Surpresa nunca.</p>
