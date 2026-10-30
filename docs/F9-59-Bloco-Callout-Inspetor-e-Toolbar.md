# F9-59 — Bloco Callout: inspetor e toolbar

## Alterações

- O inspetor de `core/callout` agora inclui edição rich-text do conteúdo, sincronizada com o canvas e preservando as marcas inline.
- O seletor de tom do aviso (informação, sucesso, atenção ou crítico) foi preservado no inspetor; a aba **Personalizar** identifica os controles de aparência como estilo do aviso.
- A toolbar contextual e as ações do inspetor exibem rótulos claros. O bloco segue a convenção estabelecida na F9-57: rótulos aparentes abaixo dos ícones; ferramentas com ícones convencionais podem continuar icon-only e mostram nome/descrição no hover.
- Textos do inspetor localizados em português, inglês e espanhol. Sem mudanças no schema do Callout, em validação de tom ou nos fluxos independentes de Pages e Blog.

## Validação

- `node --check` e bateria estática ampliada: **131 verificações aprovadas**, cobrindo 21 blocos principais, 4 tipos aninhados e verificações direcionadas para o Callout (editor rich-text, manutenção do campo de tom, aparência e ações nomeadas).
- Análise profunda de CSS com `tinycss2` sem erros; ZIP validado com `unzip -t`.
- Não houve navegador/runtime PHP disponível; não afirmar testes interativos ou visuais reais. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py` (bateria estática ampliada para Callout)
- `docs/F9-59-Bloco-Callout-Inspetor-e-Toolbar.md`
- Documentação F9-52 a F9-58 para contexto das etapas anteriores.
