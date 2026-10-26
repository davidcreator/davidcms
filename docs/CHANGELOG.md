# Changelog

## SceneRunner compartilhado para Three.js e thumbs declarativas (2026-10-26)
- Unifica renderer, resize, lazy-init, visibilidade, reduced-motion, render loop e descarte no `scene-runner.js` do System.
- Adapta thumbs declarativas por `thumbnail-runner.js`; mantém `custom_js` no iframe sandbox isolado.
- Converte Hero/404 para factories do engine compartilhado, preservando configuração e fallback sem WebGL.
- Registra ADR 0074 e atualiza documentação do runtime e a descrição correta da Prévia Admin.
- Sem migration, deploy, escrita GitHub ou execução de testes mutáveis.

## Engine Three.js centralizada no System (2026-10-26)
- Move vendor, cenas nativas e runtime de thumbs para a fonte única `dev/system/library/three/runtime/`.
- Adiciona `AssetManifest` no System e rota pública Catalog allowlisted `/catalog/three-runtime/`; o diretório `system/` continua negado por HTTP.
- Home/404, Blog/CMS e Admin usam a base do manifesto; imports ESM relativos preservam o mesmo caminho público e os módulos deixam de ser duplicados em Catalog.
- Mantém CSS de apresentação no Catalog, fallback e sandbox; sem CDN, migration ou escrita de conteúdo/settings.
- Registrada a decisão na ADR 0073.

## Runner read-only para ambientes persistentes (2026-10-26)
- Incluído Playwright E2E somente GET para validar settings persistidos, toggles renderizados, fallback, import map e carregamento de assets sem salvar ou modificar estado.
- Documentado que o runner mutável é proibido em instalações compartilhadas/persistentes (incluindo ambiente local que reúna produção, desenvolvimento, testes e documentação).

## QA funcional das configurações nativas Three.js (2026-10-26)
- Adicionado runner Playwright opcional para testar UI/salvamento do Admin, enable/disable do Hero e 404, fallback HTML e carregamento do import map self-hosted.
- Runner aplica a configuração de teste apenas em instalação descartável, guarda os valores originais e tenta restaurá-los sempre no `finally`; não aceita credenciais via CLI.
- Adicionado contrato estático para o runner; execução autenticada não é possível sem ambiente local autorizado.

## E2E autenticado das cenas nativas (2026-10-26)
- Incluído runner Playwright opcional para validar settings Admin, persistência, toggles das cenas home/404 e import map/assets locais.
- O runner exige `E2E_CONFIRM_DISPOSABLE=1`, storage state local, origens iguais e restaura as configurações originais em `finally`.
- Atualizado o contrato estático do runner; execução funcional permanece dependente de ambiente CMS descartável autenticado.

## Three.js nativo gerenciado pelo System/Admin/Catalog (2026-10-26)
- Centraliza limites/defaults de Hero/404 em `system/library/three/scene_config.php`, registrado pelo framework e consumido pelas aplicações sem instanciar serviço em controller.
- Adiciona controles na tela `setting/setting` para habilitar cenas e ajustar partículas/cor/velocidade, usando a tabela de settings existente, ACL/CSRF existentes e sem migration.
- Catalog aplica configurações validadas em home/404 e mantém fallback quando a cena está desativada.
- Import map do site e thumb usa Three.js local; Admin Blog/CMS busca assets de thumb de `/catalog/view/`, corrigindo os 404.
- Documentação do status e checker estático adicionados; E2E autenticado continua pendente.

## Correção — thumb renderizada na prévia do post (2026-10-26)
- O cartão do bloco no canvas e o modo **Prévia** do editor Blog/CMS agora criam o stage com a configuração da cena e iniciam o runtime Three.js local, em vez de mostrar apenas um rótulo estático.
- A prévia administrativa renderiza somente a cena declarativa; `custom_js` não é executado no editor e permanece exclusivo da thumb pública em iframe sandbox.
- Textareas e editor de código podem ser redimensionados na largura e altura, limitados ao espaço disponível do formulário.
- Atualizados os textos nos três idiomas, versionamento dos assets administrativos e checkers estáticos.

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

## Ampliação — JavaScript opcional para thumbs (2026-10-26)
- Adicionado campo `custom_js` opcional no envelope v2 (máx. 8 KiB), com compatibilidade para thumbs anteriores.
- Scripts isolados em iframe sandbox de origem opaca e CSP sem rede/recursos externos; interface fornece THREE, scene, camera, renderer, canvas e callback onFrame.
- Atenção: código confiável somente; loop infinito ainda pode travar a thumb/aba. Sem acesso ao DOM/cookies do CMS.

- Corrigida a distribuição do runtime: Three.js 0.186.1 importa também `three.core.js`; os dois módulos locais agora acompanham o ZIP.
- URLs dos assets do editor/runtime recebem versionamento específico para evitar que cache mantenha a interface anterior.

## Correção de usabilidade — edição direta do bloco Three.js (2026-10-26)
- Adicionado botão visível **Editar thumb e script** no próprio cartão do canvas; abrir o Inspetor lateral deixa de ser pré-requisito.
- Painel inline reúne formas/luzes e textarea JS com ação **Excluir script** (desfazível), mantendo a edição no envelope v2 e o salvamento do formulário.
- O campo JS usa commit sem re-render ao perder foco para que os controles próximos continuem acionáveis.

## Correção — execução e diagnóstico do script público (2026-10-26)
- O módulo Three.js da iframe passa a importar diretamente os módulos locais por data URL, sem depender de import map no frame opaco. Testado por importação ESM do pacote local em Node.
- A thumb pública agora reporta carregamento, inicialização e erros de módulo/CSP/código com texto seguro; mantém o fallback acessível até sucesso.
- A ajuda especifica JavaScript puro (sem tags `<script>`) e o runtime público ganhou novo cache-buster.

## Ferramenta E2E CMS opcional (2026-10-26)
- Incluído runner Playwright com sessão local, edição/salvamento/reabertura e verificação da execução de `onFrame` no iframe público; exige confirmação explícita de página descartável e não registra credenciais.
