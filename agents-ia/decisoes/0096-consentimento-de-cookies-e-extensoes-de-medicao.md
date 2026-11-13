# ADR 0096 — Aviso de cookies no núcleo e extensões de medição que só rodam com aceitação

| | |
| --- | --- |
| **Status** | ✅ aceita (etapa EX-02, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Segunda etapa da frente de extensões (EX-01 base → EX-02 → EX-03 meio de pagamento). Apoia-se na base de extensões (ADR 0095) e na política de conteúdo (ADR 0093). Guia: [`docs/guias/Guia-Extensoes.md`](../../docs/guias/Guia-Extensoes.md), §10. |

## O problema

A EX-01 deu um lugar para etiquetas de medição, mas uma etiqueta de Analytics ou de anúncios grava cookies no navegador do visitante, e isso depende de ele aceitar. Se cada extensão cuidasse do próprio aviso, o site teria um aviso por extensão, e a aceitação dependeria de cada autor ter acertado.

Faltava:

1. um aviso só, do próprio CMS, com recusar tão fácil quanto aceitar;
2. a garantia de que nada de uma extensão de medição chega ao navegador de quem não aceitou, sem depender do código dela;
3. as extensões que o site vai usar de fato: Google Analytics 4, Google Tag Manager, Pixel da Meta e Tag do Pinterest.

## Decisão

### 1. O aviso é do núcleo; a extensão só declara

A extensão declara no `extension.json` para que usa cookies (`"consent": ["analytics"]`, `["marketing"]` ou os dois) e que cookies grava (`"cookies": ["_ga", "_ga_*"]`). Duas categorias, além dos necessários: **medição de visitas** e **publicidade**. `consent` só é aceito em extensão do site público (`apps` igual a `["catalog"]`).

### 2. Quem bloqueia é o CMS, não a extensão

Enquanto o visitante não aceitou nenhuma das categorias de uma extensão, `Extension\Manager` **não inclui o `extension.php` dela**, não registra os eventos dela e não acrescenta os endereços do `csp` dela à política do site. Para esse visitante a extensão está desligada. Sem escolha registrada vale "não".

Extensão com duas categorias roda quando pelo menos uma foi aceita e pergunta `PageOutput::allows()` por cada uma.

### 3. A escolha fica num cookie do site, sem tabela

Cookie `rms_consent`, valor `1.<hora>.<respostas>` (por exemplo `1.1794600000.a1m0`), `HttpOnly`, `SameSite=Lax`, `Secure` quando o site é https. Valor fora do formato, com hora no futuro ou vencido conta como "sem escolha". Vale 180 dias; o prazo muda em *Configurações → Privacidade* (`config_consent_days`, de 30 a 390).

Não há registro no servidor: o site não guarda quem aceitou o quê. O visitante que adultera o cookie só muda a própria escolha.

### 4. O aviso só aparece quando é preciso, e pergunta só o que está em uso

Sem extensão ligada que declare `consent`, não há aviso, não há link no rodapé e a página pública sai idêntica à de antes. Com extensão ligada, o aviso pergunta só pelas categorias em uso e ainda sem resposta. Ligar depois uma extensão de categoria nova faz o aviso voltar só para ela; as respostas anteriores continuam valendo.

### 5. Recusar e aceitar com o mesmo peso; sem JavaScript

O aviso é um formulário comum no cabeçalho: "Recusar" e "Aceitar" com a mesma classe e a mesma aparência, nessa ordem, e "Personalizar". Não há caixa pré-marcada. A tela *Preferências de cookies* (rota `common/consent`) mostra cada categoria com os serviços e os cookies declarados, e fica a um clique pelo rodapé enquanto houver extensão que dependa de aceitação. O aviso fica na parte de baixo da janela e não impede o uso do site. Textos em `en-us`, `pt-br` e `es-eu`.

Gravar a escolha é POST com token; o retorno é só para um caminho do próprio site.

### 6. Retirada apaga os cookies declarados

Quando uma categoria aceita passa a recusada, a extensão deixa de rodar na página seguinte e o CMS manda o navegador apagar os cookies que ela declarou (nome exato ou prefixo com `*`), no caminho `/`, para o host e para os domínios acima dele. A conferência se repete a cada página enquanto houver recusa, porque uma etiqueta pode regravar o cookie dela enquanto a página fecha.

O cookie de sessão e o da própria escolha nunca são apagados por essa lista, e cookie que outra extensão em uso também declara fica.

### 7. Formulário enviado vira um aviso sem dados pessoais

Depois de um envio aceito do formulário de contato ou do pedido de orçamento, a página seguinte informa o fato às extensões uma única vez (`PageOutput::happened()`). Nome, e-mail, telefone e mensagem não passam por ali. Envio tratado como robô não gera aviso.

### 8. Quatro extensões, todas desligadas até alguém ligar

| Extensão | Categoria | O que faz | Escolhas de privacidade |
| --- | --- | --- | --- |
| `google_analytics` | medição | `gtag.js` com o ID de medição; `generate_lead` nos formulários | Sinais de publicidade sempre negados no Consent Mode; sem Google Signals |
| `google_tag_manager` | medição e publicidade | Contêiner; informa ao contêiner o que foi aceito (Consent Mode) | Etiqueta de HTML personalizado só com a caixa marcada pelo administrador; sem `eval` |
| `meta_pixel` | publicidade | `fbevents.js`; `PageView`, `Contact`, `Lead` | Coleta automática de cliques e dados da página desligada por padrão |
| `pinterest_tag` | publicidade | `core.js`; `page`, `lead` | — |

Cada identificador é conferido pelo formato na configuração e de novo dentro da extensão antes de ir para a página.

## Efeito

| Para quem | O que muda |
| --- | --- |
| Visitante | Nada enquanto nenhuma dessas extensões estiver ligada. Com uma ligada: aviso de cookies; sem aceitar, nenhum pedido a outro site e nenhum cookie além da sessão |
| Site instalado, ao atualizar | Sobem os arquivos. Nenhum SQL, nenhuma linha nova no `config.php`. As quatro extensões aparecem desligadas em *Extensões* |
| Quem administra | Campo novo em *Configurações → Privacidade*. Na lista de extensões, a linha mostra de que aceitação cada uma depende. Convém existir uma página com a palavra-chave `cookies` (o aviso passa a mostrar o link "Política de cookies") |
| Quem escreve extensão | Chaves `consent` e `cookies`; `PageOutput::allows()` e `happened()`. Guia §10 |
| Quem escreve o núcleo | `Controller::pageSignal()` para avisar um fato à página seguinte; `Response::addCookie()`; cabeçalho e rodapé não carregam arquivo de idioma de página |

Arquivos: `system/library/consent.php`; rota `common/consent` com template, CSS e textos; aviso em `common/header.twig`; link no rodapé; quatro pastas em `dev/extension/`; ajustes em `Extension\{Manifest, Manager, PageOutput}`, `Engine\Controller`, `Library\Response`, pré-ação `startup/extension` do catálogo, formulários de contato e de orçamento, tela e camada de dados de *Configurações*, tela *Extensões*.

## Verificação

Ambiente local (PHP 8.2, MariaDB, Chromium headless), com identificadores fictícios. O navegador de teste alcança a internet: os scripts das quatro plataformas foram de fato carregados e executados.

| Situação | Resultado |
| --- | --- |
| Nenhuma extensão com `consent` ligada | HTML e política de conteúdo idênticos aos de `origin/main` em nove páginas; tela de preferências informa que só há cookies necessários |
| Primeira visita, extensões ligadas | Aviso no idioma da página; nenhum pedido a outro site; só o cookie de sessão; "Recusar" e "Aceitar" com o mesmo estilo calculado; em 360 px sem rolagem horizontal |
| Recusar | Aviso some; nada carrega nesta página nem nas seguintes |
| Aceitar tudo | `gtag`, `fbq` e `pintrk` definidos; console sem erro; cookie da escolha com o prazo configurado |
| Formulário de contato / pedido de orçamento | GA4 `generate_lead` (`contact` / `quote`), Meta `Contact` / `Lead`, Pinterest `lead`; recarregar não repete; nenhum dado do formulário em pedido a outro site |
| Retirada pela tela de preferências | Nada carrega; `_ga` e `_pin_unauth` apagados na hora; `_ga_<id>` regravado pela etiqueta ao fechar a página e apagado na página seguinte |
| Só GA4 ligado | O aviso pergunta só por medição |
| Pixel ligado depois, visitante já com resposta sobre medição | O aviso volta só para publicidade; GA4 segue rodando |
| Tag Manager, medição aceita e publicidade recusada | Contêiner carrega com `ad_*` negado; o inverso, com `analytics_storage` negado |
| Tag Manager com "HTML personalizado" | Desmarcada: carregador sem nonce. Marcada: nonce repassado |
| Prazo no painel | 29, 391, 1800 e texto recusados; 45 gravado e o cookie sai com 45 dias; campo vazio volta a 180 |
| Idiomas | Aviso, tela e rodapé em `en-us`, `pt-br`, `es-eu`, sem chave sem tradução |

Dois defeitos apareceram só no ambiente, com os testes de unidade já passando, e foram corrigidos e cobertos por teste: a camada de dados de *Configurações* descartava a chave nova (a tela dizia "salvo" e nada era gravado); a confirmação "Preferências de cookies salvas", guardada depois de responder ao aviso, aparecia mais tarde dentro do formulário de contato.

Testes: `tests/Unit/Consent/`, e em `tests/Unit/Extension/` os arquivos `ConsentGateTest`, `TrackingExtensionsTest`, `ConsentWiringTest` e `GuideTest`. Prova por mutação: 103 trocas que retiram uma garantia cada, todas acusadas por algum teste.

## Limites

- **É o mecanismo, não a adequação.** O aviso, o bloqueio e os textos padrão não substituem a política de privacidade e de cookies do site nem a avaliação jurídica (LGPD e, para visitantes europeus, GDPR / ePrivacy). O que cada plataforma faz com os dados depois de recebê-los é da conta contratada com ela.
- **Sem registro no servidor.** O site não consegue demonstrar depois que um visitante específico aceitou. Foi a opção escolhida para esta etapa (nenhum dado pessoal novo no banco).
- **Cookies de outro domínio ficam.** O site apaga só os cookies gravados no próprio domínio. Os que a plataforma gravou no domínio dela (por exemplo `facebook.com`) saem pelo navegador ou pela conta do visitante nela.
- **Logo depois da retirada** uma etiqueta ainda em execução pode regravar o cookie dela; ele é apagado de novo no pedido seguinte ao site.
- **A lista `cookies` é declarada pelo autor da extensão.** Cookie que a extensão grava e não declara não é apagado.
- **Tag Manager:** quem edita o contêiner decide que etiquetas rodam. O CMS informa a aceitação ao contêiner; respeitá-la em cada etiqueta é configuração do contêiner.
- **GA4 e Tag Manager ligados juntos**, com GA4 também dentro do contêiner, contam as visitas em dobro.
- **Fora desta etapa:** envio de eventos pelo servidor (Conversions API), Google Ads / remarketing como extensão própria, eventos de loja (carrinho, compra), categorias além das duas.
- **Não foi exercitado:** identificadores reais (os painéis das plataformas não foram conferidos; viu-se o pedido sair do navegador com os parâmetros certos, não a chegada do dado); site em https (o atributo `Secure` do cookie foi conferido por teste de unidade); produção.
