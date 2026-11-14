# Changelog

## KZ-06 — KAZEditor-Theme: cor e tamanho por elemento (2026-11-14)
- ADR 0099. Sexta etapa do caminho B do editor de tema.
- **Novo no painel Elemento, seção "Cor e tamanho":** **Cor do texto**, **Cor de fundo** e **Tamanho do texto (px)** do elemento selecionado. O painel escreve `color`, `background-color` e `font-size` no atributo `style` da própria tag, em `templates[chave]`. Nada é gravado até salvar o rascunho.
- **Cores do tema ou cor própria:** a lista traz as cores que o tema declara (por exemplo `--dj-primary`), cada uma com o valor atual; escolher uma escreve `var(--nome)`, e o elemento acompanha se o tema mudar a cor. "Cor própria" abre o seletor de cor do navegador e escreve o código `#rrggbb`.
- **A mudança aparece na hora** nos três quadros (celular, tablet, computador), enquanto o servidor redesenha a página.
- **O que já está no `style` é mantido:** `margin`, `display` e as outras declarações ficam byte a byte iguais. "Como o tema define" retira só aquela declaração; se não sobrar nenhuma, o atributo `style` sai e o código volta ao que era.
- **Valores aceitos:** cor `#rgb` / `#rrggbb` ou `var(--nome)`; tamanho de 6 a 400 px. O resto é recusado com aviso e o código continua com o valor anterior. Valor do autor em outro formato (`rgba(…)`, `1.2rem`) aparece como "Escrito no código" e só muda se outro for escolhido.
- **Aviso quando o tema vence:** se uma regra do tema marcada `!important` ganha do que foi escrito, o painel diz "A página mostra outro valor neste elemento". O painel não escreve `!important`.
- **Sem campos, com explicação:** `style` que vem do conteúdo do site (`style="color: {{ cor }}"`), `style` que o editor não lê com segurança (mesma propriedade duas vezes, por exemplo), imagens e vídeos. Trecho padrão do sistema pede **Personalizar este trecho** antes de editar.
- **Correção: limite de tamanho do código.** O servidor aceita 131 072 bytes por trecho (letra acentuada e quebra de linha valem dois); o editor contava caracteres. Perto do limite, uma mudança passava no painel e era recusada pelo servidor em seguida. O editor passa a contar como o servidor, nos campos de atributo, cor, tamanho, texto e no painel Design. O defeito é anterior a esta etapa.
- **Atenção ao atualizar um site instalado:** suba os arquivos. Não há SQL, linha nova no `config.php` nem configuração. O endereço do script do canvas mudou de versão (`-kaz-canvas-kz06`); não é preciso limpar o cache do navegador.
- **Banco e dados salvos:** nenhuma tabela, coluna, rota ou campo novo no `POST`. Nenhum controle novo tem `name`.
- **Para quem escreve código:** `Theme\ThemeSourceMap` passa a localizar `style` em qualquer tag; ler e escrever as declarações fica em `admin/view/js/kaz-element-attrs.js` (`styleOf`, `setStyle`, `tooLong`), conferido por `node tests/check_kaz_element_style.js` (chamado também pelo PHPUnit).
- **Limites:** só essas três propriedades (largura, margem, borda, fonte e posição ficam de fora). A mudança vale para todas as larguras de tela e para todas as repetições de um elemento em laço. Regra `!important` do tema continua vencendo. O painel não confere contraste entre texto e fundo.
- **Conferido:** 1576 testes em PHP 8.2 e 8.3; no Chromium, um pacote de prova e os seis pacotes de `docs/temas`, com salvar, reabrir, desfazer e salvar de novo. Outros navegadores não foram testados.

## KZ-05 — KAZEditor-Theme: editar link, texto alternativo e rótulo do elemento (2026-11-13)
- ADR 0098. Quinta etapa do caminho B do editor de tema; cor e tamanho por elemento (KZ-06) vêm depois.
- **Novo no painel Elemento:** **Destino do link** (`href` de links), **Texto alternativo** (`alt` de imagens) e **Rótulo para leitor de tela** (`aria-label` de qualquer elemento). Cada campo troca só o valor entre as aspas em `templates[chave]`; o resto do código fica byte a byte igual. Nada é gravado até salvar o rascunho.
- **Abrir em nova aba:** a caixa escreve `target="_blank"` e `rel="noopener"` na tag do link, e retira ao desmarcar. Um `rel` do autor (por exemplo `noopener me`) é mantido.
- **Destinos aceitos:** caminho do site (`/contato`), âncora (`#secao`), consulta (`?pagina=2`), `https://`, `http://`, `mailto:` e `tel:`. O resto (`javascript:`, `data:`, `//site`, endereço com `usuario@`) é recusado com aviso, e o código continua com o destino anterior.
- **Valor que vem do conteúdo do site** (`href="{{ … }}"`) mostra a expressão de origem e não é editado ali. Imagem sem `alt` no código recebe um aviso. Trecho padrão do sistema pede **Personalizar este trecho** antes de editar.
- **Chegar ao link:** quando o clique cai no texto ou no ícone dentro do link, o painel oferece **Editar o link**. Com o trecho inteiro selecionado, a lista **Links deste trecho** leva a cada link pelo teclado.
- **Correção: o botão Salvar do editor de tema não enviava o formulário.** Controles só de tela (altura da tela, X/Y/W/H do objeto, números da cena 3D) ficavam fora do passo configurado neles e o navegador barrava o envio sem mostrar nada; **Publicar** usa o mesmo formulário. Medido em Chromium nos seis pacotes: nenhum salvava. Agora só os campos que vão ao servidor são conferidos antes do envio. O defeito é anterior a esta etapa.
- **Correção:** o padrão do campo `slug` era ignorado pelo navegador (expressão inválida). Slug fora do padrão passa a ser apontado no campo antes do envio; a regra do servidor é a mesma.
- **Atenção ao atualizar um site instalado:** suba os arquivos. Não há SQL, linha nova no `config.php` nem configuração. Os endereços dos scripts do editor mudaram de versão (`-kaz-canvas-kz05`, `-kaz-theme-editor-kz05`); não é preciso limpar o cache do navegador.
- **Banco e dados salvos:** nenhuma tabela, coluna, rota ou campo novo no `POST`. Nenhum controle novo tem `name`.
- **Para quem escreve código:** `Theme\ThemeSourceMap` devolve os atributos de cada tag (oitavo campo de cada item de `elements`); as regras de escrita ficam em `admin/view/js/kaz-element-attrs.js`, sem acesso a página, rede ou armazenamento, e são conferidas por `node tests/check_kaz_element_attrs.js` (chamado também pelo PHPUnit).
- **Limites:** o painel não cria nem apaga `href`, `alt` ou `aria-label`; só troca o valor de um que já existe e esteja escrito entre aspas, sem Twig. Nenhum dos seis pacotes tem `alt` fixo: esse campo foi provado com um pacote de teste. O destino aceito não é conferido contra as páginas do site.
- **Conferido:** 1564 testes em PHP 8.2 e 8.3; no Chromium, os seis pacotes de `docs/temas` e um pacote de prova, com salvar e reabrir. Outros navegadores não foram testados.
- **Achado, não corrigido:** o pacote `David-Universe-3D` traz um desenho SVG com um código de cor no meio do traçado (`M40 24h80v84#eb767e40z`), em quatro trechos; o selo não é desenhado. Ver ADR 0098.

## Extensões EX-03 — contrato de meio de pagamento (2026-11-13)
- ADR 0097. Terceira etapa da frente de extensões. Não acrescenta meio de pagamento, tela, tabela nem configuração.
- **Para quem usa o site: nada muda.** Mesmos endereços (`checkout/payment.pay|<chave>`, `checkout/payment.notify`), mesma tela *Mercado Pago*, mesmo botão na lista de pedidos, mesmas respostas ao Mercado Pago, mesmos avisos na tela e mesmas linhas de log. Links já enviados a clientes e avisos de cobranças antigas continuam valendo.
- **Novo no código:** `system/library/payment/`. Um meio de pagamento cumpre `Payment\Method` e responde a três perguntas: está pronto para cobrar, crie a cobrança deste pedido, este aviso é verdadeiro e de qual pagamento ele fala.
- **Regras gerais num lugar só:** `Payment\Checkout` cuida da chave do link, do link pendente reaproveitado, do aviso repetido, da situação do pedido, do histórico e do evento de pedido pago. Antes essas regras estavam misturadas ao Mercado Pago e repetidas no admin e no site público.
- **Mercado Pago** passa a ser o primeiro meio (`Payment\Mercadopago`), com as regras de antes (ADRs 0035 e 0094).
- **Conferência:** o mesmo roteiro (painel, site público e avisos, contra um Mercado Pago de mentira) foi rodado no código anterior e no novo. Os relatórios, com respostas, avisos de tela, banco, histórico dos pedidos, log e chamadas feitas ao Mercado Pago, são idênticos.
- **Atenção ao atualizar um site instalado:** suba os arquivos. Não há SQL, linha nova no `config.php` nem configuração.
- **Banco:** nenhuma tabela ou coluna nova. As cobranças continuam em `rms_mp_payment`.
- **Para quem escreve código:** saíram dos models `addPayment`, `getPaymentByOrder`, `getPaymentByToken`, `reflectPaymentStatus` (catálogo) e `addPayment`, `getPaymentByOrder`, `getActiveSeller` (admin); o admin ganhou `getActiveSellers()` e `getSellerByMpUserId()`. Regra em `agents-ia/regras/02-padroes-de-codigo.md`. Exemplo de meio de pagamento: `tests/Unit/Payment/FictitiousMethod.php`.
- **Limite:** conferido só contra um Mercado Pago de mentira, porque o ambiente de teste do Mercado Pago real não consegue enviar avisos ao ambiente local. O contrato foi provado com um meio fictício; o primeiro meio real (Stripe, PayPal) pode pedir ajustes nele, e traz junto a lista de meios, a tabela geral de cobranças e a tela dele.
- **Mantido como estava (ver ADR 0097):** vale o último aviso recebido, mesmo que cancele um pedido já aprovado; devolução não muda a situação do pedido.

## Extensões EX-02 — aviso de cookies e extensões de medição (2026-11-13)
- ADR 0096. Guia: [`docs/guias/Guia-Extensoes.md`](guias/Guia-Extensoes.md), §10. Segunda etapa da frente de extensões; meio de pagamento (EX-03) vem depois.
- **Novo no site público:** aviso de cookies do próprio CMS, com "Recusar" e "Aceitar" de mesma aparência e "Personalizar"; tela **Preferências de cookies** (rota `common/consent`), com link no rodapé. Funciona sem JavaScript. Textos em `en-us`, `pt-br` e `es-eu`.
- **Só aparece quando é preciso:** sem extensão ligada que dependa de aceitação, não há aviso nem link no rodapé, e a página pública sai idêntica à de antes.
- **Garantia:** extensão que declara `consent` não é carregada, e os endereços dela não entram na política de conteúdo, para o visitante que não aceitou. Sem escolha vale "não": nenhum pedido a outro site e nenhum cookie além da sessão.
- **Retirada:** o visitante muda a escolha quando quiser; a extensão para na página seguinte e o CMS apaga do navegador os cookies que ela declarou.
- **Quatro extensões novas, desligadas até alguém ligar:** Google Analytics 4 (`google_analytics`), Google Tag Manager (`google_tag_manager`), Pixel da Meta (`meta_pixel`) e Tag do Pinterest (`pinterest_tag`). Cada uma pede só o identificador da conta.
- **Formulários:** depois de um envio do formulário de contato ou do pedido de orçamento, as extensões ligadas e aceitas registram um evento de contato (`generate_lead`, `Contact` / `Lead`, `lead`). Nome, e-mail, telefone e mensagem não são enviados. A caixa "Avisar formulários enviados" de cada extensão desliga isso.
- **Escolhas de privacidade de fábrica:** GA4 sem sinais de publicidade; Pixel da Meta sem a coleta automática de cliques e dados da página; Tag Manager sem etiquetas de HTML personalizado. As duas últimas têm caixa para ligar.
- **Novo no admin:** *Configurações → Privacidade → Validade da escolha de cookies* (de 30 a 390 dias; padrão 180). Na tela *Extensões*, a linha mostra de que aceitação a extensão depende.
- **Para quem escreve extensão:** chaves `consent` e `cookies` no `extension.json`; `$page->allows('marketing')` e `$page->happened(PageOutput::SIGNAL_CONTACT)`.
- **Atenção ao atualizar um site instalado:**
  1. suba os arquivos (inclui quatro pastas novas em `extension/`); não há SQL nem linha nova no `config.php`;
  2. antes de ligar uma das quatro extensões, confira se o site tem uma página com a palavra-chave `cookies`: o aviso mostra o link para ela;
  3. revise a política de privacidade do site para citar os serviços que forem ligados.
- **Banco:** nenhuma tabela ou coluna nova. A escolha do visitante fica só no navegador dele (cookie `rms_consent`); o prazo, quando alterado, em `rms_setting` (`config_consent_days`).
- **Limite:** o aviso e o bloqueio são o mecanismo. A adequação à LGPD depende também da política de privacidade do site e do contrato com cada plataforma. O site não guarda prova de quem aceitou. Cookies gravados no domínio da própria plataforma (por exemplo `facebook.com`) o site não consegue apagar.
- **Não conferido:** os painéis das plataformas, porque o teste usou identificadores fictícios. O pedido sai do navegador com os parâmetros certos; a chegada do dado na conta precisa ser conferida com os identificadores reais.
- **Para quem escreve código:** regra em `agents-ia/regras/02-padroes-de-codigo.md`; o trecho de exemplo do guia é lido por `tests/Unit/Extension/GuideTest.php`.

## Extensões EX-01 — base: pasta, descrição conferida, eventos e saída controlada (2026-11-13)
- ADR 0095. Guia: [`docs/guias/Guia-Extensoes.md`](guias/Guia-Extensoes.md). Primeira etapa da frente de extensões; aviso de cookies (EX-02) e meio de pagamento (EX-03) vêm depois.
- **Novo:** uma extensão é uma pasta em `dev/extension/<código>/` com `extension.json` (nome, versão, versão mínima do CMS, campos de configuração, eventos, endereços de fora) e `extension.php`. Chega por FTP ou deploy; não há envio de ZIP pelo admin.
- **Novo no admin:** tela **Extensões** (menu, rota `extension/extension`): lista com a situação de cada uma e o que ela declara, ligar / desligar, formulário de configuração gerado, remover dados. Descrição inválida ou versão incompatível aparece com o motivo e não pode ser ligada. Textos em `en-us`, `pt-br` e `es-eu`.
- **Garantias:** extensão desligada não tem nenhum código executado, e a tela nunca executa código de extensão. Erro em uma extensão vai para o log e a página abre. Sem extensão ligada, a página pública sai idêntica à de antes.
- **Página pública:** a extensão não escreve HTML. Ela pede meta tag, script por endereço ou script embutido, e o CMS escreve a tag com escape e nonce. Script de endereço não declarado é recusado e registrado no log.
- **Política de conteúdo (ADR 0093):** os endereços declarados em `csp` valem só com a extensão ligada e só no site público. A política do admin não muda. Continua sem script embutido livre e sem `eval`.
- **Segredos (ADR 0094):** campo do tipo `secret` é gravado cifrado, não volta para a tela e entra em *Configurações → Proteção dos segredos*.
- **Eventos para extensões:** `page/output` (cabeçalho do site público) e `order_add`, `order_edit`, `order_history_edit` (os mesmos dados dos webhooks, entregues depois deles). `Engine\Event` ganha prioridade.
- **Extensão de exemplo:** "Verificação de site" (`site_verification`), com as meta tags de verificação do Google, Bing, Pinterest e Meta. Vem desligada.
- **Atenção ao atualizar um site instalado:**
  1. suba a pasta `extension/` junto com o resto;
  2. para a tela aparecer, rode `install/sql/upgrade_ex01_extension_grant.sql` no phpMyAdmin (pode rodar mais de uma vez; `rollback_ex01_extension_grant.sql` desfaz), ou marque `extension/extension` em *Usuários → Grupos de usuários*;
  3. em nginx, feche a pasta `extension/` na configuração do servidor (sob Apache o `.htaccess` da pasta já fecha).
- **Configuração nova, opcional, no `config.php`:** `define('REAMUR_EXTENSIONS', false);` nos dois arquivos desliga todas as extensões de uma vez, sem tocar no banco. Para o caso de uma extensão quebrar o site.
- **Banco:** nenhuma tabela ou coluna nova. O estado fica em `rms_setting` (`code = 'extension'`).
- **Limite:** uma extensão ligada é código PHP com o mesmo alcance do CMS (banco, arquivos, configurações). A descrição mostra o que ela pretende e limita o que sai na página; não isola o código. Ligue só extensão de origem conhecida. Erro fatal que o PHP não deixa capturar (falta de memória, `exit`) derruba a página até a extensão ser desligada.
- **Para quem escreve código:** regra em `agents-ia/regras/02-padroes-de-codigo.md`; os exemplos do guia são executados por `tests/Unit/Extension/GuideTest.php`.
- Admin: `ASSET_V` de `8.1.2` para `8.1.3`.
- `tools/check_producao.sh`, check 19 (novo): o `extension.json` de uma extensão não é entregue ao navegador. O script passa a ter 19 checks.

## Segurança S9 — segredos cifrados no banco (2026-11-13)
- ADR 0094. Achado da análise, reproduzido antes da correção: tokens do Mercado Pago, segredo do aplicativo, chave de assinatura das notificações, senha SMTP e segredos de webhook estavam legíveis no banco. Um token copiado do banco foi aceito pelo Mercado Pago de teste. Revisa o item 6 da ADR 0034.
- **Corrigido:** os seis valores passam a ser gravados cifrados (AES-256-GCM) e abertos só no ponto de uso. Cada valor fica preso à coluna dele; valor alterado ou movido não abre. As telas continuam sem mostrar segredo, e o cache das configurações deixa de guardá-lo legível.
- **Configuração nova no `config.php`:** `define('REAMUR_SECRET_KEY', '…');`, a mesma linha em `catalog/config.php` e `admin/config.php`. O instalador gera e escreve a chave nos dois arquivos.
- **Atenção ao atualizar um site instalado:** nada muda sozinho. O painel passa a avisar. Para proteger:
  1. abra *Configurações → Proteção dos segredos* e copie a linha mostrada;
  2. cole a mesma linha em `catalog/config.php` e em `admin/config.php`;
  3. recarregue a tela e clique em **Proteger segredos**.
- **Atenção à cópia de segurança:** guarde a linha da chave junto do backup do banco. Sem a chave, os valores cifrados não voltam: é preciso reconectar cada vendedor no Mercado Pago e redigitar segredo do aplicativo, chave de assinatura, senha SMTP e segredos de webhook.
- **Chave errada ou perdida:** o valor conta como ausente e nunca é enviado para fora. E-mail por SMTP, renovação de token e link de pagamento são recusados com explicação; o webhook não é enviado (código 0 no registro de entregas); salvar uma tela sem redigitar não destrói o valor gravado. Restaurar a chave devolve tudo ao normal.
- **Mudança de comportamento:** `checkout/payment.notify` responde **503 com `Retry-After: 3600`** quando a chave de assinatura ou o token do vendedor não abre (antes respondia sempre 200). O Mercado Pago reenvia.
- **Troca de chave:** chave nova em `REAMUR_SECRET_KEY`, a anterior em `REAMUR_SECRET_KEY_OLD` (só abre), **Proteger segredos**, e a linha da anterior sai.
- **Banco:** `rms_webhook.secret` passa de `VARCHAR(64)` para `VARCHAR(512)`. O painel alarga a coluna sozinho; se o usuário do banco não tiver permissão de `ALTER`, a tela avisa e `install/sql/upgrade_s9_webhook_secret.sql` faz o mesmo no phpMyAdmin (`rollback_s9_webhook_secret.sql` desfaz).
- **Admin:** a chave de assinatura das notificações do Mercado Pago (`mp_webhook_secret`) entra pela tela *Mercado Pago Marketplace*; antes, só por SQL. Editar um webhook com o campo de segredo em branco mantém o atual.
- **Limite:** a chave fica no `config.php`. Protege contra quem alcança só o banco (cópia de segurança, arquivo `.sql`, phpMyAdmin, falha de leitura por SQL). Não protege contra quem lê os arquivos do site. Cópias de segurança anteriores à conversão continuam com os valores legíveis.
- **Para quem escreve código:** segredo novo no banco passa por `$this->secrets()->seal()` / `->open()`. Regra em `agents-ia/regras/02-padroes-de-codigo.md`.
- `tools/check_producao.sh`, check 18 (novo): nenhum `config.php`, cópia dele ou cache de configurações entregue como texto. O script passa a ter 18 checks.

## Segurança S8 — política de conteúdo aplicada e HTTPS obrigatório no navegador (2026-11-13)
- ADR 0093. Achado da análise, reproduzido antes da correção com navegador real: a política do site estava em modo de relato e, mesmo aplicada, aceitaria script embutido, `eval` e script de qualquer site. Um ataque simulado na página inicial executava os quatro testes; agora os quatro são bloqueados.
- **Corrigido:** `Content-Security-Policy` aplicada no site público e no admin. O navegador só executa scripts do próprio site e os blocos que o servidor marcou com um código sorteado a cada página (nonce). Sem `eval`. Estilos embutidos continuam permitidos.
- **Corrigido:** `Strict-Transport-Security` de 180 dias, só para o domínio do site, enviado quando o `HTTP_SERVER` do `config.php` começa com `https://`. Nunca em `localhost`.
- **Admin:** as 5 confirmações que ficavam em `onsubmit` / `onclick` passam por `data-rms-confirm` (`admin.js`). A tela do editor antigo de posts (CKEditor 4) é a única que mantém a regra de script frouxa, e só quando o editor antigo abre; ele deixou de consultar `cke4.ckeditor.com`.
- **Configuração nova, opcional, no `config.php`** (exemplos comentados em `install/config.php.tpl`):
  - `define('REAMUR_CSP_MODE', 'report');` chave de emergência: a política só relata no console do navegador. `'off'` desliga. O painel do admin avisa enquanto a linha existir.
  - `define('REAMUR_CSP_SOURCES', ['script-src' => 'https://cdn.exemplo.com']);` libera um serviço de fora no site público. Só endereços `https://` de site.
  - `define('REAMUR_HSTS_MAX_AGE', 15552000);` prazo do HSTS em segundos; `0` desliga.
- **Atenção ao atualizar:** se o site público carrega algo de outro endereço (estatística, fonte, mapa, chat), declare em `REAMUR_CSP_SOURCES` antes de publicar; sem isso o navegador bloqueia e registra no console (F12).
- **Atenção ao HSTS:** depois de recebido, o navegador recusa `http://` no domínio por 180 dias, mesmo com certificado vencido. Subdomínios não são afetados.
- **Para quem escreve template:** `<script>` embutido leva `nonce="{{ csp_nonce }}"` e o controller entrega `$data['csp_nonce'] = $this->csp->nonce();`. Atributos `on*=` não rodam. Regra em `agents-ia/regras/02-padroes-de-codigo.md`.
- `tools/check_producao.sh`, check 17 (novo): política aplicada com nonce no site e no login do admin; HSTS em alvo https (aviso). O script passa a ter 17 checks.
- **Sem** tabela, coluna, migration ou rota nova. Instalador sem alteração de comportamento.

## Segurança S10 + S11 — build de produção sem PHPUnit / PHPStan; aviso em dependência (2026-11-13)
- ADR 0092. Achados da análise, reproduzidos antes da correção: `tools/build_prod.sh` executado como estava e `composer audit` sobre o `composer.lock`.
- **Corrigido (S10):** a build copiava o `vendor/` de `dev/` inteiro. `prod/` levava 27 pacotes de desenvolvimento (PHPUnit, PHPStan e dependentes) e o servidor incluía 3 arquivos deles a cada requisição. Agora o Composer remove da cópia tudo que é `require-dev` e refaz o autoload. `prod/` passa de 132 MB para 97 MB; o `vendor/` de produção, de 47 para 20 pacotes.
- **Veredito da build:** reprova se sobrar PHPUnit, PHPStan, seus executáveis em `vendor/bin/` ou qualquer pacote de desenvolvimento no registro do Composer.
- **Pré-requisito novo de `tools/build_prod.sh`: Composer** no terminal em que o script roda. Sem ele, a build para antes de copiar e diz o que fazer. `COMPOSER_BIN="php /caminho/composer.phar"` aponta outro executável.
- **Corrigido (S11):** `symfony/filesystem` v7.4.21 → v7.4.23 no `composer.lock` (aviso GHSA-6prc-h956-hxjq; o pacote entra pelo AWS SDK e o CMS não o chama). `composer audit` fica sem avisos. `composer.json` não muda.
- **CI, job `composer`:** `tools/verify-build-prod.sh` gera a build e confere `prod/` contra o lock (46 verificações); `composer audit --locked` roda como aviso e não reprova o CI. `tools/verify-ci-workflow.sh` passa a 35 verificações.
- **Depois de aplicar:** rode `composer install --working-dir=dev/system/storage` para atualizar o `vendor/` local.
- **Efeito no site:** nenhum no comportamento. Nenhum arquivo PHP do CMS foi alterado. Sem tabela, coluna, migration, rota ou configuração nova.
- **Mantido por decisão do mantenedor:** os 8 pacotes de `require` que o código ainda não usa (AWS SDK, Guzzle, Mustache, Latte, scssphp). Medidas e opção de remoção na ADR 0092.
- **Pendência:** a pasta `install` do site antigo em `davidalmeida.xyz` fica para a troca de site.

## Segurança S12 — instalador fechado pelo estado real da instalação (2026-11-12)
- ADR 0091. Achado da análise, reproduzido na instalação de teste antes da correção.
- **Corrigido:** o instalador só se fechava com o arquivo `install.lock`, gravado ao abrir a página do passo 4. Com o site instalado e a trava ausente (assistente interrompido, pasta `storage` sem permissão de escrita, `config.php` criado à mão), qualquer visitante refazia a instalação com um banco dele: os `config.php` eram regravados, o dono perdia o login e o visitante entrava no admin. Agora o instalador responde 403 quando existe `install.lock` **ou** um `config.php` do site, antes de subir o núcleo.
- **Passo 3:** a trava é gravada junto com a instalação. Se ela não puder ser gravada, nada é instalado e o erro aparece no formulário (antes, a página dizia "Instalação concluída" e a falha passava em silêncio). Se a gravação de um `config.php` ou a importação do banco falhar, o que foi gravado em disco é desfeito.
- **Página de conclusão:** sai na resposta do passo 3. Recarregá-la mostra "Installation locked".
- **"Testar conexão":** limite de 5 s (antes, um destino que aceita a conexão e não responde prendia o PHP) e recusa de `;`, `=` e espaço no servidor do banco.
- **Painel inicial do admin:** aviso enquanto a pasta `install/` existir no servidor. Em `pt-br`, `en-us` e `es-eu`.
- `tools/check_producao.sh`, check 14: avisa quando `/install/` está no servidor com o instalador travado (respondia 403 e passava como ausente).
- **Efeito em site existente:** nenhum para quem tem `install.lock`. Para reinstalar, apague `install.lock` e os dois `config.php`; apagar só a trava deixa de bastar.
- Nova classe pura `Security\InstallSeal`. Sem tabela, coluna, migration, rota, configuração ou dependência nova.
- **Site antigo em produção:** o `/install/` que responde 200 em `davidalmeida.xyz` é o instalador do site antigo, fora deste repositório. Apague a pasta `install` do servidor.

## Segurança S6 + S7 — requisições de saída usam o IP conferido (2026-11-12)
- ADR 0090. Achados da análise, reproduzidos antes da correção em uma rede isolada com DNS, "site público" e "serviço interno" de teste.
- **Corrigido (S7):** a guarda contra SSRF consultava o DNS e o cURL consultava de novo. Um nome que responde um IP público na primeira consulta e `127.0.0.1` na segunda fazia o importador de blog ler um serviço interno do servidor (página de posts, redirecionamento e imagem de capa) e fazia o webhook abrir conexão nele. Agora o cURL conecta só nos endereços que a guarda conferiu (`CURLOPT_RESOLVE`), no webhook a cada tentativa e no importador a cada redirecionamento.
- **Corrigido (S6):** a guarda cortava as pontas da URL antes de conferir e aceitava URL com quebra de linha, tab ou NUL no fim. A URL passa a ser conferida crua. `MediaUpload::absolute()` confere o caminho cru.
- **Guarda mais estrita:** recusa IP em forma decimal, hexadecimal, octal ou abreviada (`2130706433`, `0x7f000001`, `0177.0.0.1`, `127.1`), `localhost.` com ponto final, e IPv6 que carrega um IPv4 interno (`::127.0.0.1`, NAT64, 6to4). Um endereço interno entre os que o DNS devolve reprova o nome inteiro.
- **Importador de blog:** resposta cortada durante o download (8 MB para a página de posts, 5 MB para a imagem); antes, uma página de 400 MB derrubava a tela com erro fatal de memória. Redirecionamento para `file://` e afins encerra na hora com `ssrf_blocked`. URL de imagem inválida vira erro registrado e a importação segue.
- **Webhook:** URL recusada pela guarda não gera novas tentativas (antes, URL com quebra de linha gastava 3 tentativas e cerca de 6 s). A linha de log sai sem bytes de controle.
- **Efeito em site existente:** webhooks e importações para destinos públicos se comportam como antes. Importação com página de posts acima de 8 MB falha com `too_big`: reduza "posts por página". Salvar um webhook continua sem consultar o DNS.
- `UrlGuard` ganha `inspect()`, `curlOptions()` e `pinLines()`; `isSafeExternalUrl()` mantém a assinatura. Código novo que conecta deve usar `inspect()` + `curlOptions()` (há teste que reprova `curl_init(` sem os dois). Sem tabela, coluna, migration, rota, configuração ou dependência nova.
- **Limite:** se o servidor sai para a internet por um proxy HTTP, quem resolve o nome é o proxy e a trava de IP não se aplica.

## Segurança S5 — IP do visitante e HTTPS atrás de proxy ou CDN (2026-11-12)
- ADR 0089. Achado da análise, reproduzido na instalação de teste com um proxy TLS na frente do site antes da correção.
- **Corrigido:** atrás de CDN ou proxy reverso, todos os visitantes chegavam ao PHP com o IP do proxy. Seis senhas erradas de qualquer pessoa trancavam o login do admin para todos por 15 minutos (inclusive para quem digitava a senha certa); cinco mensagens de um visitante fechavam o contato e o orçamento para os demais por 10 minutos; o IP gravado em mensagens e pedidos era sempre o do proxy.
- **Configuração nova, opcional, no `config.php`** (mesmos valores em `catalog/config.php` e `admin/config.php`): `define('REAMUR_TRUSTED_PROXIES', '203.0.113.0/24, 2001:db8::/32');` com os endereços ou faixas do proxy, ou `define('REAMUR_TRUSTED_PROXY_HOPS', 1);` com a quantidade de proxies na frente do site. Sem nenhuma das duas, nada muda no IP: vale quem conectou, e `X-Forwarded-For` forjado continua ignorado.
- **Atenção ao modo `REAMUR_TRUSTED_PROXY_HOPS`:** só é seguro se o servidor não responde por fora do proxy. Com ele, quem alcança o servidor direto escolhe o IP registrado e escapa do limite de tentativas. Prefira a lista quando a hospedagem publica as faixas.
- **Cookie de sessão:** sai com `Secure` quando `HTTP_SERVER` começa com `https://`, mesmo que o proxy esconda o HTTPS do PHP (antes saía sem `Secure` nesse caso). Efeito em site existente: acesso por `http://` a um site configurado em https deixa de manter sessão.
- **Painel inicial do admin, bloco Conexão:** mostra o IP atribuído a você, o endereço de quem conectou, se há HTTPS, se o cookie sai com `Secure` e o modo em vigor. Avisa quando chegam cabeçalhos de proxy sem configuração, quando a lista não contém quem conectou, quando a lista tem entrada inválida e quando o modo saltos recebe conexão sem `X-Forwarded-For`. Em `pt-br`, `en-us` e `es-eu`.
- **Instalador:** aberto por HTTPS atrás de proxy, os links e formulários saem em `https://` (antes `http://`). O `config.php` gerado traz as duas constantes como exemplo comentado.
- `tools/check_producao.sh`, check 16 (novo): cookie de sessão com `HttpOnly`, `SameSite` e, em alvo https, `Secure`. O script passa a ter 16 checks.
- Nova classe pura `Security\ClientAddress`; `Request` ganha `ip()`, `isHttps()`, `connection()` e `setProxyTrust()`; `Session` ganha a opção `secure` e `Session::cookieSecure()`. Código novo deve usar `$this->request->ip()` no lugar de `REMOTE_ADDR` (há teste que reprova a leitura direta). Sem tabela, coluna, migration, rota ou dependência nova.
- Runbook de cutover: passo 4.6b (conferir o bloco Conexão e declarar o proxy). `Contrato-Bootstrap.md` §1.1 lista as constantes opcionais.
- Não verificado: a CDN real da hospedagem (quais cabeçalhos e endereços ela entrega ao PHP), Apache/nginx com PHP-FPM, MySQL, driver `mysqli`. Proxy que informe o visitante só por `X-Real-IP`, `CF-Connecting-IP` ou `Forwarded` não é lido. O limite do login continua por IP e conta também logins corretos.
- Validação local: cenários de login, contato, orçamento, cookie e instalador com proxy TLS de teste, antes e depois, nos três modos; bateria HTTP de 41 casos da ADR 0088 idêntica linha a linha; PHPUnit `OK (429 tests, 5368 assertions)` em PHP 8.2.32 e 8.3.32 (104 testes novos em `ClientAddressTest`, 25 em `ProxyWiringTest`), PHPStan nível 2 sem erros, `php -l`, guards do repositório.

## Segurança S3 + S4 — modo produção e erro 500 (2026-11-12)
- ADR 0088. Dois achados da análise, reproduzidos numa instalação real (instalador web, MariaDB 11.8.6, PHP 8.2.32) antes da correção.
- **S3, corrigido:** `REAMUR_ERROR_DISPLAY = false` não desligava nada, porque `config/catalog.php` e `config/admin.php` fixavam `error_display = true` por cima do `default.php`. Com o modo produção ligado, senha de banco errada mostrava `Access denied for user …`, tabela ausente mostrava o SQL e o caminho do arquivo. As duas linhas saíram; `framework.php` lê a opção com padrão `false`.
- **S4, corrigido:** qualquer falha dentro de uma tela respondia "404 Página não encontrada" (uma tabela ausente derrubava a home como 404). Agora 404 fica só para URL que não corresponde a uma tela; tela que falha responde **500**.
- **Página 500 nova** (`Library\ErrorPage`): HTML curto e fixo, sem banco, tema ou template; nome do site, link para a home e um código de referência que aparece também na linha do log. Em `pt-br`, `en-us` e `es-eu`. Quem pede JSON recebe `{"success":false,"error":…,"ref":…}`.
- A resposta 500 sai com `Cache-Control: no-store` e `X-Robots-Tag: noindex`, mantém os cabeçalhos de segurança do config e descarta os cabeçalhos e a saída parcial do controller que falhou. Erro fatal do PHP (memória, código inválido) responde a mesma página.
- **Log:** a linha de erro ganha classe, arquivo e linha: `[F11E7B37] RuntimeException: … em /caminho/arquivo.php:6 — request: /…`.
- **Atenção em instalação nova:** o instalador continua gravando `define('REAMUR_ERROR_DISPLAY', true);`. Em produção, troque para `false` em `catalog/config.php` e `admin/config.php`. Enquanto estiver `true`, o painel inicial do admin mostra um aviso.
- **Instalador, corrigido:** a instalação parava no meio (erro 1267, `Illegal mix of collations`) em servidor cuja collation padrão de utf8mb4 não é `utf8mb4_unicode_ci`, caso do MariaDB 11.8. A tabela temporária de `schema-complete.sql` e `schema.sql` passou a declarar a collation.
- `tools/check_producao.sh`, check 12: reprova também vestígio de erro do PHP ou de SQL nas páginas lidas.
- `Action` ganha `prepare()` e a exceção `Engine\NotFoundException`; `Response` ganha `reset()`. `Action::execute()` mantém assinatura e mensagens. Sem tabela, coluna, migration, rota ou dependência nova.
- Não verificado: Apache/nginx com PHP-FPM (a bateria usou o servidor embutido do PHP), MySQL, driver `mysqli`, e a página 500 atrás da CDN de produção.
- Validação local: bateria HTTP de 41 casos antes e depois, nos dois modos (8 respostas 200, 11 respostas 404, 22 respostas 500); PHPUnit `OK (300 tests, 5140 assertions)` em PHP 8.2.32 e 8.3.32 (98 testes novos em `ErrorDispatchTest`, `ErrorPageTest` e `ErrorDisplayConfigTest`), PHPStan nível 2 sem erros, `php -l`, guards do repositório.

## Segurança S1 + S2 — ACL do admin e sandbox do tema publicado (2026-11-12)
- ADR 0087. Dois achados de severidade alta da análise, reproduzidos no código antes da correção.
- **S1, corrigido:** um usuário logado de um grupo restrito abria qualquer tela do admin escrevendo a rota com `..` (`?route=user/us..er` era checada como `user/us` e executada como `user/user`). `startup/permission` e o dispatcher agora leem a rota pela mesma função, `Action::parse()`. Rota com `..` deixa de ser "consertada" e dá 404 (Admin, Catalog e Install).
- **S2, corrigido:** uma fonte Twig de tema publicada lia constantes do PHP (`constant('DB_PASSWORD')`) e executava comando no servidor (`map('system')`); a importação de pacote `.reamurtheme.json` aceitava a mesma fonte. As fontes de tema passam a rodar em Twig Sandbox também no site publicado e na prévia de página do CMS, com a mesma política do canvas (nova classe `Template\ThemeSandbox`, modo estrito).
- **Salvar e importar** conferem cada fonte pelo parser do Twig. O salvar recusado informa a fonte e a linha e devolve ao formulário o que foi digitado (antes a edição se perdia).
- **No site publicado**, uma fonte fora da política não derruba a página: o template padrão entra no lugar e o motivo vai para o log do sistema (`Theme sandbox: source "…" left out…`), no máximo uma vez por hora por fonte.
- `common/header.twig` pede a barra do tema com `{{ theme_partial('partial/site_header', {…}) -}}` no lugar do `{% include … only %}`. Não troque de volta: a barra do tema deixaria de aparecer.
- **Efeito em sites existentes:** os 6 presets × 11 rotas saem byte a byte iguais (canvas e publicado; cache desligado, frio e quente). Tema próprio que use tag, filtro ou função fora da lista (`replace`, `number_format`…) perde aquele template no site até ser ajustado; a lista está em `docs/guias/Guia-Twig.md`. O canvas do editor já recusava o mesmo, exceto `block()` e `attribute()`, que ele aceitava e agora são recusadas (modo estrito).
- Sem tabela, coluna, migration, rota ou dependência nova. Dados salvos iguais. `Twig::setThemeTemplates()` ganha um segundo parâmetro opcional.
- Não verificado: instalação com banco, sessão real de admin e servidor web.
- Validação local: PHPUnit `OK (202 tests, 4809 assertions)` em PHP 8.2.32 e 8.3.32 (45 testes novos em `AdminAclRouteTest`, 58 em `ThemeSandboxPublishedTest`), PHPStan nível 2 sem erros, `php -l`, os três `tools/verify-*.sh` e `lychee 0.15.0` sem erros.

## KZ-04 — KAZEditor-Theme: selecionar um elemento dentro do trecho (2026-11-12)
- ADR 0086: clicar em um título, parágrafo, link ou botão da página do rascunho seleciona **aquele elemento** (contorno próprio e caminho clicável no painel **Trecho**). O painel **Estrutura** continua selecionando trechos; `Esc` solta primeiro o elemento, depois o trecho.
- **Texto fixo editável no painel:** o campo **Texto** reescreve só aquele texto em `templates[chave]`; o resto do código fica byte a byte igual. O que é digitado é sempre texto: `<`, `>`, `&` e `{` viram entidades, nunca HTML nem Twig.
- **Texto que vem de dado** mostra a expressão de origem (`{{ heading_title }}`) e não é editado ali. Elemento dentro de laço avisa quantas vezes aparece. Trecho padrão do sistema pede **Personalizar este trecho** antes de editar.
- **Ver no código** leva à tag do elemento no código do trecho. Com o trecho inteiro selecionado, o painel lista os textos fixos dele (caminho por teclado).
- Nova classe pura `Theme\ThemeSourceMap` (mapa de elementos de uma fonte Twig). `Twig::renderThemePage()` com `$annotate` passa a escrever `data-kz-el="CHAVE:N"` nas tags das fontes e a devolver `elements`; a anotação é descartada se mudar os tokens Twig da fonte. `design/theme.canvas` devolve `elements`. Catalog, prévia isolada e site publicado não recebem âncoras.
- Revisão documentada da ADR 0084, que havia recusado atributos `data-*`: agora a anotação é feita na fonte (não na saída) e os comentários continuam marcando os trechos. Motivos na ADR 0086.
- **Correção com efeito em sites publicados:** em `catalog/view/css/theme.css`, `--accent` passa a ser resolvido no `body` e segue a cor **Primária** do tema (antes ficava sempre `#6d28d9`; achado da ADR 0085). Sites nos templates padrão com outra cor Primária mudam de cor em botões, links e destaques. `--bg`/Superfície tem o mesmo defeito e **não** foi alterado.
- Dados salvos iguais: nenhum controle novo tem `name`; sem tabela, coluna, migration, rota, dependência ou entrada nova em `design/theme.canvas`.
- Validação local: PHPUnit `OK (99 tests, 4184 assertions)` em PHP 8.2.32 e 8.3.32 (13 testes novos em `KazElementSelectTest`; 3 de `KazPointEditTest` ajustados), PHPStan nível 2 sem erros, `php -l`, `node --check`, os três `tools/verify-*.sh` e `lychee 0.15.0 --offline` limpos; fluxo conferido em Chromium headless nos seis pacotes de `docs/temas/` (mouse, teclado e toque). Sem banco, sem sessão real de admin, sem Firefox/Safari.

## KZ-03 — KAZEditor-Theme: tokens ao vivo (2026-11-12)
- ADR 0085: o painel direito do módulo **Página** ganha a aba **Design**. Cores do tema, os 22 tokens (tipografia, espaçamento e forma, cores claro/escuro) e as variáveis que o próprio tema declara são editados ao lado da página; a mudança aparece na hora e o render do servidor confirma em seguida.
- **Variáveis deste tema:** nova classe pura `Theme\ThemeLiveTokens` lê os `<style>` das fontes do tema e oferece controle para cada variável com cor `#rrggbb` ou tamanho em `px`. Mudar um controle reescreve só aquele valor em `templates[chave]`. É o que faz a aba funcionar nos pacotes desenhados (`Atlas-Studio`, `Atlas-Studio-3D`, `David-Creator`, `David-Universe-3D`), que não leem os tokens do sistema.
- Selo **não usado** nos valores que não mudam nada na página aberta (medido no navegador, trocando uma variável por vez).
- Seletor **Claro / Escuro** na barra do canvas: `design/theme.canvas` aceita `canvas_scheme` e devolve a página com `data-bs-theme="dark"`. Preferência local; não é salva no tema.
- O canvas passa a dizer **por que** voltou ao rascunho salvo (`form_error`) e lista os sete pares de contraste com a razão medida (`contrast`). Antes, um contraste abaixo de 4,5:1 fazia a página parar de atualizar sem explicação.
- Dados salvos iguais: nenhum controle novo tem `name`; tudo é escrito nos campos que já existiam (`primary`, `surface`, `tokens[…]`, `templates[…]`). Sem tabela, coluna, migration, rota ou dependência.
- Desvio documentado da ADR 0084: o editor passa a escrever propriedades personalizadas CSS (nome e valor validados) no documento do quadro para a prévia imediata. O `sandbox` dos quadros não muda.
- Achado, não corrigido: em `catalog/view/css/theme.css`, `--accent` é resolvido em `:root` e não enxerga `--theme-primary` (definido no `<body>`); a cor Primária do módulo Geral não alcança as regras que usam `--accent`. Detalhe na ADR 0085.
- Validação local: PHPUnit `OK (86 tests, 1794 assertions)` em PHP 8.2.32 e 8.3.32 (9 testes novos em `KazLiveTokensTest`), PHPStan nível 2 sem erros, `php -l`, `node --check`, os três `tools/verify-*.sh` e `lychee 0.15.0 --offline` limpos; fluxo conferido em Chromium headless nos seis pacotes de `docs/temas/`. Sem banco, sem sessão real de admin, sem Firefox/Safari.

## KZ-02 — KAZEditor-Theme: apontar e editar (2026-11-12)
- ADR 0084: clicar em um trecho da página do rascunho seleciona a fonte Twig que o gerou. Painel **Estrutura** (trechos da página, com origem "Deste tema" / "Padrão do sistema") e painel **Trecho selecionado** (arquivo, tamanho em cada quadro, código editável, "Personalizar este trecho", "Voltar ao padrão do sistema" com desfazer). Nas seções da página inicial: subir, descer, ocultar e mostrar.
- `Twig::renderThemePage()` ganha `bool $annotate` (padrão `false`): cerca a saída de cada fonte allowlistada com comentários `<!--kz:b:CHAVE-->`…`<!--kz:e:CHAVE-->` e devolve `defaults` (fonte padrão das partes da página que o tema não redefine). Só `design/theme.canvas` usa; Catalog, prévia isolada e site publicado não recebem marcadores.
- Dados salvos iguais: o painel escreve nos campos que já existiam (`templates[chave]`, `sections[]`, `sections_order`). Nenhum campo novo no `POST`; sem tabela, coluna, migration, rota ou dependência.
- Barra de módulos reorganizada por uso: Herança, Three.js, Cena 3D, Mídia, Modelos, Agendamento, Sites, Condições e Prévia ficam em **Configurações**. Os módulos em si não mudaram.
- Correção: um aviso do PHP durante o render do canvas vazava para dentro da página; agora vira erro de template com fallback, como no Catalog (conversão restrita à execução de `renderThemePage()`).
- Correção de preset: `docs/temas/Reamur-Reference.reamurtheme.json` imprimia uma lista como texto em `catalog/project_info` (`{{ img }}` → `{{ img.src }}`), o que derruba a página de projeto no Catalog; `checksum` do pacote reemitido.
- Limpeza: removidos da raiz os arquivos de pacotes anteriores commitados por engano (`copiar-para-o-repositorio/`, `kz-01-canvas-pagina-real.patch`, `ci-fix-phpunit.patch`, `LEIA-ME.txt`, `MANIFESTO.txt`). A cópia do índice de ADRs dentro daquela pasta tinha 17 links relativos quebrados e deixava o job `docs-links` vermelho.
- Validação local: PHPUnit `OK (77 tests, 1476 assertions)` em PHP 8.2.32 e 8.3.32 (7 testes novos em `KazPointEditTest`), PHPStan nível 2 sem erros, `php -l`, `node --check`, os três `tools/verify-*.sh` e `lychee 0.15.0 --offline` limpos; fluxo conferido em Chromium headless (mouse, teclado e toque). Sem banco, sem sessão real de admin, sem Firefox/Safari.

## KZ-01 — KAZEditor-Theme abre na página real do rascunho (2026-11-12)
- ADR 0083: o editor de temas passa a abrir no módulo **Página**, que mostra a página inteira do rascunho (cabeçalho do tema + rota + rodapé) em três quadros — desktop 1440, tablet 768 e celular 390 — sobre um canvas com zoom e deslocamento livres. 11 rotas selecionáveis e 3 idiomas.
- O canvas reflete o formulário **antes de salvar**: nova ação somente leitura `design/theme.canvas` (`POST` + CSRF + ACL `access`) e método aditivo `Twig::renderThemePage()`, em Twig Sandbox, com todas as fontes do tema sobre os templates padrão. Nada é gravado; sem tabela, coluna, migration ou chave de configuração nova.
- Uma fonte Twig quebrada não apaga mais a prévia: o template padrão entra no lugar e o erro aparece com template e linha.
- Nova classe pura `Theme\ThemeCanvasSample` com os dados de amostra. O board SVG (`kaztheme-scene/v1`) continua igual, agora como módulo **Cena decorativa**.
- Correção: os atalhos de cena (`Backspace`, `Delete`, `R`, `T`…) agiam com qualquer módulo aberto e podiam apagar ou criar camadas sem a cena estar visível; agora só valem no módulo da cena.
- Desvio documentado da ADR 0080: os quadros usam `sandbox="allow-same-origin"` (sem `allow-scripts`) para medir a altura da página; a prévia antiga segue com `sandbox=""`.
- Validação local: PHPUnit `OK (70 tests, 1268 assertions)` em PHP 8.2.32 e 8.3.32 (8 testes novos em `KazCanvasPageTest`), PHPStan nível 2 sem erros, `php -l`, `node --check` e os três `tools/verify-*.sh` limpos; fluxo conferido em Chromium headless com os 6 pacotes de `docs/temas/`. Sem banco, staging, deploy ou escrita no GitHub.

## F11-12 — `docs-links` determinístico com `--offline` (2026-11-02)
- O `docs-links` seguiu `failure` (`exit 2`) em três runs mesmo após F11-10, embora a correção desse `exit 0` localmente com o binário `lychee 0.15.0` idêntico. Descartadas as hipóteses de `--mode task`, `--format/--output` e réplica incompleta; a causa exata exige logs de admin (403). A única diferença restante é a rede do runner.
- Decisão do responsável (opção A): `args` passa a usar **`--offline`**, validando apenas links internos/relativos. `fail`/`failIfEmpty=true`, `lycheeVersion: v0.15.0`, `timeout-minutes: 20` e as 5 exclusões originais permanecem; `--accept` e as 3 exclusões de domínios mortos foram removidos por serem inócuos neste modo.
- Trade-off explícito: **links externos deixam de ser verificados** pelo CI; links locais quebrados continuam derrubando o job.
- `verify-ci-workflow.sh` atualizado (28 PASS / 0 FAIL): exige `--offline`, proíbe `fail: false` e `--accept`, mantém `docs/README.md` no disco. Validação local: `exit 0 / 0 erros / 449 OK`.
- 5 manifestos de integridade reemitidos com hashes do estado final. Sem alteração de código PHP/JS, migration, banco, staging, deploy ou escrita no GitHub.

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
- Ver `docs/threejs/THREEJS_THUMB.md` para esquema, limites e verificações.

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
