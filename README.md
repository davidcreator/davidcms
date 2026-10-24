# F9-15 — Renderer público Blog em blocos

Pacote incremental: PHP renderer allowlisted, validação estendida, fallback catalog compatível, CSS responsivo, translations, fixtures e ADR.

- GitHub confirmado: `https://github.com/davidcreator/davidcms`, branch `main`.
- HEAD base: `0162822d3bf98ce100780b17cf0c88ff22ee832f`.
- F9-14 assets/contrato/ADR/gate principais conferidos contra o commit remoto.
- Prévia de estilo responsivo: `tools/preview/f915-renderer-preview.html` (markup demonstrativo, sem chamadas externas).
- Gate estático: `bash tools/e2e/e2e_f915.sh --check`.
- Runner funcional PHP incluído, **não executado**.

Se a sidecar F9-12 ainda não existe, o catálogo segue o caminho legado atual. Com linha de blocos inválida, a publicação do conteúdo falha fechada em vez de cair silenciosamente para HTML cru. Nenhum banco, GitHub ou conteúdo foi alterado nesta entrega.
