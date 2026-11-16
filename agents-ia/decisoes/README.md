# 🗂️ decisoes — Registro de Decisões Arquiteturais (ADR)

Toda decisão relevante do ReamurCMS fica registrada aqui, em um arquivo por decisão, com numeração sequencial. **Nenhuma decisão arquitetural vive só na memória ou no chat.**

## Quando criar um ADR

- Escolha entre alternativas arquiteturais (ex.: "blog dentro do `cms/` × módulo próprio");
- Exceção a uma regra de `agents-ia/regras/`;
- Renúncia de escopo com impacto técnico (ex.: "blog externo por ora");
- Mudança de convenção, de nome, de estrutura de pastas;
- Mudança de assinatura pública do núcleo (quebra de compatibilidade).

## Template

Copiar para o novo arquivo `NNNN-titulo-curto.md`:

```markdown
# ADR NNNN — Título da decisão

| | |
| --- | --- |
| **Status** | proposta · aceita · rejeitada · substituída por ADR XXXX |
| **Data** | AAAA-MM-DD |
| **Agente proponente** | system / install / admin / catalog / orquestrador / humano |
| **Contexto** | (qual problema forçou a decisão — 3-6 linhas) |

## Decisão

(A opção escolhida, em 1-3 linhas objetivas.)

## Alternativas consideradas

1. **Alternativa A** — por que não;
2. **Alternativa B** — por que não.

## Consequências

- ✅ Positivas...
- ⚠️ Negativas / cuidados...

## Documentos atualizados

- `docs/...` · `agents-ia/regras/...`
```

## Índice

| ADR | Título | Status | Data |
| --- | --- | --- | --- |
| [0001](0001-idioma-padrao-en-us.md) | Idioma padrão do sistema: `en-us` | ✅ aceita | 2026-10-15 |
| [0002](0002-blog-hibrido.md) | Blog híbrido (externo no lançamento, interno depois) | ✅ aceita | 2026-10-15 |
| [0003](0003-ci-e-gitignore.md) | Correção do CI e do `.gitignore` | ✅ aceita | 2026-10-15 |
| [0004](0004-contratos-qualidade-e-modelos.md) | Contratos de fundação e infra de qualidade (L1–L10) | ✅ aceita | 2026-10-15 |
| [0048](0048-f8-04-sale-marketing-filtros-paginacao-csrf.md) | F8-04: sale/marketing, filtros, paginação e CSRF | ✅ aceita | 2026-10-22 |
| [0049](0049-f8-05-blog-media-library.md) | F8-05: biblioteca de mídia para capas do Blog | ✅ aceita | 2026-10-22 |
| [0072](0072-threejs-scenes-admin-system.md) | Configuração declarativa das cenas Three.js nativas via System e Admin | ✅ aceita | 2026-10-26 |
| [0073](0073-threejs-runtime-system-asset-gateway.md) | Runtime Three.js canônico no System com entrega allowlisted pelo Catalog | 🟡 aprovada para implementação incremental | 2026-10-26 |
| [0074](0074-threejs-shared-scene-runner.md) | Ciclo de vida compartilhado para cenas Three.js e thumbs declarativas | 🟡 aceita para implementação incremental | 2026-10-26 |
| [0075](0075-cena-2-hero-logo-glb-placeholder-procedural.md) | Cena 2 do Hero: logo GLB servido pela allowlist do runtime | ✅ implementada; GLB atual autorizado para continuidade | 2026-10-26 |
| [0076](0076-cena-3-etapa-2-galeria-3d-do-portfolio.md) | Cena 3 etapa 2 do Portfólio: galeria 3D opcional sobre a grade | 🟡 aceita para implementação incremental | 2026-10-27 |
| [0077](0077-cena-5-ferramentas-3d-flutuantes.md) | Cena 5: ícones 3D flutuantes das Ferramentas | ✅ aceita para implementação incremental | 2026-10-27 |
| [0078](0078-conteudo-editorial-rotas-catalog.md) | Conteúdo editorial CMS associado às rotas públicas do Catalog | ✅ aceita para implementação incremental | 2026-10-27 |
| [0079](0079-temas-catalog-admin.md) | Temas do Catalog como ferramenta Admin separada | 🟡 incremento local; pendente runtime/preview/editor Twig | 2026-10-27 |
| [0080](0080-temas-preview-sandboxed-templates.md) | Fontes Twig de tema com prévia isolada e publicação versionada | 🟡 incremento local | 2026-10-27 |
| [0081](0081-dependencias-node-em-system-storage-e-ci.md) | Dependências Node em `dev/system/storage/` e correção do CI | ✅ aceita | 2026-11-02 |
| [0082](0082-modernizacao-e-guards-do-ci.md) | Modernização e endurecimento do CI (actions Node 24, runner pinado, `repo-guards`) | ✅ aceita | 2026-11-02 |
| [0083](0083-kazeditor-canvas-pagina-real.md) | KAZEditor-Theme: canvas com a página real do rascunho (três larguras, sandbox) | ✅ aceita | 2026-11-12 |
| [0084](0084-kazeditor-apontar-e-editar.md) | KAZEditor-Theme: apontar e editar (marcadores de origem, estrutura, trecho selecionado) | ✅ aceita | 2026-11-12 |
| [0085](0085-kazeditor-tokens-ao-vivo.md) | KAZEditor-Theme: tokens ao vivo (aba Design, variáveis do tema, esquema claro/escuro, contraste) | ✅ aceita | 2026-11-12 |
| [0086](0086-kazeditor-selecionar-elemento.md) | KAZEditor-Theme: selecionar um elemento dentro do trecho (âncoras por elemento, edição de texto fixo); `--accent` segue a cor Primária | ✅ aceita | 2026-11-12 |
| [0087](0087-seguranca-acl-admin-e-sandbox-do-tema-publicado.md) | Segurança S1 + S2: a ACL do admin checa a rota que executa (`Action::parse()`); fonte de tema roda em Twig Sandbox também no site publicado (`ThemeSandbox`) | ✅ aceita | 2026-11-12 |
| [0088](0088-seguranca-modo-producao-e-erro-500.md) | Segurança S3 + S4: `REAMUR_ERROR_DISPLAY=false` desliga a exibição de erros de verdade; falha de tela responde 500 com página estática (`ErrorPage`), 404 fica só para URL que não corresponde a uma tela (`NotFoundException`) | ✅ aceita | 2026-11-12 |
| [0089](0089-seguranca-proxy-cdn-ip-do-visitante-e-cookie-secure.md) | Segurança S5: IP do visitante e HTTPS lidos em um ponto só (`Request::ip()` / `isHttps()`, `Security\ClientAddress`); cabeçalhos de proxy valem apenas com `REAMUR_TRUSTED_PROXIES` ou `REAMUR_TRUSTED_PROXY_HOPS` no `config.php`; cookie de sessão com `Secure` quando `HTTP_SERVER` é https | ✅ aceita | 2026-11-12 |
| [0090](0090-seguranca-ssrf-ip-conferido-e-url-crua.md) | Segurança S6 + S7: webhook e importador conectam só nos endereços que `UrlGuard::inspect()` conferiu (`CURLOPT_RESOLVE`, sem segunda consulta ao DNS); URL e caminho conferidos crus; IP só na forma canônica; importador com limite de tamanho durante o download | ✅ aceita | 2026-11-12 |
| [0091](0091-seguranca-instalador-fechado-pelo-estado-real.md) | Segurança S12: o instalador responde 403 quando existe `install.lock` ou um `config.php` do site (`Security\InstallSeal`), decidido antes de subir o núcleo; o passo 3 grava a trava junto com a instalação e desfaz o que gravou se falhar; limite de tempo em "Testar conexão"; aviso no painel enquanto a pasta `install/` existir | ✅ aceita | 2026-11-12 |
| [0092](0092-build-producao-sem-pacotes-de-desenvolvimento.md) | Segurança S10 + S11: `tools/build_prod.sh` entrega só os pacotes de `require` (`composer install --no-dev` sobre a cópia; veredito reprova PHPUnit / PHPStan em `prod/`); `symfony/filesystem` v7.4.23; CI prova a build (`tools/verify-build-prod.sh`) e roda `composer audit` como aviso | ✅ aceita | 2026-11-13 |
| [0093](0093-seguranca-politica-de-conteudo-aplicada-e-hsts.md) | Segurança S8: `Content-Security-Policy` aplicada e montada por requisição (`Security\Csp`): scripts só do site e de blocos com nonce, sem `eval`; confirmações do admin fora do HTML; exceção única para o editor antigo de posts; chaves `REAMUR_CSP_MODE` / `REAMUR_CSP_SOURCES` / `REAMUR_HSTS_MAX_AGE`; HSTS de 180 dias quando o site é https | ✅ aceita | 2026-11-13 |
| [0094](0094-seguranca-segredos-cifrados-no-banco.md) | Segurança S9: tokens do Mercado Pago, segredo do aplicativo, chave de assinatura, senha SMTP e segredos de webhook gravados cifrados (`Security\SecretBox`, AES-256-GCM, valor preso ao lugar); chave `REAMUR_SECRET_KEY` igual nos dois `config.php`, gerada pelo instalador; "Proteger segredos" em Configurações; valor que não abre conta como ausente e a notificação de pagamento responde 503; troca por `REAMUR_SECRET_KEY_OLD`; `rms_webhook.secret` em `VARCHAR(512)` | ✅ aceita | 2026-11-13 |
| [0095](0095-extensoes-base-pasta-manifesto-eventos.md) | Extensões EX-01: pasta `dev/extension/<código>/` com `extension.json` lido de forma estrita e classe `Extension`; desligada, nenhum código roda; tela *Extensões* (rota `extension/extension`) com ligar / desligar e formulário gerado; segredos cifrados (ADR 0094); eventos com prioridade e erro isolado no log; saída na página pública só por `PageOutput` (nonce); origens `csp` só com a extensão ligada e só no catálogo; chave `REAMUR_EXTENSIONS`; `check_producao.sh` check 19 | ✅ aceita | 2026-11-13 |
| [0096](0096-consentimento-de-cookies-e-extensoes-de-medicao.md) | Extensões EX-02: aviso de cookies do núcleo (recusar e aceitar com o mesmo peso, sem JavaScript, três idiomas) e tela *Preferências de cookies* (rota `common/consent`); chaves `consent` e `cookies` no `extension.json`; extensão que depende de aceitação não é carregada nem entra na política de conteúdo para o visitante que não aceitou; escolha no cookie `rms_consent`, sem tabela; retirada apaga os cookies declarados; aviso de formulário enviado sem dados pessoais; extensões Google Analytics 4, Google Tag Manager, Pixel da Meta e Tag do Pinterest, desligadas até alguém ligar | ✅ aceita | 2026-11-13 |
| [0097](0097-contrato-de-meio-de-pagamento.md) | Extensões EX-03: contrato de meio de pagamento em `system/library/payment/`: `Method` (pronto para cobrar? / crie a cobrança / este aviso é verdadeiro?), `Checkout` com as regras que valem para qualquer meio (chave do link, link pendente, aviso repetido, situação do pedido, histórico, evento de pedido pago) e `DbLedger` sobre `rms_mp_payment`; Mercado Pago passa a ser o primeiro meio, com os mesmos endereços, telas, tabelas, respostas e linhas de log; nenhum SQL; relatório do mesmo roteiro idêntico antes e depois | ✅ aceita | 2026-11-13 |
| [0098](0098-kazeditor-atributos-do-elemento.md) | KAZEditor-Theme: editar atributos do elemento no painel (`href`, `alt`, `aria-label`, "Abrir em nova aba"), com lista fechada de destinos e regras de escrita em `kaz-element-attrs.js`; correção do botão Salvar do editor, que não enviava o formulário | ✅ aceita | 2026-11-13 |
| [0099](0099-kazeditor-cor-e-tamanho-do-elemento.md) | KAZEditor-Theme: cor do texto, cor de fundo e tamanho do texto por elemento, escritos no `style` da própria tag com lista fechada de propriedades e formatos (`#rrggbb`, `var(--nome)`, `px` de 6 a 400); cores do tema na lista, prévia imediata, aviso quando uma regra `!important` do tema vence; correção do limite de tamanho do código, que o editor contava em caracteres e o servidor em bytes | ✅ aceita | 2026-11-14 |
| [0100](0100-kazeditor-descricao-de-imagens-e-desenhos.md) | KAZEditor-Theme: lista das imagens e dos desenhos da página que estão sem descrição; o painel Elemento cria o `alt` ou o `aria-label` que falta (no `<svg>`, com `role="img"`), marca como enfeite (`alt=""` / `aria-hidden="true"`) e cria rótulo novo só em links, botões, desenhos e blocos de navegação ou seção; o servidor passa a localizar `aria-hidden`, `aria-labelledby`, `role` e o `<title>` do desenho | ✅ aceita | 2026-11-14 |
| [0101](0101-kazeditor-alinhamento-e-espacamento-do-elemento.md) | KAZEditor-Theme: o painel Elemento ganha alinhamento do texto e os quatro lados de margem e de espaço interno (0 a 400 px), escritos no `style` da tag; a declaração geral do autor (`margin: 0 auto`) nunca é reescrita, o lado entra no fim; valor negativo, `auto` ou em outra unidade é mantido e mostrado; os 38 desenhos sem descrição de três pacotes de `docs/temas` foram marcados como enfeite, com teste que impede a volta | ✅ aceita | 2026-11-14 |
| [0102](0102-kazeditor-link-e-botao-sem-nome.md) | KAZEditor-Theme: a lista da página passa a mostrar também link (`<a href>`) e botão sem nome para o leitor de tela, e o conserto é o campo de rótulo que já existia (`aria-label`); o nome vem de `aria-labelledby`, `aria-label`, do que há dentro e de `title`, e só é pendente o que o editor consegue afirmar (Twig dentro não é acusado); o servidor passa a entregar um resumo do que há dentro de cada link e botão; os 30 selos do pacote `David-Universe-3D` ficam só como enfeite, com teste que impede a volta | ✅ aceita | 2026-11-15 |
| [0103](0103-kazeditor-pendencias-fechadas.md) | KAZEditor-Theme: quatro pendências fechadas, cada uma com teste que impede a volta: o traçado quebrado do selo do envelope em `David-Universe-3D` (uma cor no lugar do comando `H`) volta a `M40 24h80v84H40z`; os 30 selos do tema "Cena Real" (`tema/`) ficam só como enfeite, com `checksum` novo e o tamanho certo em `GUIA-BO.md`; o `pattern` do ID de regra de condição, que o navegador descartava, passa a `[a-z0-9_\-]{1,48}` e o campo avisa com a borda; quatro classes sem regra de CSS saem da tela do editor. Corrige o registro da ADR 0102 sobre a pasta `tema/` | ✅ aceita | 2026-11-15 |
| [0104](0104-kazeditor-moldura-no-idioma-do-admin.md) | KAZEditor-Theme: a moldura do editor (barra de cima, barra da tela, abas dos painéis, navegação dos módulos e rodapé) deixa de ter texto fixo em português e passa a ler 109 chaves `text_kaz_*` dos três arquivos de idioma; as 24 frases que o script escreve chegam em `data-kaz-labels` e são lidas por `KazEditorText`, com o português como texto de reserva; o rodapé mostra o nome do módulo igual ao do botão; um conferidor impede a volta de texto fixo nessas regiões. O conteúdo dos painéis e as mensagens de ação continuam em português fixo | ✅ aceita | 2026-11-15 |
| [0105](0105-kazeditor-tela-cheia-em-nova-pagina.md) | KAZEditor-Theme: as duas telas cheias do editor voltam a funcionar. "Tela cheia em nova página" (`kaz_fullscreen=1`) abria sem folha de estilo e sem script, porque `theme_form.twig` deixava o cabeçalho e o rodapé do Admin de fora; agora os dois entram sempre e o `<body>` recebe a classe `kaz-page-fullscreen`. Nas duas telas cheias o editor passa a ocupar a janela inteira (a regra de altura ganhou peso maior que a do editor comum; antes sobrava uma faixa de 104 px) e o menu, a barra e o rodapé do Admin saem da tela e do caminho do Tab (`kaz-tab-fullscreen`, posta pelo script, para a tela cheia da mesma aba). Sem rota, tabela ou texto novo | ✅ aceita | 2026-11-15 |
| [0106](0106-kazeditor-paineis-e-mensagens-no-idioma-do-admin.md) | KAZEditor-Theme: o conteúdo dos dois painéis laterais do editor de tema e as mensagens que o rodapé mostra depois de cada ação passam a sair no idioma do admin (`pt-br`, `en-us`, `es-eu`), pelo mecanismo da ADR 0104. 75 chaves `text_kaz_*` novas por idioma (184 no total), 33 delas entregues ao script (`KAZ_FRAME_SCRIPT` vai de 24 para 57). Frase com `<code>` ou destaque no meio é cortada com `|split('%s')`, sem `|raw`. Nomes técnicos (`X`/`Y`/`W`/`H`, `primary`, `surface`, opções de Tipo e Papel) ficam iguais e levam `translate="no"`. Em português mudam três mensagens ("painel esquerdo oculto" no lugar de "painel left oculto", o nome do bloco como no botão, dois acentos). `check_kaz_frame_text.js` passa a cobrir os painéis e as chamadas `say()`. | ✅ aceita | 2026-11-15 |
| [0107](0107-kazeditor-avisos-modulos-e-avancado-no-idioma-do-admin.md) | KAZEditor-Theme: o que restava em português fixo na tela do editor de tema passa a sair no idioma do admin: os avisos "Liberação" e "Aceitação", o aviso vermelho de quando o editor não inicia, o cabeçalho dos 14 módulos, a seção "Avançado" e os textos de exemplo dos blocos prontos. 57 chaves `text_kaz_*` novas por idioma (241 no total), 16 delas entregues ao script (`KAZ_FRAME_SCRIPT` vai de 57 para 73). O Twig escolhe a frase do aviso "Liberação" pelo veredito; `ThemeReleaseProbe` não muda. `check_kaz_frame_text.js` passa a conferir o arquivo Twig inteiro. Corrige um registro da ADR 0106 (`kaz-canvas.js` e `kaz-element-attrs.js` não tinham frase fixa). | ✅ aceita | 2026-11-15 |
| [0108](0108-kazeditor-pendencias-da-traducao-e-rotulos-de-condicoes.md) | KAZEditor-Theme: pendências das etapas KZ-10 a KZ-14 fechadas. Com uma camada só a tela diz "1 camada" (chave `text_kaz_one_layer`; função `layersText` no script). O aviso vermelho deixa de citar `tools/kaz-theme-editor-health.js`, que não existe. `ThemeReleaseProbe::ASSET_MARK` passa a `-kaz-theme-editor-` (o atributo `data-kaz-release-asset` dava sempre 0) e a classe ganha teste próprio. No construtor de condições, `labelRow` liga cada rótulo ao campo (`for` + `id`) ou ao grupo (`role="group"`). Saem seis regras sem uso de `kaz-theme-editor.css`. Registra o que ficou: 98 rótulos sem ligação nos outros módulos e os campos sem nome da parte "Quando". | ✅ aceita | 2026-11-15 |
| [0109](0109-kazeditor-nome-para-cada-campo-do-editor.md) | KAZEditor-Theme: todo campo da tela do editor tem nome para o leitor de tela. Os 98 rótulos escritos no Twig sem ligação ganham `for` + `id` (Geral, Tokens, Three.js, Mídia, Sites, Componentes, Comparar); os cartões de mídia, site e componente viram grupos com o nome do item; os 25 campos de Templates recebem o nome do arquivo; as setas do painel de camadas ganham nome; o número ao lado de cada barra do canvas de página recebe o nome da linha; os campos da parte "Quando" das condições e o par de cada variável recebem `aria-label` (oito textos `text_builder_*` novos por idioma). A tela passa a dizer só "KAZEditor-Theme". Nenhum `name`, nenhum dado enviado e nenhum desenho muda. | ✅ aceita | 2026-11-15 |
