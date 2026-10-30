# F9-58 — Bloco Button: inspetor e toolbar

## Alterações

- O inspetor de `core/button` agora inclui edição rich-text do rótulo, sincronizada com o conteúdo no canvas e preservando as marcas inline.
- O destino do botão continua editável e aceita URL ou caminho local. O campo usa texto (em vez da validação nativa restritiva de `input[type=url]`) e limite de 2048 caracteres; a validação segura existente do destino permanece responsável por aceitar/rejeitar o valor.
- A aba **Personalizar** identifica os controles como estilo do botão; toolbar e ações do inspetor identificam as ações do bloco. O botão entra no padrão de rótulos abaixo dos ícones definido na F9-57; ações com ícones convencionais continuam com tooltip de nome e descrição.
- Os novos textos estão localizados em português, inglês e espanhol. O modelo de dados do botão (`url` + conteúdo textual) e as transformações já existentes foram preservados.

## Validação

- `node --check` passou.
- A bateria estática abrangente foi reexecutada: **128 verificações aprovadas**, com cobertura dos 21 tipos principais e 4 tipos aninhados; quatro verificações adicionais cobrem o editor do rótulo, destino local/URL, estilo/ações e toolbar do botão.
- Parser profundo `tinycss2` sem erros de CSS; CRC do ZIP verificado.
- Sem navegador ou runtime PHP disponível, não foi possível validar interações reais, foco/teclado, hover visual, responsividade ou submissão nos fluxos Pages/Blog. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py` (bateria estática ampliada para incluir F9-58)
- `docs/F9-58-Bloco-Button-Inspetor-e-Toolbar.md`
- Documentação F9-52 a F9-57 para contexto das etapas anteriores.
