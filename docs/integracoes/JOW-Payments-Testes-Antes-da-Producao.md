# JOW Payments — o que precisa ser testado antes de ir para produção

**Estado:** engenharia encerrada até onde é possível sem credenciais. O que falta é **validação
operacional**, e ela depende de contas de teste nos provedores.

**Regra de ouro:** credencial de teste. Conta de gateway em `sandbox`, `REAMUR_PAYMENTS_ALLOW_LIVE`
**não** definida, e nenhum pedido real em nenhum passo.

---

## 1. Antes de qualquer teste: quatro verificações de dez segundos

| # | Verificação | Como | Esperado |
| --- | --- | --- | --- |
| 1.1 | Migração do núcleo novo aplicada | `php tools/payment-schema-drift.php --config=catalog/config.php` | `ok` (código 0), três tabelas do núcleo **e** a tabela de pedidos |
| 1.2 | Corpo do site em ordem | `bash tools/verify-checksums.sh` | `3 PASS / 0 FAIL` |
| 1.3 | Nenhum manifesto de pacote na raiz | o mesmo comando acima | nenhum `AVISO CHECKSUMS-PACOTE.txt` |
| 1.4 | Conta do provedor habilitada | tela **JOW Payments** → *Contas próprias do JOW* | badge verde e credenciais salvas |

Se 1.1 disser `config_mismatch`, **pare**: o `config.php` aponta para outro banco e qualquer teste
dali em diante mede a instalação errada.

---

## 2. Os sete testes de gateway (roteiro completo em `KZ-64-Validacao-Sandbox.md`)

| Teste | O que prova | Credencial necessária |
| --- | --- | --- |
| **A** | a tentativa nasce no provedor (`provider_reference`, endereço de pagamento) | conta sandbox |
| **B** | webhook assinado aplica `paid` no ledger **e** sincroniza a cobrança do JOW (P0-a) | conta sandbox + webhook configurado |
| **C** | reenvio do mesmo aviso não duplica evento nem regride estado | idem |
| **D** | recusa vira `failed`/`canceled` e não volta atrás | cartão de recusa |
| **E** | **pagamento com webhook perdido é recuperado pelo cron** (P0-b) | idem + `REAMUR_RECONCILE_TOKEN` |
| **F** | guardas da rota `cron/reconcile`: 403 / 400 / 200 / 503 | token |
| **G** | Stripe/PayPal sem a coluna de âncora → `missing_provider_anchor`; PayPal fica `authorized` | contas Stripe/PayPal sandbox |

## 3. Os testes do fluxo de pedido (ADR 0119)

Roteiro em `MIGRACAO-ORDER.md` e no pacote `roteiro-teste-pedido.md`.

| Teste | O que prova | Observação |
| --- | --- | --- |
| **H** | link de pedido criado no ledger novo (`source_type = 'order'`) | não muda a situação do pedido na criação |
| **I** | clicar duas vezes reaproveita o link (`Link::REUSED`) | nenhuma cobrança nova |
| **J** | pagamento confirmado move o pedido e a tentativa para `paid` | caminho de dinheiro completo |
| **K** | **link antigo do legado continua abrindo (302)** | **o mais crítico dos doze**: quebra cliente em produção |
| **L** | pedido já pago não ganha segundo link | `Link::PAID` |
| **M** | provedor sem conta habilitada → bloqueado, nada criado | fail closed |

---

## 4. Decisões de produto antes de `live` — uma tomada, duas em aberto

### 4.1 Para onde vai o dinheiro do pedido — **decidido** (ADR 0120)

**Decisão: o CMS cobra apenas o que é da plataforma.** A plataforma não fecha com pedidos de vendedores
terceiros — só conteúdo próprio e serviços. Portanto:

| | Legado (checkout Marketplace) | Núcleo novo (ADR 0119) |
| --- | --- | --- |
| Token | vendedor (OAuth) | conta da plataforma |
| Taxa da plataforma | 10% (`mp_marketplace_fee_percent`) | **nenhuma** |
| Quem recebe | vendedor | **plataforma, valor integral** |

O comportamento do núcleo novo é o **correto**, não um atalho: não existe pedido de terceiro a dividir.
Nada a implementar.

- **Consequência para o teste:** nenhum teste de split, de repasse ou de conta de vendedor precisa
  existir. A bateria A–M continua valendo como está.
- **Consequência para o estorno (§4.2):** fica **mais simples** do que o estimado — um estorno mexe em
  uma conta só.
- **Se o modelo mudar:** a ADR 0120 registra o caminho (B1/B2) e o custo, para não começar do zero.

### 4.2 Estorno

`DECISAO-REEMBOLSO.md` (entregue fora do repositório) compara três escopos — R1 (estorno no painel do
provedor), R2 (estorno total pelo CMS), R3 (com parcial, exige tabela nova). **D01 sem resposta.**

**Decisão pendente — não bloqueia o go-live de cobrança, mas deixa o estorno manual.**

### 4.3 Webhook perdido é rotina ou exceção?

O cron de reconciliação é rede de segurança, **não** gatilho. Ele exige `REAMUR_RECONCILE_TOKEN`
(32+ caracteres) e um crontab do sistema chamando a rota. Sem isso, pagamento com aviso perdido só
aparece quando o provedor reenviar.

**Decisão operacional pendente — precisa de crontab configurado antes de `live`.**

---

## 5. Ordem recomendada quando as credenciais chegarem

1. Testes **A, B, C** com Mercado Pago sandbox (vendedor de teste + pagador `@testuser.com`).
2. Testes **H, I, K, L, M** — o fluxo de pedido não move dinheiro real e valida a ADR 0119.
3. Teste **E** com o webhook desligado de propósito (`min_age=1`) — prova o P0-b.
4. Testes **D** e **F** (cartão de recusa, guardas da rota).
5. Teste **J** por último: é o único que exige o caminho completo de pagamento.
6. Só depois de 1–5: decidir o **4.2** (estorno), configurar o crontab (4.3) e reavaliar `live`. O **4.1** já está decidido pela ADR 0120 e não bloqueia mais nada.

---

## 6. O que já está provado (não precisa repetir)

- **Suíte automatizada:** 1910 testes, 46.278 asserções, verde; PHPStan sem erros.
- **Guards:** `verify-checksums` 3 PASS, `verify-ci-workflow` 35 PASS, `verify-deps-layout` 18 PASS.
- **Migração:** aplicada e confirmada pelas tabelas do núcleo existentes no banco.
- **Idempotência da chave de pedido:** coberta por teste (`OrderPaymentLinkTest`), incluindo chave
  nova por tentativa e reaproveitamento de link vivo.
- **Escrita em um dono por pagamento:** coberta por teste (`OrderFlowLedgerTest`).

O que **nenhum** teste automatizado prova, e por isso está nesta lista: conversa real com o provedor,
assinatura de webhook real e o cron rodando de verdade. É exatamente o que as credenciais destravam.
