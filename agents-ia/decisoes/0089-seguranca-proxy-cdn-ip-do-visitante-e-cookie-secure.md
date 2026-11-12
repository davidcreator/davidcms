# ADR 0089 — Segurança: IP do visitante e HTTPS atrás de proxy ou CDN

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S5, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Achado S5 da análise do repositório, reproduzido em `70d9840` na instalação de teste (instalador web, MariaDB 11.8.6, PHP 8.2.32) com um proxy TLS na frente do site, antes de qualquer mudança. Complementa as ADRs 0037 (hardening) e 0088 (modo produção). |

## O que acontecia

O site de produção responde por trás da CDN da hospedagem (`server: hcdn`). Atrás de um proxy, o PHP recebe a conexão do proxy: `REMOTE_ADDR` é o endereço dele, igual para todos os visitantes, e `$_SERVER['HTTPS']` pode vir vazio mesmo com o visitante em HTTPS. O código lia os dois direto.

Medido com um proxy TLS em `127.0.0.1:8443` repassando para o site em HTTP, visitantes A e B em endereços diferentes:

| Cenário | Resultado antes |
| --- | --- |
| A erra a senha do admin 6 vezes; B entra com a senha **certa** | B recebe "Too many login attempts. Wait 15 minutes". Um contador só: `login / 127.0.0.1 / 7` |
| A envia 5 mensagens de contato; B envia a primeira | B recebe "Too many attempts". Contador `contact / 127.0.0.1 / 6`; IP gravado nas mensagens: sempre `127.0.0.1` |
| A envia 5 pedidos de orçamento; B envia o primeiro | B bloqueado; IP gravado nos pedidos: sempre `127.0.0.1` |
| Cookie de sessão pelo endereço HTTPS | `RMSSESSION=…; path=/; HttpOnly; SameSite=Lax`, **sem `Secure`** (idem `RMSADMIN`) |
| Instalador aberto pelo endereço HTTPS | formulário e links do passo 1 em `http://…`; o passo 3 já sugeria `https://…` |

Ou seja: atrás de CDN, seis senhas erradas de qualquer pessoa trancavam o login do admin para todos por 15 minutos, e cinco mensagens de um visitante fechavam o contato e o orçamento para os demais por 10 minutos.

Sem proxy o comportamento estava certo, inclusive com `X-Forwarded-For` forjado (ignorado). Isso foi mantido.

## Decisão

### 1. Um ponto de leitura

`Reamur\System\Library\Security\ClientAddress` (classe pura, recebe o array do servidor) resolve o IP do visitante e o esquema da conexão. `Request` ganha:

- `ip(): string` — IP do visitante;
- `isHttps(): bool` — a conexão do visitante é HTTPS;
- `connection(): ClientAddress` — a leitura completa (para o diagnóstico);
- `setProxyTrust(string|array $trusted, int $hops = 0)` — chamado uma vez pelo `framework.php`.

Passam a usar `Request::ip()`: limite de tentativas do login, do contato e do orçamento; IP gravado em mensagem de contato, pedido de orçamento e pedido criado pelo admin. Passam a usar `Request::isHttps()`: cookie de sessão e a condição `https` das regras de tema. O teste `ProxyWiringTest` reprova qualquer arquivo de `dev/` que volte a ler `REMOTE_ADDR` ou `X-Forwarded-For` por conta própria.

### 2. Confiança declarada por instalação, no `config.php`

`X-Forwarded-For` e `X-Forwarded-Proto` são texto que qualquer cliente escreve. Só valem quando a instalação diz de quem aceitar. Duas constantes opcionais, com os mesmos valores em `catalog/config.php` e `admin/config.php`:

```php
// endereços e faixas dos proxies (texto separado por vírgula, espaço ou ponto e vírgula; ou array)
define('REAMUR_TRUSTED_PROXIES', '203.0.113.0/24, 2001:db8::/32');

// ou: quantos proxies ficam na frente do site
define('REAMUR_TRUSTED_PROXY_HOPS', 1);
```

| Modo | Quando | Regra |
| --- | --- | --- |
| Direto | nenhuma das duas (padrão) | IP = quem conectou; HTTPS = o que o servidor vê. Igual a antes |
| Lista | `REAMUR_TRUSTED_PROXIES` com pelo menos uma entrada válida | Os cabeçalhos só valem se quem conectou está na lista. `X-Forwarded-For` é lido da direita para a esquerda; o primeiro endereço que não é proxy confiável é o visitante. O que estiver à esquerda dele foi escrito pelo visitante e é descartado |
| Saltos | só `REAMUR_TRUSTED_PROXY_HOPS` > 0 | O visitante é o N-ésimo endereço contando da direita. Vale para qualquer conexão |

A lista ganha dos saltos quando as duas existem. `X-Forwarded-Proto` (primeiro valor) decide o HTTPS apenas quando os cabeçalhos valem.

**O modo saltos só é seguro se o servidor não puder ser alcançado por fora do proxy.** Nele, quem conecta direto no servidor escolhe o IP que o sistema registra e escapa do limite de tentativas trocando o cabeçalho a cada pedido (medido: 7 senhas erradas, 7 contadores de valor 1). Ele existe porque parte das hospedagens compartilhadas não publica as faixas da CDN. O exemplo no `config.php`, o painel e o runbook dizem isso.

Proteções da lista: `/0` é recusado (confiar em todo mundo equivale a não ter lista); entrada que não é endereço nem faixa é ignorada e relatada no painel; lista só com entradas inválidas não vira confiança (fica o modo direto). IPv4 embutido em IPv6 (`::ffff:a.b.c.d`) é tratado como IPv4. Entrada ilegível no `X-Forwarded-For` interrompe a leitura no último endereço bom. No máximo 32 entradas da cadeia são lidas. O resultado é sempre um endereço validado, de até 39 caracteres (as colunas `ip` são `VARCHAR(40)`).

Por que no `config.php` e não numa tela do admin: a regra precisa valer antes do login (é ela que protege o login), e um erro nela pela tela poderia trancar o próprio admin.

### 3. Cookie de sessão

`Session::cookieSecure(HTTP_SERVER, $request->isHttps())`: o cookie sai com `Secure` quando o endereço configurado do site começa com `https://` **ou** quando a conexão é HTTPS. A primeira regra não depende do proxy: site publicado em https entrega o cookie com `Secure` mesmo sem nenhuma constante de proxy. `Session` aceita a opção `secure`; sem ela mantém o teste antigo de `$_SERVER['HTTPS']`.

### 4. Instalador

O instalador roda antes de existir `config.php`, então não tem lista de confiança. Ele usa, para montar os próprios links, a mesma regra que o passo 3 já usava para sugerir o endereço do site: `HTTPS` ligado, porta 443 ou `X-Forwarded-Proto: https` (`ClientAddress::looksHttps()`, uso restrito ao instalador). Um cabeçalho forjado ali só muda os links da resposta de quem o enviou. O `config.php` gerado traz as duas constantes como exemplo **comentado**: instalação nova não confia em proxy nenhum.

### 5. Diagnóstico no painel

O bloco **Conexão** do painel inicial do admin mostra: IP atribuído ao visitante, endereço de quem conectou, HTTPS (não / visto pelo servidor / informado pelo proxy), cookie com ou sem `Secure`, e o modo em vigor. Avisos no topo:

- chegaram cabeçalhos de proxy e nada está configurado;
- a lista existe, chegaram cabeçalhos, e quem conectou não está nela;
- a lista tem entradas inválidas (mostradas);
- modo saltos e a conexão chegou sem `X-Forwarded-For` (o servidor responde por fora do proxy).

É o jeito de descobrir o que a hospedagem entrega ao PHP sem acesso ao servidor.

### 6. Verificação remota

`tools/check_producao.sh` ganha o check 16: nas respostas do site e da tela de login, o cookie de sessão precisa de `HttpOnly` e `SameSite`, e de `Secure` quando o alvo é https.

## Efeito em sites existentes

- Sem as constantes, IP e limite de tentativas ficam exatamente como antes. A bateria de 41 casos da ADR 0088 saiu idêntica, linha a linha, nos dois modos de exibição de erro, com e sem lista.
- Uma mudança vale sem configuração: site cujo `HTTP_SERVER` começa com `https://` passa a emitir o cookie de sessão com `Secure`. Quem acessa esse site por `http://` deixa de manter sessão (o navegador não devolve o cookie). É o comportamento desejado para um site publicado em https; instalação local em `http://` não muda.
- Sem tabela, coluna, migration, rota ou dependência nova.

## Verificação

Com o proxy de teste, depois da mudança:

| Cenário | Sem configuração | Lista (`127.0.0.1`) | Saltos (`1`) |
| --- | --- | --- | --- |
| A erra 6 vezes; B entra com a senha certa | B bloqueado (como antes) | B entra; contadores `A=7`, `B=1` | B entra; contadores `A=7`, `B=1` |
| A troca `X-Forwarded-For` a cada pedido, atrás do proxy | bloqueado na 7ª | bloqueado na 7ª; contador no IP real de A | bloqueado na 7ª; contador no IP real de A |
| A troca `X-Forwarded-For` a cada pedido, direto no servidor | bloqueado na 7ª | bloqueado na 7ª | **não bloqueado** (limite conhecido do modo) |
| Contato e orçamento: A envia 5; B envia o primeiro | B bloqueado (como antes) | B envia; IP gravado de cada um | B envia; IP gravado de cada um |
| Cookie pelo endereço HTTPS | sem `Secure` se `HTTP_SERVER` é http; com `Secure` se é https | com `Secure` | com `Secure` |
| Cookie por HTTP direto com `X-Forwarded-Proto: https` forjado | sem `Secure` | sem `Secure` | com `Secure` (inofensivo: só afeta quem forjou) |
| Instalador pelo endereço HTTPS | 5 links / ações em `https://`, nenhum em `http://` | — | — |

- PHPUnit: `ClientAddressTest` (104) e `ProxyWiringTest` (25). Onze alterações propositais do código (aceitar cabeçalho de qualquer conexão, ler a cadeia pela esquerda, não parar no primeiro endereço que não é proxy, aceitar `X-Forwarded-Proto` de qualquer um, saltos ganhar da lista, contar um salto a mais, cookie ignorar `HTTP_SERVER`, `Request` ignorar a confiança, `Session` ignorar a opção `secure`, o login voltar a ler `REMOTE_ADDR`, o framework não declarar a confiança) fazem pelo menos um teste falhar.
- Painel conferido por HTTP com sessão de admin: os quatro avisos apareceram nas condições previstas e nenhum aparece em instalação sem proxy; o bloco foi lido em `pt-br`, `en-us` e `es-eu`; entrada inválida com marcação HTML sai escapada.
- `check_producao.sh` check 16 conferido contra a instalação de teste: reprova sem configuração pelo endereço HTTPS, aprova com a lista.

## Limites

- **Não exercitado atrás da CDN real da hospedagem.** Não se sabe quais cabeçalhos e endereços a `hcdn` entrega ao PHP nem se ela publica faixas; o bloco **Conexão** do painel existe para responder isso na própria instalação. O proxy de teste acrescenta o endereço de quem conectou ao `X-Forwarded-For` (como nginx e a maioria das CDNs); um proxy que **substitua** o cabeçalho também funciona; um que use só outro cabeçalho (`X-Real-IP`, `CF-Connecting-IP`, `Forwarded`) não é lido.
- Não exercitado: Apache/nginx com PHP-FPM (a bateria usou o servidor embutido do PHP), MySQL, driver `mysqli`. O pedido criado pelo admin foi conferido por teste de código, não por HTTP.
- O limite de tentativas do login continua contando também os logins corretos e continua por IP apenas (sem contador por usuário). Visitantes atrás do mesmo NAT ou da mesma rede móvel dividem o contador. Fora do escopo do S5.
- Trocar o proxy de endereço sem atualizar a lista devolve a instalação ao comportamento antigo (todos como um IP). O painel avisa; nada tranca além do que já trancava.
- No momento da entrega, o domínio de produção ainda responde com o cookie `PHPSESSID` do sistema anterior (`secure`, sem `HttpOnly` e sem `SameSite`): o check 16 reprova lá até o cutover.
