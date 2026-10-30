# F9-60 — More Informations (`core/details`), preview e tamanhos de tela

## Bloco More Informations

- O inspetor de `core/details` passa a oferecer edição rich-text do conteúdo expandido, sincronizada com o canvas e preservando formatação inline.
- O campo de resumo/título da seção expansível continua presente. Os controles de aparência e ações estruturais recebem identificação contextual.
- Novos rótulos e instruções localizados em português, inglês e espanhol. O schema e a forma nativa de `<details>/<summary>` foram preservados.

## Correção do preview e dos tamanhos

- Identificada uma cascata de CSS que anulava o preview responsivo: `.rb-preview-canvas` forçava `max-width:none!important`, e estilos do host Pages (`.rb-editor-host` / `.bdre-page-editor`) declaravam `max-width:none`, sobrepondo os breakpoints de Tablet e Mobile.
- Removido o `!important` conflitante e adicionadas regras mais específicas para Tablet (768 px) e Mobile (390 px) nos hosts Pages/Blog e no layout fullscreen. Desktop volta a usar a largura disponível.
- O modo de pré-visualização usa os mesmos limites responsivos.
- A ação de seleção de tamanho aceita somente `desktop`, `tablet` ou `mobile`, atualiza `data-preview` e sincroniza `aria-pressed` nos controles, sem reconstruir o canvas.

## Validação e limites

- `node --check` passou.
- A bateria estática foi ampliada e passou **139 verificações**, incluindo cobertura completa dos blocos, edição do More Informations, manutenção do resumo, ações nomeadas, seletor de tamanho e regras CSS responsivas para host/fullscreen.
- Análise profunda do CSS com `tinycss2` sem erros e validação CRC do ZIP.
- Sem navegador/runtime PHP disponível: o comportamento visual e cliques reais ainda precisam ser confirmados no ambiente da aplicação. Não foram alterados controllers, banco, migrações, rotas, GitHub ou deploy.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py` (bateria ampliada até F9-60)
- `docs/F9-60-Bloco-More-Informations-e-Preview.md`
- Documentação F9-52 a F9-59 para contexto das etapas anteriores.
