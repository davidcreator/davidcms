# 🧩 modelos — Esqueletos Canônicos de Arquivo

> Estes templates resolvem a lacuna **L3** da [Auditoria](../../docs/Auditoria-Fundacao.md): agente **nunca gera arquivo "de memória"** — copia o modelo correspondente e substitui os placeholders.

## Como usar

1. Identifique o tipo de arquivo na tabela abaixo;
2. Copie o `.tpl` para o destino correto (conferir [`docs/Estrutura-de-Pastas.md`](../../docs/Estrutura-de-Pastas.md));
3. Substitua os placeholders `{{MAIUSCULO}}` (busca global no arquivo);
4. Remova comentários `TEMPLATE:` que não se aplicam;
5. Rode `php -l` no resultado e siga o DoD do seu agente.

## Catálogo de modelos

| Modelo | Gera | Quando usar |
| --- | --- | --- |
| `front-controller-index.php.tpl` | `index.php` de `catalog`/`admin`/`install` | Somente conforme [Contrato-Bootstrap](../../docs/Contrato-Bootstrap.md) |
| `controller-crud-admin.php.tpl` | Controller de CRUD do painel (`index`/`form`/`save`/`delete`) | Nova entidade do admin |
| `model.php.tpl` | Model com `get/add/edit/delete/getTotal` | Qualquer aplicação |
| `language.php.tpl` | Arquivo `$_['chave']` | Sempre que criar controller/template |
| `admin-list.twig.tpl` | Lista do admin (tabela, paginação, ações em massa) | CRUDs do painel |
| `admin-form.twig.tpl` | Formulário do admin (abas por idioma, CSRF, validação) | CRUDs do painel |
| `scene-threejs.js.tpl` | Cena Three.js no padrão SceneModule | Nova cena 3D (ver `docs/Implementacao-ThreeJS.md`) |

## Regras

- Template aqui é **lei de formato**: se o modelo e a sua vontade divergirem, vença o modelo — ou proponha melhoria ao orquestrador (que atualiza o template para todos);
- Placeholders sempre em `{{DOUBLE_CURLY}}` para nunca colidir com sintaxe Twig/PHP reais;
- Melhoria de template = PR próprio (escopo `agents-ia`); nunca "ajustar o modelo só nesta cópia".
