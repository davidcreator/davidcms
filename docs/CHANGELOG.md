# Changelog

## Office/Canvas fidelity phase 2 — 2026-10-25

- DOCX: listas simples, estilos de título, cabeçalho de tabela e múltiplos parágrafos por célula.
- XLSX: até cinco abas visíveis, nomeadas e convertidas em seções/tabelas v2.
- PPTX: bullets e tabelas em shapes/graphic frames, mantendo separação de slides.
- Canvas IMSCC: respeita a ordem das páginas HTML do manifesto quando disponível.
- Sem novos uploads, raízes, endpoints, dependências ou alterações de banco.

## Fase 3 — mídias locais já cadastradas
- Integra picker somente leitura de áudio local existente em CMS > Páginas, respeitando ACL `modify blog/blog` e `modify cms/page`.
- Adiciona seleção e arrastar/soltar de itens de áudio locais validados; mantém o picker de arquivos desabilitado.
- Sem upload/exclusão, novo endpoint, migração ou alteração no fluxo de salvamento. Vídeo permanece em URL HTTPS permitida.
- Inclui checker estático específico para a integração e seus limites de ACL.
