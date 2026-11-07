# Diagnóstico — BL: por que o vazamento continua e o editor não carrega os dados reais

**HEAD auditado:** `3219ccae80550f81510068c311a8afcc1de8d71a` · CI 7/7 ✅
**Conclusão:** as duas reclamações têm **uma única causa**: o que foi
commitado no repositório **não é** o que o Admin e o Catálogo leem.

## 1. O que o commit `3219ccae…` realmente fez

Ele adicionou os **arquivos do ZIP BK** (`DIAGNOSTICO-BK.md`, os 4 JSONs
corrigidos em `docs/temas/`, `docs/correcao/correcao-runtime.patch`, evidências).
Verificamos o diff: **`dev/` não foi tocado**. No HEAD atual as 3 linhas do
patch continuam exatamente iguais:

| Arquivo | Linha | Conteúdo no HEAD |
|---|---|---|
| `common/home.twig` | 4 | `{% include 'partial/theme-scene-block.twig' %}` |
| `common/home.php` | 369 | `$data['theme_scene_view'] = …get('catalog_theme_scene_view', [])` |
| `startup/theme.php` | 155 | `$this->config->set('catalog_theme_scene_view', $snapshot['scene_view'])` |

Ou seja: **`git apply` não foi executado**. E mesmo que tivesse sido, ainda
faltaria a peça principal abaixo.

## 2. Repositório ≠ banco de dados

Temas **não** são lidos de `docs/temas/*.json`. São registros da tabela
`design_theme`, com três payloads distintos:

| Coluna | Quem grava | Quem lê |
|---|---|---|
| `draft_config` | `save($id,…)` e `import($id,…)` | **editor** (`edit()`), prévia de rascunho |
| `published_config` | **somente `activate()`** | **Catálogo** (`startup/theme.php:79-80` → `getThemeMap('published')`) |
| `config` | criação e `activate()` | fallback legado |

`dev/admin/model/design/theme.php::save()` com `$id` faz:

```sql
UPDATE design_theme SET name=…, slug=…, `draft_config`=…, date_modified=NOW()
WHERE theme_id=…
```

**Não toca em `published_config` nem em `config`.** Só `activate()` executa
`published_config = draft_config, config = draft_config, status=1`.

E `import()` com `theme_id=0` (importar pela lista) cria um registro **novo com
`status=0`** — inativo. Portanto:

- atualizar/colocar os JSONs em `docs/` → **não muda nenhum registro**;
- **importar** num tema existente → muda só o rascunho;
- **salvar** no editor → muda só o rascunho;
- **sem clicar em Ativar**, o Catálogo continua usando o `published_config`
  antigo, com o `common/home` antigo e o `kaz_scene` antigo → **vazamento
  persiste**.

Provamos isso com o **model real** em DB em memória (`evidencia/model-harness.txt`,
10 OK / 0 FALHAS): após `import()` o rascunho fica limpo, mas o publicado
continua com o include; só `activate()` publica.

## 3. Por que o KAZEditor-Theme "não carrega as informações reais"

`edit()` lê `draft_config` do registro — correto. O problema está em
`theme.php:369`:

```php
$data['template_sources']=$data['templates'];
foreach($data['template_keys'] as $templateKey){
  if(!isset($data['template_sources'][$templateKey])){
    $templateFile=…/catalog/view/template/{$templateKey}.twig;
    if(is_file($templateFile)) $data['template_sources'][$templateKey]=file_get_contents($templateFile);
  }
}
```

Se o registro não tem `templates` (temas criados antes dos pacotes, ou
rascunho gravado sem templates), o editor **preenche as 25 lacunas com os
`.twig` nativos do disco** e mostra `#2563eb`/`#ffffff` e tokens default.
Você vê o tema **do sistema**, não o tema que importou. A prévia isolada
ainda devolve *"No saved source is available"* porque `preview()` exige
`templates[$key]` no rascunho.

**E há um efeito colateral grave:** esses textareas têm
`name="templates[…]"` (`kaz_theme_editor.twig:507`). Ao clicar em **Salvar**,
`buildDraftConfig()` recebe os 25 fontes nativos e os grava como se fossem do
tema. O `common/home.twig` nativo contém justamente
`{% include 'partial/theme-scene-block.twig' %}` — então **cada salvamento no
editor reinjeta o vazamento**, mesmo depois de importar o pacote corrigido.
Isso fecha o ciclo das duas reclamações.

## 4. Correção da frente BL

### Patch A — `A-vazamento-failclosed.patch` (**obrigatório**)

Diferente da BK, **não depende** de reimportar, ativar ou do conteúdo do
banco. O ponto central é um **gate fail-closed** no renderizador:

```twig
{% if theme_scene_preview|default(false) and theme_scene_view.enabled|default(false) %}
```

`twig.php::renderSandboxPreview()` passa a injetar `theme_scene_preview=true`
(2 pontos). O Catálogo nunca injeta esse flag → o partial **nunca** renderiza
no front, mesmo com `published_config` antigo, mesmo com `kaz_scene` cheio,
mesmo se algum tema ainda incluir o partial. A prévia do Admin e o
`ThemeCompareView` (fallback PHP próprio) continuam mostrando a cena.

Mais 5 edições de defesa em profundidade: as 3 remoções da BK
(`home.twig`, `home.php`, `startup/theme.php`) e os 2 flags no `twig.php`.
Total: **6 edições em 5 arquivos**. `theme-scene-block.twig` continua existindo
e sendo usado no Admin — não foi removido.

### Patch B — `B-editor-dados-reais.patch` (**recomendado**)

- `edit()`: se o rascunho não traz `templates`, **herda do `published_config`**
  (o dado real do tema) antes de qualquer fallback;
- expõe `template_saved_keys` = chaves que realmente pertencem ao tema;
- no editor, fontes nativos ficam **visíveis**, porém `disabled` e **sem
  `name=`**, com aviso *"Fonte nativa do disco — não pertence a este tema e
  não é salva"*. Assim salvar não reintroduz o include.

`buildDraftConfig()`, `save()`, `activate()`, `ThemePackage`, allowlists,
contratos, manifestos, schema e renderer **não foram alterados**.

## 5. Diagnóstico no seu servidor (somente leitura)

`tools/diagnostico-tema.sql` faz apenas `SELECT` e responde: qual tema está
ativo, quantos templates há em cada payload e **onde** está o include. Rode
antes de aplicar os patches e guarde a saída.

## 6. Ordem operacional (importa)

1. Rodar `tools/diagnostico-tema.sql` e anotar o `theme_id` ativo.
2. `git apply docs/correcao/A-vazamento-failclosed.patch`
3. `git apply docs/correcao/B-editor-dados-reais.patch`
4. Limpar `storage/cache/template/` (Twig reconstrói o `ChainLoader`).
5. Admin → **Temas do Catálog** → abrir o **tema ativo** → **Importar tema**
   com o `.json` corrigido → **Ativar** (sem Ativar, nada muda no site).
6. Conferir em aba anônima: não deve existir `<section class="rms-scene">`.
7. `node tools/verify-bl.cjs <raiz>`.

> Passo 2 sozinho já fecha o vazamento. Os passos 3–5 garantem que o editor
> passe a mostrar/editar os dados reais e que o tema publicado fique limpo.

## 7. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration/`ALTER`, nenhum SQL de
escrita, nenhum runtime/MySQL/deploy executado por mim, nenhuma decisão do
**Recorte B**, nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em
`PASS`. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM e guardas
estáticos **não significam release aprovada**. Aceite visual e health no Admin
real continuam pendentes.
