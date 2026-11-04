# KAZTheme-Editor — guia rápido

## Onde fica

Admin → Design → Temas → editar um tema. O card **KAZTheme-Editor** aparece depois do editor de templates, **dentro do formulário principal**, antes de fechar o `<form>`. O card de pacote de tema continua fora do formulário, como antes.

## Primeiro uso

1. Abra o tema. O canvas já vem com cinco camadas derivadas do config efetivo: `kaz-primary`, `kaz-surface`, `kaz-title`, `kaz-section` e `kaz-component`.
2. Clique numa camada no canvas ou na lista à esquerda para selecioná-la. O inspector à direita mostra X, Y, largura, altura, cor, raio, fonte, texto, tipo, papel no tema e ID allowlistado.
3. Arraste a camada no canvas para mover; arraste o quadrado azul no canto inferior direito para redimensionar. Tudo com snap na grade de 8 px.
4. Use **+ Retângulo / Texto / Botão / Cartão / Seção** para criar camadas. **Duplicar**, **Excluir**, **↑**, **↓**, **Ocultar** e **Bloquear** agem sobre a seleção.
5. **Desfazer** / **Refazer** ou `Ctrl+Z` / `Ctrl+Shift+Z`. Outras teclas: `Ctrl+D` duplica, `Delete` exclui, setas movem (com `Shift`, 32 px). Atalhos são ignorados enquanto você digita num campo.

## Aplicar ao tema

Defina o **Papel no tema** da camada:

- `primary` ou `surface` → a cor da camada é escrita nos campos `primary` / `surface`.
- `section` → preencha **ID allowlistado** com um ID real de seção (por exemplo `hero`); a camada marca `sections[]` e entra em `sections_order`.
- `component` → preencha **ID allowlistado** com um ID real de componente; a camada marca `components[<id>][enabled]`.

Abrir a tela **não muda nada** no formulário: a propagação só acontece quando você edita cor/papel, clica em **Aplicar ao tema** ou importa uma cena. Seções e componentes são marcados sem desmarcar os já salvos. Clique em **Aplicar ao tema** e depois **salve o tema normalmente**. A validação do servidor (`ThemePackage::validateConfig()`, `SectionRegistry::formOrder()`, `ComponentRegistry::normalize()`) continua soberana: IDs desconhecidos são ignorados pelo formulário, cores fora de `#[0-9a-fA-F]{6}` não são aceitas.

## Guardar e retomar a cena

- **Exportar cena** serializa `kaztheme-scene/v1` no textarea. Copie e guarde onde quiser.
- **Importar cena** cola o JSON no textarea e valida o contrato. JSON malformado, enum desconhecido, cor inválida, ID duplicado ou limite excedido são **rejeitados sem alterar o estado**.
- A cena é local ao navegador/textarea, como os *style presets* do BDREditor. Ela **não** vira chave nova do config nesta frente; veja a limitação na referência.

## Arquivos para editar (estrutura fácil)

| Quer mudar... | Arquivo |
| --- | --- |
| Tipos de camada, validação, atalhos, histórico | `dev/admin/view/js/kaz-theme-editor.js` |
| Cores, layout de 3 colunas, responsivo | `dev/admin/view/css/kaz-theme-editor.css` |
| Rótulos e campos do inspector | `dev/admin/view/template/design/kaz_theme_editor.twig` |
| Cena inicial derivada do config, assets | `dev/admin/controller/design/theme.php` |
| Contrato e limites | `docs/KAZTheme-Editor.md` |

## Limites que continuam valendo

O BDREditor (F9-103) não foi tocado. F9-27 permanece `BLOCKED_NOT_RUN` com `release_approved:false`. A limitação de hreflang da frente N permanece. Não houve migration, schema, coluna, `custom_js`, upload, WebGL, Three.js, chave de tradução nova, escrita remota ou deploy. CI verde/health coerente não é release aprovada.
