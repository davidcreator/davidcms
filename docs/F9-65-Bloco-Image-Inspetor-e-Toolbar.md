# F9-65 — Bloco Image: inspetor e toolbar

## Entrega

- `core/image` mantém no inspetor os campos de caminho da imagem, texto alternativo, título, legenda, alinhamento, tamanho e tamanho para celular.
- O painel de conteúdo agora tem título e orientação de acessibilidade em português, inglês e espanhol. A instrução esclarece que texto alternativo pode ficar vazio para imagem decorativa.
- A aba **Personalizar** identifica explicitamente os controles de aparência da imagem.
- A toolbar contextual de Image segue a convenção visual de rótulos abaixo dos ícones; quando o seletor de mídia está habilitado, oferece ação para selecionar/trocar a imagem diretamente.
- A ação de mídia vincula o editor ao bloco acionado antes de abrir o seletor, para aplicar a seleção ao Image correto. Reutiliza o evento/fluxo existente e não altera os fluxos independentes de Blog e Pages.
- Validação de caminho local, atributos, limites e renderização existentes foram preservados.

## Verificações

- `node --check dev/admin/view/js/blog-block-editor.js`.
- Probe Node do schema aceitou um `core/image` com atributos válidos.
- Bateria estática: **161 verificações aprovadas**.
- Smoke tests de URL: **9 aprovados**.
- Análise CSS com `tinycss2`: sem erros de parse.
- Não houve teste de navegador nem teste de runtime PHP; não foram alterados backend, banco, migrações, controller, GitHub ou deploy.

## Arquivos completos no pacote

- Editor JS e CSS do admin.
- Validação e renderer PHP compartilhados (incluídos sem alterações específicas desta etapa).
- Bateria estática, smoke test e documentação F9-52 a F9-65.
