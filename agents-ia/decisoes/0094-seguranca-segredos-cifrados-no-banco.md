# ADR 0094 — Segredos cifrados no banco, com a chave no `config.php`

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S9, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Achado S9 da análise do repositório, reproduzido em `884ba37` antes de qualquer mudança, em um ambiente local com um Mercado Pago e um servidor de e-mail de teste. Revisa o item 6 da ADR 0034 (tokens em texto legível como risco aceito). Usa a ADR 0042 (alteração de banco por arquivo `upgrade_*.sql`, sem migration automática) e a ADR 0090 (o webhook só conecta em endereço conferido). |

## O que foi encontrado

Seis valores que dão acesso a serviços de fora estavam gravados no banco exatamente como foram digitados ou recebidos:

| Onde | O que é | O que permite a quem ler |
| --- | --- | --- |
| `rms_mp_seller.access_token` | Token de acesso do vendedor no Mercado Pago | Criar cobranças e consultar pagamentos em nome do vendedor |
| `rms_mp_seller.refresh_token` | Token de renovação | Obter tokens de acesso novos, por meses |
| `rms_setting` · `mp_client_secret` | Segredo do aplicativo no Mercado Pago | Agir como o aplicativo da plataforma |
| `rms_setting` · `mp_webhook_secret` | Chave que confere a assinatura das notificações de pagamento | Forjar uma notificação com assinatura válida |
| `rms_setting` · `mail_smtp_password` | Senha da conta de e-mail | Enviar e-mail como o site |
| `rms_webhook.secret` | Segredo de cada webhook de saída | Forjar um evento com assinatura válida para o sistema que recebe |

Reprodução: um token copiado do banco, usado direto contra o Mercado Pago de teste, foi aceito. O arquivo de cache das configurações (`system/storage/cache/cache.setting.config`) trazia o segredo do aplicativo em texto legível.

As telas do admin já não mostravam nenhum desses valores. O alcance do problema é quem lê o banco sem passar pelo admin: uma cópia de segurança guardada fora do servidor, um arquivo `.sql` exportado, o phpMyAdmin da hospedagem, uma falha de leitura por SQL em qualquer tela.

A ADR 0034 registrou esse estado como risco aceito, com o argumento de que cifrar com a chave em um arquivo ao lado do código seria proteção só na aparência, e deixou a cifragem para depois. Esta ADR faz a cifragem e declara, em **Limites**, exatamente contra o que ela vale.

## Decisão

### 1. Uma cifra, em um ponto

`Security\SecretBox` (`system/library/security/secret_box.php`), registrada como serviço `secret_box` e alcançada em controllers e models por `$this->secrets()`.

- AES-256-GCM (extensão OpenSSL do PHP). Cada valor leva um sorteio próprio (nonce de 12 bytes): o mesmo segredo gravado duas vezes gera dois textos diferentes.
- Formato gravado: `rmsenc:v1:` + base64 de nonce, selo de integridade (16 bytes) e dados. O prefixo distingue valor cifrado de valor legível antigo.
- **Cada valor fica preso ao lugar dele.** O nome do lugar (`mp_seller.access_token`, `webhook.secret`, `setting.<chave>`) entra na conta do selo. Um valor cifrado copiado de uma coluna para outra não abre.
- Valor alterado no banco, mesmo em um caractere, não abre.

### 2. A chave fica no `config.php`

```php
define('REAMUR_SECRET_KEY', '…44 caracteres…');
```

- 32 bytes aleatórios em base64, gerados pelo próprio CMS. Uma frase, uma chave de outro tamanho ou com caractere estranho é recusada, e o painel avisa. Chave fraca não é aceita em silêncio.
- **A mesma linha em `catalog/config.php` e `admin/config.php`.** O admin grava; o site público lê (confirma pagamentos, dispara webhooks, envia e-mail).
- A chave vai da constante direto para a cifra. Não entra no `Config`, não aparece em `var_dump` nem em rastro de erro.
- O instalador gera uma chave por instalação e a escreve nos dois arquivos. Site novo já grava cifrado.

### 3. Gravar cifrado, ler os dois formatos, abrir só onde o valor é usado

| Valor | Cifrado ao gravar em | Aberto em |
| --- | --- | --- |
| Tokens do vendedor | `admin/model/setting/marketplace.php` (`upsertSeller`, `rotateTokens`) | Os models do admin e do catálogo que entregam o vendedor para uso |
| `mp_client_secret`, `mp_webhook_secret`, `mail_smtp_password` | `admin/model/setting/setting.php` (`editSettings`) | `startup/setting` (senha SMTP, só na memória da requisição) e os dois controllers do Mercado Pago |
| Segredo de webhook | `admin/model/setting/webhook.php` | `getHooksForEvent()` do admin e do catálogo, que alimenta o envio |

- Valor legível antigo continua sendo lido como sempre. Nada precisa ser convertido para o site seguir funcionando.
- O que já está cifrado nunca é cifrado de novo, nem quando a chave atual não o abre.
- A lista de vendedores e as telas não recebem token nem texto cifrado.
- O cache das configurações guarda o que está no banco: depois da conversão, texto cifrado.

### 4. O admin só cifra se o catálogo conseguir abrir

Antes de cifrar, o admin lê `catalog/config.php` (sem executá-lo) e compara a chave. Se o arquivo não tem a chave, tem outra ou não pôde ser lido, o admin grava legível, como antes, e avisa. Cifrar com uma chave que o site público não tem pararia a confirmação dos pagamentos.

### 5. "Proteger segredos"

Seção **Proteção dos segredos** em *Configurações*:

- situação da chave, em uma frase;
- quando a chave falta ou é inválida, uma linha `define(...)` recém-gerada, pronta para colar nos dois arquivos (só para quem pode alterar configurações). **A chave em uso nunca é exibida;**
- quantos valores há em cada grupo: cifrados, legíveis, da chave anterior, que não abrem;
- o botão **Proteger segredos**, que cifra o que está legível e regrava com a chave atual o que foi cifrado com a anterior. Pode ser repetido. O que não abre fica como está, não é apagado.

O painel inicial mostra um aviso enquanto houver algo a fazer. Com a chave ausente e nenhum segredo gravado, fica em silêncio.

### 6. Sem a chave, o sistema segue como antes

Decisão do mantenedor. Um site que atualiza o código e ainda não colou a chave continua gravando e lendo valores legíveis; o painel avisa até a chave entrar e o botão ser usado.

### 7. Chave errada ou perdida: o valor conta como ausente, e o sistema diz por quê

Um valor cifrado que não abre **nunca** é usado no lugar do segredo, e nunca é enviado a um serviço de fora.

| Ponto | Comportamento |
| --- | --- |
| E-mail por SMTP | Não tenta enviar; a mensagem aponta a chave |
| Vendedor do Mercado Pago | Aparece como "Reconectar"; renovar token e gerar link de pagamento são recusados com explicação |
| Webhook de saída | Não é enviado; fica uma linha no registro de entregas, com código 0, e uma no log |
| Tela do webhook | Avisa e exige um segredo novo para salvar |
| Notificação de pagamento | Ver item 8 |
| Salvar uma tela sem redigitar o segredo | Não destrói o valor gravado |
| Situação em *Configurações* | "A chave não é a que cifrou os valores gravados" (vermelho), com a contagem |

Restaurar a chave certa no `config.php` devolve tudo ao normal, sem redigitar nada.

### 8. Notificação de pagamento responde 503 quando um segredo não abre

`checkout/payment.notify` sempre respondeu 200. Passa a responder **503 com `Retry-After: 3600`** em dois casos:

- a chave de assinatura gravada não abre. Tratar como "sem chave" faria a conferência da assinatura ser pulada;
- o token do vendedor dono do pagamento não abre, e por isso o pagamento não pôde ser confirmado.

Com 503 o Mercado Pago reenvia depois. Corrigida a chave, a mesma notificação é processada. Com 200 o aviso de pagamento estaria perdido.

### 9. Troca de chave

```php
define('REAMUR_SECRET_KEY', 'chave nova');
define('REAMUR_SECRET_KEY_OLD', 'chave anterior');   // só abre; nunca grava
```

Com as duas linhas nos dois arquivos, tudo continua funcionando. **Proteger segredos** regrava com a chave nova; depois disso a linha da anterior sai.

### 10. Coluna do segredo de webhook

Um segredo de 64 caracteres, cifrado, ocupa até 390. `rms_webhook.secret` passa de `VARCHAR(64)` para `VARCHAR(512)`.

- Instalação nova: os dois arquivos de esquema já criam a coluna larga.
- Site instalado: o próprio painel alarga a coluna na primeira vez que precisa (ao salvar um webhook ou em **Proteger segredos**). Se o usuário do banco não tem permissão de `ALTER`, o segredo segue legível (nunca cortado), a tela avisa e `install/sql/upgrade_s9_webhook_secret.sql` faz o mesmo à mão. `rollback_s9_webhook_secret.sql` desfaz.

As outras cinco posições são `TEXT` e não mudam.

### 11. Chave de assinatura do Mercado Pago pela tela

`mp_webhook_secret` só podia ser gravada por SQL. A tela *Mercado Pago Marketplace* ganhou um campo só de escrita (16 a 255 caracteres), com indicação de "configurado" e opção de remover.

### 12. Verificação remota

`tools/check_producao.sh`, check 18: nenhum `config.php`, nenhuma cópia dele (`config.php.bak`, `config.php~`, `.config.php.swp` …) e nem o cache das configurações são entregues como texto. Reprova só quando o conteúdo aparece na resposta.

## Efeito

| Para quem | O que muda |
| --- | --- |
| Visitante | Nada |
| Site novo | Segredos gravados cifrados desde a instalação |
| Site instalado, ao atualizar | Nada muda sozinho. O painel passa a avisar. Para proteger: colar a linha da chave nos dois `config.php` e usar **Proteger segredos** |
| Quem faz a cópia de segurança | **A linha da chave precisa ser guardada junto.** Banco restaurado sem ela não abre os segredos |
| Quem integra com o Mercado Pago | A notificação pode responder 503 (item 8). A chave de assinatura entra pela tela |
| Quem escreve código | Segredo novo no banco passa por `$this->secrets()->seal()` ao gravar e `->open()` no ponto de uso, com um nome de lugar próprio |

**Perder a chave tem custo.** Sem a chave que cifrou, os valores não voltam: é preciso reconectar cada vendedor no Mercado Pago e redigitar o segredo do aplicativo, a chave de assinatura, a senha SMTP e o segredo de cada webhook (combinando o novo com quem recebe).

Um arquivo SQL novo (`upgrade_s9_webhook_secret.sql`), uma coluna alargada, duas constantes novas no `config.php`. Sem tabela nem rota nova de tela; uma ação nova (`setting/setting.protectSecrets`), coberta pela permissão já existente de *Configurações*.

## Verificação

Ambiente local (PHP 8.2, MariaDB em modo estrito, Chromium headless). O Mercado Pago e o servidor de e-mail de teste aceitam **só os valores verdadeiros**: se o CMS enviasse texto cifrado ou valor errado, a operação falharia.

| Situação | Resultado |
| --- | --- |
| Sem chave | Painel avisa; a tela mostra a linha para colar e nenhum botão. E-mail de teste, renovação de token, link de pagamento e notificação funcionam, com os valores legíveis no banco |
| Chave só no `admin/config.php` | Situação "falta no catálogo"; nada é cifrado; webhook novo gravado legível |
| Mesma chave nos dois | "Proteger segredos" converte os 7 valores gravados (dois tokens, três configurações, dois webhooks); todos ficam cifrados no banco, a coluna vira `VARCHAR(512)`, o cache deixa de ter segredo legível, o painel para de avisar |
| Em uso, com tudo cifrado | E-mail de teste enviado; configurações salvas sem redigitar a senha; tokens renovados; vendedor conectado; link de pagamento criado; webhook editado sem redigitar o segredo; chave de assinatura gravada pela tela. O serviço de teste recebeu o segredo do aplicativo e os tokens verdadeiros |
| Notificação de pagamento | Assinatura certa: processada. Assinatura errada ou ausente: recusada |
| Chave trocada por outra válida (chave perdida) | Painel e tela avisam; e-mail, renovação e link de pagamento recusados com explicação; notificação responde 503; salvar as telas não alterou nenhum valor gravado; **nada chegou ao Mercado Pago nem ao servidor de e-mail de teste** |
| Troca de chave (nova + anterior) | Tudo funciona antes da conversão; "Proteger segredos" regrava os valores ainda cifrados com a chave anterior; sem a linha da anterior, tudo continua funcionando |
| Chave do catálogo diferente da do admin | Tela aponta a diferença, sem botão; notificação responde 503; corrigido o arquivo, a mesma notificação é processada |
| Seis telas do admin | Não mostram segredo nem texto cifrado |
| Webhooks de saída, lidos do banco de teste com a chave do `config.php` | O segredo entregue ao envio é o verdadeiro e a assinatura calculada confere. Com outra chave ou sem chave: nada é enviado, código 0 |

Testes: `tests/Unit/Security/SecretBoxTest.php` (cifra, chave, contexto, adulteração, troca de chave, leitura do outro `config.php`), `SecretStorageTest.php` (o SQL que sai para o banco não contém o segredo; o valor que chega ao ponto de uso é o verdadeiro, ou vazio com aviso; conversão; notificação) e `SecretWiringTest.php` (instalador, esquema, telas, textos nos três idiomas, ação protegida, aviso do painel). 48 alterações propositais no código, uma por vez: os testes reprovaram as 48.

## Limites

- **Não protege contra quem lê os arquivos do site.** A chave está no `config.php`, ao lado da senha do banco. Quem consegue ler esse arquivo (acesso FTP, painel da hospedagem, falha que leia arquivos, cópia do site inteiro) abre tudo. A proteção vale para quem alcança **só o banco**: cópia de segurança do banco, arquivo `.sql`, phpMyAdmin, falha de leitura por SQL.
- **A chave tem de estar escrita no `config.php` como texto.** O admin confere o arquivo do catálogo lendo a linha `define(...)`. Chave montada de outra forma (lida de outro arquivo ou de variável de ambiente) faz o admin entender que o catálogo não tem a chave, e ele não cifra. Guardar a chave fora da pasta do site, como a ADR 0034 sugeria, não é atendido por esta etapa.
- **Uma cópia de segurança que leve banco e arquivos juntos leva a chave junto.**
- **Notificação do Mercado Pago sem assinatura:** enquanto `mp_webhook_secret` estiver vazio, a assinatura não é conferida. Decisão anterior (ADR 0035), não alterada aqui; o pagamento continua sendo confirmado por consulta ao Mercado Pago, não pelo conteúdo da notificação.
- **Sem a extensão OpenSSL com AES-256-GCM, nada é cifrado.** O painel avisa.
- **Não foi exercitado:** entrega real de um webhook de saída (o envio só conecta em endereço público `https`, e nenhum serviço de terceiros foi contatado; a leitura do segredo e a assinatura foram conferidas com o banco de teste); Mercado Pago e servidor de e-mail reais; hospedagem de produção (Apache / LiteSpeed, usuário do banco sem `ALTER`).
- **O histórico não é reescrito.** Cópias de segurança e arquivos `.sql` anteriores à conversão continuam com os valores legíveis. Se algum saiu do controle do mantenedor, os segredos precisam ser trocados na origem (Mercado Pago, conta de e-mail, sistemas que recebem webhooks).
