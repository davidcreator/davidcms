# Mercado Pago Marketplace — guia do operador e do desenvolvedor

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS |
| **Entrega** | F5-02 · ADR 0034 · fundação OAuth (conectar/renovar/desconectar vendedores) |
| **Fora do escopo** | Checkout com split (`marketplace_fee`), IPN de pagamento → **F5-03**, com credenciais reais de teste |

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

## 6. Segurança — o que já vale e o que é dívida

- `state` aleatório de 16 bytes, comparado com `hash_equals` e consumido após uso ✔
- Redirect URI `https://` obrigatório ✔ · credenciais fora do git ✔ · grade sem tokens ✔
- **Dívida declarada (ADR 0034 §6):** access/refresh tokens persistem em texto plano no banco. Sem KMS/keyring no projeto, cifrar com chave em arquivo próximo seria ilusão. Hardening = env keyring fora do docroot + cifragem em repouso (Fase 6).
