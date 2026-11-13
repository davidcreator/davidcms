# ADR 0093 — Política de conteúdo aplicada (nonce por requisição) e HTTPS obrigatório no navegador

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S8, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Achado S8 da análise do repositório, reproduzido em `c529bdf` antes de qualquer mudança: navegador real (Chromium) percorrendo o site público e o admin em um ambiente local, com um ataque simulado na página inicial. Substitui o item 2 da ADR 0037 (política de transição em modo de relato). Usa as ADRs 0087 (o tema não escreve `<script>`) e 0089 (HTTPS atrás de CDN). |

## O que foi encontrado

O CMS enviava uma única linha de política para todas as telas, em `system/config/default.php`:

```
Content-Security-Policy-Report-Only: default-src 'self' https:; img-src * data: blob:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; font-src 'self' https: data:; connect-src 'self' https:; frame-ancestors 'self'
```

Dois problemas, um por cima do outro:

1. **Modo de relato.** `Report-Only` pede ao navegador que só registre; nada é bloqueado. E não havia endereço de relato configurado: os registros ficavam no console de cada visitante.
2. **A política não bloquearia nada mesmo se fosse aplicada.** `'unsafe-inline'`, `'unsafe-eval'` e `https:` em `script-src` aceitam script embutido, `eval` e script de qualquer site.

Ataque simulado na página inicial (HTML injetado como viria de uma falha de XSS):

| Política | Script embutido | Atributo `onerror` | Script de outro site | `eval` |
| --- | --- | --- | --- | --- |
| A de então (modo de relato) | executou | executou | executou | executou |
| A de então, se fosse aplicada | executou | executou | executou | executou |
| A desta ADR | bloqueado | bloqueado | bloqueado | bloqueado |

Não havia `Strict-Transport-Security`. Sem ele, a primeira visita de cada sessão por `http://` sai em texto aberto até o redirecionamento, e quem estiver no caminho (rede Wi-Fi aberta) pode impedir a troca para HTTPS.

Inventário do que o próprio CMS escreve e que uma política de verdade precisa acomodar:

| Onde | O que |
| --- | --- |
| Site público | 1 bloco embutido: o importmap do Three.js em `common/header.twig`, só quando o tema pede 3D. Nenhum atributo `on*`, nenhum recurso de outro site, nenhum `eval` |
| Admin | 8 blocos embutidos em 7 templates; 5 confirmações em atributo (`onsubmit` / `onclick`); fonte do painel no Google Fonts; "Conectar Mercado Pago" é um formulário que redireciona para `auth.mercadopago.com.br` |
| Admin, editor antigo de posts | CKEditor 4 monta a própria interface com atributos `on*` (162 relatos em uma tela com 3 editores) e consultava `cke4.ckeditor.com` a cada abertura. Só aparece em post que não foi convertido para blocos |
| Os dois | Atributo `style=""` em dezenas de pontos; o tema publica cores por ele |

## Decisão

### 1. A política é montada em um ponto, por requisição

`Security\Csp` (`system/library/security/csp.php`) monta a política; `framework.php` a envia com `Response::setHeader()` e registra o serviço `csp`. A linha fixa saiu de `config/default.php`.

Site público:

```
default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self' blob:; media-src 'self' https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

Admin: a mesma, mais `https://fonts.googleapis.com` em `style-src`, `https://fonts.gstatic.com` em `font-src`, `'self'` em `frame-src` (prévias), `blob:` em `media-src` e `https://auth.mercadopago.com.br` em `form-action`.

- **Scripts:** só arquivos do próprio site e blocos com o nonce da resposta. Sem `'unsafe-eval'`; sem `'unsafe-inline'` para script.
- **Estilos embutidos continuam permitidos.** Tirá-los exigiria reescrever o tema e as telas; fica fora desta etapa.
- **Imagens** de qualquer site `https` (posts e páginas citam imagens de fora). **Vídeo incorporado** só dos dois endereços que o renderizador de blocos gera.
- `blob:` em `connect-src`: o carregador de modelos 3D lê texturas embutidas por esse esquema.
- **Instalador:** não recebe política (antes recebia a de relato, que não bloqueava nada). Roda uma vez, antes de o site existir, e as telas dele não foram adaptadas.

### 2. Nonce: entregue pelo controller, fora do alcance do tema

O nonce é sorteado uma vez por requisição (18 bytes). O template escreve `<script nonce="{{ csp_nonce }}">` e o controller entrega `$data['csp_nonce'] = $this->csp->nonce();`.

Não é variável global do Twig, de propósito. A fonte de tema roda em sandbox e não pode escrever `<script>` (ADR 0087); com o nonce ao alcance, um tema publicado autorizaria o próprio script. `common/header.twig`, que traz o importmap, não é redefinível pelo tema e passa ao trecho do tema só variáveis nomeadas.

### 3. Admin: confirmações fora do HTML

Os 5 `onsubmit="return confirm(…)"` / `onclick` viraram `data-rms-confirm="mensagem"`, tratado em `admin.js` (formulário ou botão de envio). Cancelar não envia; confirmar envia.

### 4. Uma exceção declarada: o editor antigo de posts

Quando a tela de post vai abrir o CKEditor 4 (post não convertido em blocos, ou editor de blocos indisponível), `blog.php` chama `$this->csp->allowInlineScripts()` e reenvia o cabeçalho: `script-src 'self' 'unsafe-inline'` só naquela resposta. `eval` e script de outro site continuam bloqueados ali. A consulta de versão a `cke4.ckeditor.com` foi desligada (`versionCheck: false`).

É a única chamada no código; um teste trava isso.

### 5. Telas com política própria

As prévias do admin (página, post, miniaturas do tema) já enviavam uma política mais estrita. Passam a usar `setHeader()`, que troca a linha global pela delas em vez de empilhar.

### 6. Chaves no `config.php`

| Constante | Efeito |
| --- | --- |
| `REAMUR_CSP_MODE` | `'report'`: só relata no console, nada é bloqueado. `'off'`: sem política. Ausente ou qualquer outro valor: aplicada. Um erro de digitação não desliga a proteção |
| `REAMUR_CSP_SOURCES` | Sites de fora liberados **no site público**, por diretiva. Só endereço `https://` de site (`https://exemplo.com`, `https://*.exemplo.com`, porta e caminho opcionais). Palavras-chave (`'unsafe-inline'`, `'unsafe-eval'`…), esquemas soltos (`https:`, `data:`), `*`, `http` e diretivas fora da lista são ignorados: a configuração libera um site, não reabre script embutido |
| `REAMUR_HSTS_MAX_AGE` | Prazo do HSTS em segundos; `0` desliga |

Com `REAMUR_CSP_MODE` em `report` ou `off`, o painel inicial do admin mostra um aviso até a linha sair.

### 7. HSTS

`Strict-Transport-Security: max-age=15552000` (180 dias), sem `includeSubDomains` e sem `preload`.

- Sai quando `HTTP_SERVER` do `config.php` começa com `https://`. É a declaração de quem instalou; vale mesmo quando a CDN esconde o HTTPS do PHP (ADR 0089). Não depende de cabeçalho do cliente.
- Nunca para `localhost` nem para endereço IP.
- Teto de 2 anos para o prazo configurado.

### 8. Verificação remota

`tools/check_producao.sh`, check 17: o site e o login do admin precisam trazer a política aplicada, com nonce, sem `'unsafe-inline'` de script e sem `'unsafe-eval'`. Só relato ou ausência reprova. HSTS ausente em alvo https é aviso.

## Efeito

| Para quem | O que muda |
| --- | --- |
| Visitante | Nada visível. Um script injetado na página deixa de rodar |
| Site instalado, ao atualizar | A política passa a ser aplicada sem nenhuma configuração. Se o `config.php` tem `HTTP_SERVER` em `https://`, o HSTS começa a sair |
| Quem usa serviço de fora no site público (estatística, fonte, mapa, chat) | Precisa declarar o endereço em `REAMUR_CSP_SOURCES`; sem isso o navegador bloqueia e registra no console |
| Quem escreve template | `<script>` embutido leva `nonce="{{ csp_nonce }}"`; sem `on*=`; sem `eval` |
| Tema | Nada: já não podia escrever script |

**HSTS é um compromisso com prazo.** Depois que um navegador recebe o cabeçalho, ele recusa `http://` nesse domínio por 180 dias, inclusive se o certificado vencer. Para voltar atrás: `REAMUR_HSTS_MAX_AGE` em `0` e esperar o prazo já entregue. Subdomínios não são afetados.

Sem tabela, coluna, migration ou rota nova.

## Verificação

Ambiente local (PHP 8.2, MariaDB, Chromium headless), com a política enviada pelo próprio servidor:

| Prova | Resultado |
| --- | --- |
| Ataque simulado na página inicial | Bloqueados: script embutido sem nonce, atributo `onerror`, script de outro site, link `javascript:`, `eval`, `new Function`, `<base>` trocando endereços, formulário para outro site. Hero 3D funcionando |
| Varredura do site público | 45 páginas, política aplicada em todas, 0 bloqueios, 0 erros de JavaScript; hero 3D com 1 canvas, 18 pedidos, todos 200 |
| Varredura do admin | 139 páginas, política aplicada em todas, 0 bloqueios. Os erros de JavaScript vistos são os mesmos de antes da mudança |
| Editores (blocos de post e de página, editor de tema, aparência) com cliques em abas e botões | 0 bloqueios |
| Editor antigo (post com 3 editores CKEditor) | Abre, aceita digitação, negrito, modo código-fonte e janela de link; 0 bloqueios; nenhum pedido a `cke4.ckeditor.com` |
| Confirmações (5 telas) | A pergunta aparece nas 5 e cancelar não envia. Confirmar foi exercitado na lista de webhooks: envia e exclui |
| "Conectar Mercado Pago" | Chega a `auth.mercadopago.com.br`. Controle: sem o endereço na política, o navegador bloqueia |
| Contato e orçamento | Enviados, mensagem de sucesso, 0 bloqueios |
| Vídeo de post | Quadro do YouTube e do Vimeo carregam; quadro de outro site é bloqueado; vídeo em arquivo `https` carrega |
| Prévias do admin | Uma linha de política, a própria |
| Página de erro 500 | Sai com a política; 0 bloqueios |
| Chaves | `report` → cabeçalho de relato; `off` → nenhum; valor desconhecido → aplicada; `REAMUR_CSP_SOURCES` aceita só endereços `https` de site; HSTS com `HTTP_SERVER` em `https://`, prazo ajustável, `0` desliga |

Testes: `tests/Unit/Security/CspTest.php` (política, modo, origens recusadas, HSTS, `setHeader`) e `CspWiringTest.php` (todo `<script>` embutido com nonce, nenhum `on*=`, nonce fora do tema, exceção única, chaves). 24 alterações propositais no código, uma por vez: os testes reprovaram as 24.

## Limites

- **Estilos embutidos seguem permitidos.** Um HTML injetado ainda consegue alterar a aparência da página; não consegue executar código.
- **Imagens de qualquer site `https`.** Uma imagem injetada pode fazer o navegador pedir um endereço de fora.
- **Tela do editor antigo de posts:** script embutido volta a ser aceito naquela resposta. Converter o post para blocos tira a tela da exceção. O CKEditor 4.22.1 é a última versão gratuita e não recebe correções desde 2023.
- **O admin continua carregando a fonte do Google Fonts.**
- **Sem endereço de relato.** Bloqueios aparecem só no console do navegador de quem visita.
- **Página guardada em cache por CDN:** se a hospedagem guardar o HTML, o nonce se repete enquanto a cópia valer. A política e a página continuam coerentes; o nonce deixa de ser imprevisível para aquela cópia.
- **Instalador sem política.**
- **Navegadores testados:** só Chromium. Firefox e Safari não foram exercitados.
- **Não exercitado em produção:** Apache / LiteSpeed, a CDN da hospedagem e o fluxo completo do Mercado Pago com credenciais reais.
- **`REAMUR_CSP_SOURCES` vale só para o site público.** O admin não aceita origens extras.

## Pendência registrada

Serviços de fora no site público: o mantenedor informou que usa ou vai usar, sem dizer quais. O código não traz nenhum hoje. Cada um entra por `REAMUR_CSP_SOURCES` em `catalog/config.php`.
