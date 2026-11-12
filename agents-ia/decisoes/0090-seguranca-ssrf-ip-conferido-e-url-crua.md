# ADR 0090 — Segurança: requisições de saída usam o IP conferido; URL e caminho conferidos crus

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S6 + S7, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Achados S6 e S7 da análise do repositório, reproduzidos em `f85bedb` antes de qualquer mudança, com o `Import` e o `Webhook\Dispatcher` reais do repositório em uma rede isolada (DNS, "site público" e "serviço interno" de teste). Complementa a ADR 0037 (hardening) e a ADR 0033 (webhooks). |

## O que acontecia

O CMS faz requisições de saída em dois pontos em que o destino vem de fora: o envio de webhooks (URL cadastrada no admin) e o importador de blog (URL do site de origem digitada no admin; URL da imagem de capa e redirecionamentos vindos do site remoto). Os dois passam por `Security\UrlGuard`, que recusa destinos internos.

**S7. A guarda consultava o DNS e o cURL consultava de novo.** Quem controla o DNS de um nome responde um IP público na primeira consulta e `127.0.0.1` na segunda. Medido com um nome assim (`int` = requisições que o serviço interno recebeu):

| Caso | int | Resultado antes |
| --- | --- | --- |
| Importador, nome que muda de IP | 1 | importou um "post" com o conteúdo do serviço interno |
| Importador, site público que redireciona para esse nome | 1 | idem |
| Imagem de capa nesse nome | 1 | baixou a imagem do serviço interno |
| Webhook para esse nome | 1 | conexão aberta em `127.0.0.1:443`; o TLS falhou porque o certificado não era do nome, e o corpo não foi entregue |
| Importador, mesmo nome com ponto final | 1 | como o primeiro |

**S6. A guarda cortava as pontas antes de conferir.** `trim()` remove `\0`, `\n`, `\r` e tab; a conferência via a URL limpa e o chamador seguia com a original. Também passavam formas de escrever um IP interno que a guarda não reconhecia.

| Entrada | Antes |
| --- | --- |
| URL com `\n`, tab ou NUL no fim, ou espaço no início | aceita pela guarda |
| `2130706433`, `0x7f000001`, `0177.0.0.1`, `127.1`, `0`, `localhost.` | aceitas ao salvar um webhook (modo sem DNS); o cURL conecta em `127.0.0.1` |
| `[::127.0.0.1]`, `[64:ff9b::7f00:1]`, `[2002:7f00:1::]` | aceitas nos dois modos |
| Redirecionamento para `file:///etc/passwd` | tratado como caminho relativo: 4 requisições ao site remoto até `too_many_redirects` |
| Imagem de capa com NUL no fim | `ValueError` do `curl_init()` sem tratamento |
| Webhook com `\n` no fim | 3 tentativas e cerca de 6 s até desistir, sem registro de recusa |
| Página de posts de 400 MB (com ou sem tamanho declarado) | `Fatal error: Allowed memory size … exhausted` |
| Imagem de capa de 6 MB | baixada inteira e só então descartada |
| `MediaUpload::absolute("a.png\0")` | devolvia o caminho do arquivo limpo |

Os dois pontos originais do S6 (`MercadopagoMarketplace::isHttpUrl` e `MediaUpload::guard`) já tinham sido corrigidos na etapa do CI.

## Decisão

1. **Quem conecta usa o que a guarda conferiu.** `UrlGuard::inspect()` devolve o destino conferido, com os endereços aprovados. `UrlGuard::curlOptions()` entrega esses endereços ao cURL em `CURLOPT_RESOLVE`: o cURL não consulta o DNS de novo. O nome continua valendo para o certificado TLS e para o cabeçalho `Host`. Vale no webhook (a cada tentativa) e no importador (a cada salto de redirecionamento).
2. **Um endereço interno reprova o nome inteiro.** Se o DNS devolve um IP público e um interno, o nome é recusado. No máximo 8 endereços seguem para o cURL; todos os devolvidos são conferidos.
3. **A URL é conferida crua.** Qualquer byte de `\x00` a `\x20` ou `\x7f`, em qualquer posição, recusa. Quem recebe texto de formulário continua limpando as pontas antes de chamar a guarda (o cadastro de webhook e o importador já fazem isso).
4. **IP só na forma canônica.** Host cujo último rótulo é só dígitos ou começa com `0x` é recusado: são as formas decimal, hexadecimal, octal e abreviada de um IP. `localhost.` é `localhost`.
5. **IPv6 que carrega IPv4 segue o IPv4 embutido.** NAT64 `64:ff9b::/96` e 6to4 `2002::/16` valem como públicos só quando o IPv4 de dentro é público. São recusados `::/96`, `64:ff9b:1::/48`, Teredo `2001::/32`, `100::/64`, `2001:db8::/32`, `ff00::/8`; no IPv4, `192.0.0.0/24`, as três faixas de documentação e `224.0.0.0/4`.
6. **Redirecionamento:** `Location` com esquema diferente de `http` ou `https` encerra a requisição com `ssrf_blocked`.
7. **Importador:** a resposta é cortada durante o download, em 8 MB para a página de posts e 5 MB para a imagem. A URL da capa tem as pontas limpas antes de ser conferida. `httpGet()` não lança: URL que o cURL recusa vira erro de retorno, e a importação segue para o próximo post.
8. **Webhook:** URL recusada pela guarda não gera novas tentativas. A linha de log sai sem bytes de controle e com no máximo 255 bytes.
9. **`MediaUpload::absolute()`** confere o caminho cru, como `guard()`.
10. **Salvar um webhook não consulta o DNS** (decisão do mantenedor). Um webhook para um nome que resolve para dentro é salvo; cada envio é recusado e registrado em log.

### API

```php
$target = UrlGuard::inspect($url, $requireHttps);   // null = recusado
if ($target === null) { /* registrar e sair */ }

curl_setopt_array($ch, UrlGuard::curlOptions($target) + [ /* opções do chamador */ ]);
```

`curlOptions()` fixa `CURLOPT_PROTOCOLS` (http e https), `CURLOPT_FOLLOWLOCATION` em falso e `CURLOPT_RESOLVE`. `isSafeExternalUrl()` continua existindo, com a mesma assinatura, e passa a ser `inspect(...) !== null`. Código novo que conecta deve usar `inspect()` + `curlOptions()`: há teste que reprova `curl_init(` em `dev/` sem os dois (exceção: `mercadopago_marketplace.php`, que só fala com o host fixo da API).

libcurl anterior a 7.59.0 descarta a linha de `CURLOPT_RESOLVE` que traz mais de um endereço e volta a consultar o DNS. Nessas versões `pinLines()` envia só o primeiro endereço.

## Efeito em sites existentes

- Webhooks e importações para destinos públicos se comportam como antes. Os 12 casos de "site público" da bateria deram resultado idêntico antes e depois: http, https, nome em maiúsculas, com ponto final, com porta, só IPv6, IPv4 + IPv6, dois IPs com o primeiro fora do ar, IP direto (v4 e v6), imagem de capa e webhook assinado.
- Webhook já cadastrado com IP em forma não canônica, `localhost.` ou byte de controle na URL deixa de ser enviado (já era recusado ou falhava no envio) e não pode mais ser salvo assim.
- Importação cuja página de posts passa de 8 MB falha com `too_big`. Reduza "posts por página".
- Sem tabela, coluna, migration, rota, configuração ou dependência nova.

## Verificação

- **Bateria em rede isolada, antes e depois** (32 casos, um processo por caso, com as classes reais do repositório): o serviço interno recebeu requisição em 5 casos antes e em nenhum depois. Os casos de nome que muda de IP passam a ser atendidos pelo endereço público conferido.
- Página de 400 MB: `too_big` com pico de 4 MB de memória (tamanho declarado) e 16 MB (sem tamanho declarado); antes, erro fatal. Imagem de 6 MB: `too_big` sem baixar o corpo.
- `tests/Unit/Security/UrlGuardPinTest.php`: 85 testes, sem rede (o DNS é injetado em `inspect()`).
- Mutação: 20 alterações que desfazem cada parte da correção, todas reprovadas pela suíte.
- PHPUnit em PHP 8.2 e 8.3: 514 testes. PHPStan sem erros.

A bateria usa `unshare`, um DNS falso e as portas 53, 80 e 443 dentro de um namespace de rede. Ela não roda no CI; o que roda no CI são os testes unitários.

## Limites

- **Proxy de saída.** Se o servidor sai para a internet por um proxy HTTP (variáveis `http_proxy` / `https_proxy`), quem resolve o nome é o proxy, e `CURLOPT_RESOLVE` não se aplica. Não tratado e não exercitado.
- **Não exercitado:** o PHP e a libcurl da hospedagem de produção; libcurl anterior a 7.59 (só o teste unitário de `pinLines()`; a bateria rodou com libcurl 8.20.0); um DNS hostil de verdade na internet.
- A conexão passa a depender dos endereços que o resolvedor do PHP devolve (`dns_get_record`, com `gethostbynamel` como reserva). Antes, o cURL fazia a própria consulta.
- `MercadopagoMarketplace` (host fixo) e o envio de e-mail por SMTP (servidor configurado pelo admin) não passam pela guarda.
- O importador mostra o código do erro (`too_big`, `ssrf_blocked`) na mensagem do admin, sem texto traduzido.
