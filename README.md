# BDREditor — melhoria de fidelidade Office/Canvas (Fase 2)

Base `main` conferida em modo somente leitura em 25/10/2026: `175da409c56ce65c665bc647060b581633af8caf`.

Esta fase aprimora o conversor local da Fase 1, sem alterar o fluxo CMS de uma tela ou o formato v2:

- **DOCX:** preserva listas numeradas/bullets simples, títulos por estilo, ênfase inline, hyperlinks HTTPS seguros e texto/tabelas; detecta cabeçalho de tabela. Listas aninhadas achatam.
- **XLSX:** importa até cinco abas visíveis como seções e tabelas; cada aba continua limitada a 20 × 12. Fórmulas são omitidas, células mescladas achatadas e estilos/gráficos não transferidos.
- **PPTX:** preserva texto por shape, placeholders de título, bullets simples e tabelas de slides (até 20 × 12). Animações, layout e mídia incorporada não são transferidos.
- **Canvas IMSCC:** usa a ordem de páginas HTML indicada pelo manifesto quando possível, com fallback determinístico. Não recria módulos, quizzes, notas ou relações LMS.
- Mantém prévia do rascunho, importação por seletor/arrastar, limites ZIP, allowlist HTML, mídia apenas por referência e salvamento manual.

## Mídia e segurança

A conversão é local ao navegador, sem upload ou chamadas de rede. Áudio só pode apontar para arquivos já existentes na biblioteca permitida (`view/audio/blog/`, MP3/OGG/WAV). Vídeo só é aceito por URL HTTPS de host permitido pelo editor. Mídia incorporada em Office não é extraída. Scripts, fórmulas Excel, estilos livres e conteúdo ativo continuam fora do escopo.

## Testes

```sh
node tools/e2e/check_single_page_ribbon.cjs
node tools/e2e/check_preview_import.cjs
node --check dev/admin/view/js/blog-block-import.js
node --check dev/admin/view/js/blog-block-editor.js
```

Os testes são estáticos/contratuais. PHP e navegador automatizado não estão disponíveis neste ambiente; validação em CMS real e arquivos de fixture ainda são necessários. Sem escritas GitHub, banco, staging/produção, migrations ou deploy.
