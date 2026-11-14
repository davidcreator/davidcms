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
