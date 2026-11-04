# KAZTheme-Editor — guia rápido

## Onde fica

Admin → Design → Temas → editar um tema. O card **KAZTheme-Editor** aparece depois do editor de templates, **dentro do formulário principal**. O card de pacote de tema continua fora do formulário, como antes.

## Primeiro uso

1. Abra o tema. Se ele já tiver uma cena salva, o card mostra **"Cena salva neste tema"** e o canvas a reproduz. Temas mais antigos mostram **"Cena derivada do config"** com cinco camadas geradas a partir das cores, seções e componentes — basta salvar uma vez para gravar a cena própria.
2. Clique numa camada no canvas ou na lista à esquerda para selecioná-la. O inspector à direita mostra X, Y, largura, altura, cor, raio, fonte, texto, tipo, papel no tema e ID allowlistado.
3. Arraste a camada no canvas para mover; arraste o quadrado azul no canto inferior direito para redimensionar. Tudo com snap na grade de 8 px.
4. Use **+ Retângulo / Texto / Botão / Cartão / Seção** para criar camadas. **Duplicar**, **Excluir**, **↑**, **↓**, **Ocultar** e **Bloquear** agem sobre a seleção.
5. Na **Biblioteca de blocos**, clique em **+ Hero / + Cartão / + Faixa / + Título / + Galeria / + CTA** para inserir um conjunto pronto de 2–3 camadas. Elas entram no canvas, na árvore, no histórico e no campo salvo com o tema. O bloco não muda o formulário sozinho.
6. **Desfazer** / **Refazer** ou `Ctrl+Z` / `Ctrl+Shift+Z`. Outras teclas: `Ctrl+D` duplica, `Delete` exclui, setas movem (com `Shift`, 32 px). Atalhos são ignorados enquanto você digita num campo.

## Alterações não salvas

O selo **Alterações não salvas** aparece no cabeçalho do card assim que a cena
difere da última gravação. Ele **não aparece ao abrir a página**, desaparece ao
salvar o tema e também some se você desfazer até voltar ao estado carregado.
É apenas um lembrete local: quem valida e grava continua sendo o servidor.

## Salvar a cena

O textarea **"Cena kaztheme-scene/v1 — campo salvo com o tema"** é um campo real do formulário (`name="kaz_scene_json"`). O editor o mantém sincronizado automaticamente a cada alteração e também no momento do submit — inclusive no botão de comparar/prévia.

- **Salvar o tema** grava a cena na chave `kaz_scene` do config, validada no servidor.
- Cena inválida (JSON quebrado, enum desconhecido, cor fora do padrão, ID duplicado, limite excedido) **impede o salvamento** com a mensagem de validação já existente; nada é gravado.
- **Exportar cena** / **Importar cena** continuam funcionando offline para copiar, colar e versionar o JSON.
- **Exportar pacote de tema** inclui a cena; **importar pacote** a revalida pelo checksum e pelo contrato. Child-themes substituem a cena integralmente, sem mesclar pela metade.

## Aplicar ao tema

Defina o **Papel no tema** da camada:

- `primary` ou `surface` → a cor da camada é escrita nos campos `primary` / `surface`.
- `section` → preencha **ID allowlistado** com um ID real de seção (por exemplo `hero`); a camada marca `sections[]` e entra em `sections_order`.
- `component` → preencha **ID allowlistado** com um ID real de componente; a camada marca `components[<id>][enabled]`.

Abrir a tela **não muda nada** no formulário: a propagação só acontece quando você edita cor/papel, clica em **Aplicar ao tema** ou importa uma cena. Seções e componentes são marcados sem desmarcar os já salvos. A validação do servidor (`ThemePackage::validateConfig()`, `SectionRegistry::formOrder()`, `ComponentRegistry::normalize()`) continua soberana.

## Arquivos para editar (estrutura fácil)

| Quer mudar... | Arquivo |
| --- | --- |
| Tipos de camada, validação cliente, atalhos, histórico, blocos e selo | `dev/admin/view/js/kaz-theme-editor.js` |
| Cores, layout de 3 colunas, responsivo | `dev/admin/view/css/kaz-theme-editor.css` |
| Rótulos, campos do inspector e note de estado | `dev/admin/view/template/design/kaz_theme_editor.twig` |
| Leitura/gravação da cena e assets | `dev/admin/controller/design/theme.php` |
| Contrato servidor, allowlist e normalização | `dev/system/library/theme/theme_package.php` |
| Contrato e limites | `docs/KAZTheme-Editor.md`, `docs/KAZTheme-Editor-Cena-Persistente.md` |
| Presets dos blocos | `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` |

## Limites que continuam valendo

A cena ainda **não é renderizada no site público** — ela é dado de edição/versionamento; o visual do visitante vem dos campos allowlistados. O BDREditor (F9-103) não foi tocado. F9-27 permanece `BLOCKED_NOT_RUN` com `release_approved:false`. A limitação de hreflang da frente N permanece. Não houve migration, schema, coluna, `custom_js`, upload, WebGL, Three.js, chave de tradução nova, escrita remota ou deploy. CI verde/health coerente não é release aprovada.
