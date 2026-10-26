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
- Importar MP3/OGG/WAV pelo comando de documentos agora apresenta orientação localizada para usar o bloco Áudio e a biblioteca existente.

## Upload local seguro de áudio e vídeo
- Biblioteca Blog recebe uploads de MP3/OGG/WAV até 20 MiB e MP4/WebM/OGV até 100 MiB com MIME real, `is_uploaded_file`, nome aleatório e raízes dedicadas.
- Endpoints de upload POST protegidos por CSRF e ACL existente `modify blog/blog`; listagens com mesma ACL. Sem exclusão.
- Seletor e upload disponíveis nos editores Blog e CMS > Páginas; CMS também mantém gate `modify cms/page`.
- Adiciona bloco v2 `core/video_file` e saída pública HTML5 `<video controls>` para ativos na raiz local de vídeo; incorpora a raiz ao renderer de Blog e CMS.
- Importador de documentos continua separado e encaminha tentativas de importar áudio/vídeo aos blocos correspondentes.
- Sem migration, alteração de ACL, configuração de servidor, GitHub write, staging ou deploy.

## Ampliação — thumbs 3D declarativas (2026-10-26)
- Adicionado `core/threejs_thumb` ao BDREditor de CMS > Páginas e Blog, com configuração de cena JSON e rótulo acessível no inspetor.
- Incluído runtime Three.js local/self-hosted (0.186.1), renderer público condicional e validação estrita de cena; sem código arbitrário, CDN, modelos ou assets externos.
- Incluídos textos PT-BR, EN-US e ES-EU, CSS administrativo/público e checker estático específico.
- Ver `docs/THREEJS_THUMB.md` para esquema, limites e verificações.

## Correção — inserir formas dentro da cena Three.js (2026-10-26)
- O Inspetor do bloco agora tem ações dedicadas para adicionar/remover formas e luzes e editar tipo, cor, animação e intensidade.
- Inclusão respeita o máximo de 12 formas/4 luzes e preserva pelo menos uma de cada; novas formas recebem posições distintas para ficarem visíveis.
- Mantido o JSON avançado, com validação antes de gravar cada alteração, undo/redo e traduções PT-BR/EN-US/ES-EU.
