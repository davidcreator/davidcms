# Guia — Section Builder (Frente F)

A **F** torna a composição da home um dado do tema. Antes, a ordem das seções
vinha somente de `cms/slot.sections` (página CMS); agora o tema pode declarar
quais seções aparecem e em que ordem — e isso viaja em pacotes da **E**, respeita
a herança child-theme e aparece na prévia **C**.

## Regra de ouro: uma única allowlist

`SectionRegistry` **não** cria uma segunda lista. Os 12 identificadores e a
validação de ordem são delegados a `\Reamur\System\Library\Cms\PageLayout`
(`options('home')` / `normalizeSections('home', ...)`). O registry guarda só
metadados de apresentação (rótulo, grupo, partial, cor/altura da fita). Se o
contrato CMS mudar, o builder muda junto — sem divergência.

Seções: `hero`, `about`, `methodology`, `skills`, `services`, `editorial`,
`testimonials`, `portfolio`, `tools`, `news`, `clients`, `contact`.

## Compatibilidade (importante)

| Estado do `config` | Comportamento no Catalog |
|---|---|
| `sections` **ausente** | layout legado do CMS (`cms/slot.sections`), idêntico às frentes A–E |
| `sections: []` | override explícito: home sem seções (só header, cena opcional e footer) |
| `sections: ["services","hero"]` | exatamente essas, nessa ordem |

Temas e pacotes das frentes anteriores continuam funcionando sem migração:
nenhuma coluna, tabela ou migration foi criada. No formulário Admin, um tema
sem override **começa com todas as 12 seções marcadas** e exibe o aviso
"Ainda usando a estrutura definida pelo CMS"; ao salvar, a ordem passa a ser
do tema.

## Como usar

1. Abra **Design → Temas → Editar**.
2. No card **Seções da home**, marque/desmarque e use ↑ / ↓ para reordenar.
3. **Salvar rascunho** grava `sections` no `draft_config`. Nada é publicado.
4. **Comparar publicado × rascunho** mostra a fita de seções nos dois painéis e
   o diff estruturado (`sections.0`, `sections.1`, …).
5. **Exportar** inclui `sections` no pacote `reamurcms-theme` v1; o `checksum`
   SHA-256 cobre a nova chave. **Importar** aplica como rascunho, com as mesmas
   validações fail-closed.
6. Publique com **Ativar** quando quiser (fluxo existente, sem alteração).

## Child-theme

`sections` é uma lista ordenada, então é **substituída** (não mesclada por
chave) na cadeia pai → filho — o mesmo tratamento de `conditions`. Um filho sem
`sections` herda a lista do pai; um filho com `[]` desliga todas as seções.

## Segurança

- Só IDs allowlistados chegam ao `common/home.twig`, que já inclui parciais
  fixos por `if/elseif`; nenhum nome de template vem do usuário.
- `sections_order` (CSV) e `sections[]` (checkboxes) passam por
  `formOrder()` + `normalizeSections()`: duplicatas, IDs desconhecidos e listas
  maiores que a allowlist são recusados.
- O `editorial` continua exigindo `cms_home_content`; `has_3d` só liga WebGL
  se `hero`/`portfolio`/`tools` estiverem ativos na lista efetiva.
- `theme-section-builder.js` usa só DOM (`insertBefore`, `classList`,
  `textContent`): sem `fetch`, `eval`, `innerHTML`, `document.write` ou upload.
  Sem JavaScript o formulário continua funcionando (ordem renderizada no
  servidor + checkboxes).
- A fita da prévia é gerada no servidor com `htmlspecialchars`, dentro da mesma
  CSP `default-src 'none'` da frente C. Nenhum `|raw`, `<script>` ou estilo
  novo vindo de dados do usuário.

## Arquivos

`dev/system/library/theme/section_registry.php` (novo), `theme_package.php`,
`theme_snapshot.php`, `theme_compare_view.php`, `dev/system/framework.php`,
`dev/catalog/controller/startup/theme.php`, `dev/catalog/controller/common/home.php`,
`dev/admin/controller/design/theme.php`, `theme_form.twig`,
`theme-section-builder.js` (novo), `theme-condition-builder.css`, 3 idiomas.

Intocados: `common/home.twig`, `PageLayout`, `cms/slot.php`, models,
`schema.sql`, manifestos, runtime Three.js e BDREditor.
