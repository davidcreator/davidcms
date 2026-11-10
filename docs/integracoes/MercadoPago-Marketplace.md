# Mercado Pago Marketplace — guia do operador e do desenvolvedor

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS |
| **Entregas** | F5-02 · ADR 0034 · fundação OAuth (vendedores)<br>F5-03 · ADR 0035 · checkout com split + notificação de pagamento |
| **Validação** | Baterias com mock local; sandbox real quando houver credenciais de teste |

## 1. O que esta entrega faz

Em **Configurações → Mercado Pago Marketplace** (`admin → setting/marketplace`) você:

1. cadastra as **credenciais da aplicação** (App ID, Client Secret, Redirect URI, ambiente test/prod) — gravadas em `rms_setting`, **nunca versionadas no git**;
2. **conecta vendedores** via OAuth do Mercado Pago (cada vendedor fica em `rms_mp_seller` com access/refresh tokens);
3. **renova tokens** (rotate) e **desconecta** vendedores (localmente — a revogação final é no painel MP do vendedor).

Sem credenciais, a tela mostra o pré-requisito e o botão *Conectar vendedor* fica desabilitado.

## 2. Criando o app no painel do Mercado Pago

1. Acesse <https://www.mercadopago.com.br/developers> → **Suas aplicações → Criar aplicação**.
2. Nomeie (ex.: *ReamurCMS — testes*) e selecione a solução **Pagamentos de marketplace / outro cobrador**.
3. Em **Redirecionamentos (redirect URIs)**, cadastre a URL **exata** do callback do admin:

   ```
   https://SEU-DOMINIO/admin/index.php?route=setting/marketplace.callback
   ```

   Tem que ser **https://** (a tela valida isso). Copie a mesma string para o campo *Redirect URI* da tela.
4. Copie **Client ID** (= App ID, numérico) e **Client Secret** para a tela do CMS e **Salvar**.
5. Para testes use credenciais do **usuário de prueba/sandbox**; ambiente *Teste*.

## 3. Conectando um vendedor

1. Com a config salva, clique **Conectar vendedor** → você é redirecionado ao Mercado Pago.
2. Autorize com a **conta do vendedor** (em sandbox, um *usuário de prueba* vendedor).
3. O MP devolve para o callback; o CMS valida o `state` (anti-CSRF, preso à sessão), troca o `code` por tokens e cria/atualiza a linha do vendedor.
4. Reconectar o mesmo vendedor **atualiza** a mesma linha (UNIQUE por `mp_user_id`) — não duplica.

## 4. Banco de dados

```sql
rms_mp_seller
├─ mp_seller_id     INT PK AI
├─ mp_user_id       BIGINT UNIQUE  ← chave natural do MP
├─ nickname, email  VARCHAR        ← identificação na grade (NUNCA tokens)
├─ access_token     TEXT           ← texto plano nesta entrega (ADR 0034 §6)
├─ refresh_token    TEXT
├─ scope            VARCHAR
├─ token_expires_at DATETIME       ← NOW() + expires_in
├─ status           TINYINT
└─ date_added / date_modified
```

Config: `rms_setting` chaves `mp_app_id`, `mp_client_secret`, `mp_env`, `mp_redirect_uri` (code `config`).

## 5. Testes e2e (mock do MP, sem credenciais reais)

`tools/e2e/e2e_f502.sh` sobe um **mock local** do MP em `/var/www/html/hookmock.php` e grava overrides `mp_token_url` / `mp_users_me_url` no banco de teste (costuras do ADR 0034 §4 — em produção essas chaves não existem e os endpoints oficiais são usados). A bateria faz 10 checks: schema, seeds, tela, validações do save, `authorize` com state, `callback` rejeitando state forjado, fluxo OAuth completo contra o mock com **upsert**, refresh rotacionando expiração e delete local.

Rode com o ambiente padrão: `bash tools/e2e/e2e_f502.sh` (precisa do Apache + banco `reamur_test` ativos).

## 6. Checkout com split (F5-03)

1. **Defina o preço do pedido** em *Vendas → Pedidos → Editar* (o orçamento nasce com total zero).
2. Na lista de pedidos, clique **💳 Link de pagamento MP** — o CMS cria a preference no seller conectado com `marketplace_fee` (comissão da plataforma, default **10%** via chave `mp_marketplace_fee_percent`) e exibe o **link público copiável** na tela *Mercado Pago Marketplace*.
3. Envie o link ao cliente (WhatsApp/e-mail). O cliente não precisa de login: o link abre o Checkout Pro do vendedor.
4. O Mercado Pago **notifica** o CMS em `catalog/index.php?route=checkout/payment.notify` (esse endereço vai na preference automaticamente, derivado da URL pública do catálogo — precisa ser alcançável pela internet em produção). Pagamento **aprovado** → pedido vai para *Approved*; **rejeitado/cancelado** → *Canceled*.
5. Regerar link para pedido pendente **reutiliza** o mesmo link (não duplica cobrança no MP).

### Segurança do endpoint de notificação

- O CMS **nunca confia no POST** do webhook: extrai o id e **consulta `/v1/payments/{id}`** com o token do vendedor — só o status confirmado pela API é refletido no banco (idempotente).
- Opcional e recomendado para produção: cadastre a chave de **assinatura de webhooks** no app do MP e grave-a em `rms_setting` (`mp_webhook_secret`) — o CMS valida o header `x-signature` antes de chamar a API.

## 7. Segurança — o que já vale e o que é dívida

- `state` aleatório de 16 bytes, comparado com `hash_equals` e consumido após uso ✔
- Redirect URI `https://` obrigatório ✔ · credenciais fora do git ✔ · grade sem tokens ✔
- **Dívida declarada (ADR 0034 §6):** access/refresh tokens persistem em texto plano no banco. Sem KMS/keyring no projeto, cifrar com chave em arquivo próximo seria ilusão. Hardening = env keyring fora do docroot + cifragem em repouso (Fase 6).
