# 02 — Padrões de Código

> Padrões técnicos que TODO código gerado por agente deve seguir. Ao conflitar com código legado existente no arquivo, **prevalece o padrão deste documento para código novo**, sem reformatar o restante do arquivo na mesma entrega (refactors são tarefas próprias).

---

## 1. PHP 8+

```php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
```

**Obrigatório:**
- Declarar tipos em **toda** propriedade, parâmetro e retorno possível (`string`, `array`, `int`, `mixed`, `void`);
- PHPDoc em classes e métodos públicos;
- Aspas simples por padrão;
- Entrada validada no topo do método: `(int)`, `(string)`, whitelist com `in_array(..., true)`;
- `match` em vez de cadeias de `if/elseif` de mapeamento; `??` e `?->` para nulos possíveis;
- `private`/`protected` por padrão — `public` só o que é API intencional.

**Proibido:**
- Funções `mysql_*`, `eval`, `extract($this->request->post)`, variáveis variáveis (`$$x`), `@` supressor de erro;
- `die()`/`exit()` fora de `startup.php`; uso correto é `$this->response->redirect()`/output;
- Funções globais novas fora de `system/helper/`.

Referência completa: [`docs/Guia-PHP8.md`](../../docs/Guia-PHP8.md).

## 2. SQL e Model

```php
public function getProject(int $project_id): array {
    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "project`
        WHERE `project_id` = '" . (int)$project_id . "'
          AND `status` = '1'");

    return $query->row;
}
```

- Sempre `DB_PREFIX`; sempre escape (`(int)`, `$this->db->escape()`); preferir bindings no driver PDO;
- Retornos padronizados: `row` (1 registro), `rows` (lista), `bool` em operações de escrita quando fizer sentido;
- Nomes de método: `get*`, `get*List`/`get*s`, `add*`, `edit*`, `delete*`, `getTotal*`.

## 3. Controller

- Métodos de ação são `public function {acao}(): void` e terminam em `$this->response->setOutput(...)` ou `->redirect()`;
- JSON: `$this->response->addHeader('Content-Type: application/json')` + `json_encode`;
- Carregar language no início: `$this->load->language('grupo/modulo')`;
- Sem lógica de negócio: transformar/validar entrada e delegar ao Model.

## 4. Twig

- Todo texto: `{{ text_* }}` / `{{ heading_title }}` vindos da language;
- Dados p/ JS: `{{ var|json_encode|raw }}` — único `|raw` permitido sem justificativa;
- Herança: páginas estendem `layout/base.twig`; sem bloco novo sem atualizar o `Guia-Twig`;
- Partials com `{% include ... with { ... } only %}`.

## 5. JavaScript / Three.js

- ES Modules; arquivos em `catalog/view/js/three/` seguindo o padrão **SceneModule** (`play/pause/resize/dispose`) de [`docs/Implementacao-ThreeJS.md`](../../docs/Implementacao-ThreeJS.md);
- `defer`/module; listeners com `{ passive: true }` quando possível; cleanup em `dispose()`;
- Respeitar budgets: DPR ≤ 2, `prefers-reduced-motion`, lazy-init.

## 6. Segurança (checklist por entrega)

- [ ] SQL escapado/prepared
- [ ] Saída escapada (autoescape Twig intacto)
- [ ] CSRF token em formulário novo
- [ ] Upload só via `upload_security.php`
- [ ] Sem secrets no código (config/env)
- [ ] Permissão checada (admin: rota na ACL do grupo)

## 7. Qualidade antes de entregar

```bash
php -l <arquivo.php>                 # sintaxe (obrigatório em 100% dos arquivos alterados)
composer validate --strict           # se tocou composer.json (working-dir dev/system/storage)
```

## 8. Commit

```
<tipo>(<escopo>): <descrição em pt-BR, imperativo, ≤ 72 chars>

Escopo = aplicação ou módulo: catalog, admin, install, system, webhook, docs, ci
Ex.: feat(catalog): exibir galeria de imagens na página de projeto
     fix(system): corrigir escape no driver mysqli
     docs: atualizar Estrutura-de-Pastas com módulo de portfólio
```

---

<p align="center">Código novo impecável. Legado respeitado. Refactor é tarefa própria, com plano.</p>
