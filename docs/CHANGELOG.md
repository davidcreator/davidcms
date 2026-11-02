# Changelog

## F11-10 — Correção dos links quebrados no `docs-links` (2026-11-02)
- O pin de `lychee v0.15.0` (F11-08) fez o `docs-links` concluir em 6,5 min e expôs `exit code 2` em dois runs: a `@v2` tem `fail: true` por default, enquanto a `@v1` não falhava em links quebrados.
- Diagnóstico executado com o **binário oficial lychee 0.15.0** e os `args` exatos do workflow numa réplica de 2.476 arquivos: **16 erros** (2 links locais, 6×429 GNU, 1×429 Instagram, 1×403 Redbubble, 2×404 e 2×500 e 2 redirects em vendor). A estimativa anterior de 41 links relativos ausentes era artefato de réplica incompleta e foi retificada.
- Correção sem desligar `fail`: criado `docs/README.md` com índice real; 3 exclusões para domínios mortos de vendor (`watchersnet.de`, `code.google.com`, `awio.iljmp.com`); `--accept '100..=103,200..=299,403,429'` para rate limiting/anti-bot. `fail`/`failIfEmpty=true`, `timeout-minutes: 20` e o pin permanecem.
- `verify-ci-workflow.sh` ganhou 4 checks e aposentou 2 obsoletos (28 PASS / 0 FAIL); 4 manifestos de integridade reemitidos com hashes do estado final.
- Validação local com o binário do CI: `exit 0 / 0 erros / 522 OK`. Sem alteração de código PHP/JS, migration, banco, staging, deploy ou escrita no GitHub.

## F11-08 — Hotfix de performance do `docs-links` (2026-11-02)
- Diagnóstico: após F11-04 (`lychee-action@v1` → `@v2`), o `docs-links` passou de 358–369 s (~6 min, 4 runs) para 14,0 / 15,5 min em dois runs cancelados por push e ≥8,3 min no terceiro, nenhum com `success`; só +7 arquivos `.md` entraram no repo (297→304). A lentidão é observada, mas a causa interna do binário 0.24.2 não foi comprovada e pode incluir fator transitório de rede.
- A v1 fixava o binário `lychee 0.15.0`; a v2.9.0 usa `v0.24.2`. Os defaults de `--timeout`/`--max-retries`/`--max-concurrency` são iguais nas duas versões, e os logs do job exigem admin (403) — a causa interna do 0.24.2 **não foi comprovada**.
- Correção determinística: `lycheeVersion: v0.15.0` no `with:`, mantendo a action `@v2` (Node 24, sem avisos), `fail`/`failIfEmpty=true`, as 5 exclusões e sem `--accept`. Sem downgrade da action, sem desligar o job e sem alterar `timeout-minutes: 20`.
- `verify-ci-workflow.sh` ganhou 4 checks de contrato; `CHECKSUMS.txt`, `CHECKSUMS-F11-04.txt` e `CHECKSUMS-F11-05.txt` reemitidos com hashes do estado final. `CHECKSUMS-F11-07.txt` permanece intacto.
- Sem alteração de código PHP/JS, migration, banco, staging, deploy ou escrita no GitHub.

## F11-04 — Modernização e endurecimento do CI (2026-11-02)
- ADR 0082: actions atualizadas para as majors com runtime Node 24 (`checkout@v7`, `setup-node@v7`, `cache@v6`, `setup-php@v2`, `lychee-action@v2`), eliminando os 8 avisos por run.
- Runner pinado em `ubuntu-24.04` durante a janela de migração do `ubuntu-latest` para Ubuntu 26.04 (19/10–19/11/2026); revisão para 26.04 agendada após um run verde.
- Novo job `repo-guards` executa `verify-deps-layout.sh`, `verify-checksums.sh` e o novo `verify-ci-workflow.sh` em todo push/PR.
- `node-deps` passa a validar Node 20 + 22 em matrix (`fail-fast: false`), com cache npm explícito por versão; `concurrency` e `timeout-minutes` em todos os jobs.
- Documentação, PR template, regras e índice de ADRs atualizados. Sem alteração de código PHP/JS, migration, banco, staging, deploy ou escrita no GitHub.

## F11-01 — CI e dependências em `dev/system/storage/` (2026-11-02)
- Move as dependências Node/Playwright 1.64.1 da raiz para `dev/system/storage/` com `package.json` + `package-lock.json` versionados; `node_modules/` ignorado (ADR 0081).
- Corrige o `php-lint` que passava verde sem lintar nada (`defaults.run.working-directory` global) e o erro do PHPStan em `block_document.php:122` (`$version ?? 0` redundante).
- Novo job `node-deps` no CI; `php-lint` e `docs-links` passam a rodar na raiz e a excluir `node_modules/`.
- `build_prod.sh` e `check_producao.sh` passam a excluir/verificar as deps Node, preservando a build runtime-only (ADR 0039).
- Runners E2E e bateria de staging resolvem Playwright por `createRequire`; `staging.ps1` não copia mais `node_modules` para o clone descartável.
- Documentação, regras dos agentes, índice de ADRs e PR template atualizados. Sem migration, banco, staging, deploy ou escrita no GitHub.

## Cena 5 — ícones 3D opcionais de Ferramentas (2026-10-27)
- Implementa ADR 0077 como camada decorativa para até quatro cards, com movimento leve e resposta a foco/ponteiro.
- Reutiliza o SceneRunner e o gateway allowlisted; usa imagens same-origin existentes ou monogramas quando não há capa.
- Adiciona o toggle `three_tools_enabled` desligado por padrão no Admin e em três idiomas; sem assets binários ou migration.
- Mantém cards, texto e links HTML como conteúdo e fallback.

## Cena 3 — galeria 3D opcional no Portfólio (2026-10-27)
- Implementa ADR 0076 antecipada por autorização: overlay decorativo no SceneRunner; grade, anchors e tilt CSS permanecem como fallback acessível.
- Adiciona toggle desligado por padrão e limite 1–12 (default 12) no formulário existente, com validação/allowlist e textos nos três idiomas.
- Carrega apenas capas same-origin ao aproximar da viewport; compõe atlas/mesh único e preserva fallback CSS em falha/reduced-motion/WebGL indisponível.
- Registra `portfolio-3d.js` no `main.js` e no AssetManifest; sem migration.

## Cena 2 — logo GLB opcional do Hero (2026-10-26)
- Publica `logo-da.glb` pelo manifesto do System e gateway Catalog, com MIME `model/gltf-binary`; o caminho é fixo e allowlisted.
- Adiciona `GLTFLoader` r186 e dependências à allowlist, importadas dinamicamente somente quando o modelo está configurado.
- Integra rig de luz e ajuste do modelo ao Hero, mantendo partículas em falha/ausência do modelo.
- Registra ADR 0075; o GLB procedural atual foi autorizado como asset vigente da Cena 2. Um export futuro do Blender é melhoria opcional, sem bloquear o andamento.
- Sem setting novo, alteração de schema ou migration.

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
