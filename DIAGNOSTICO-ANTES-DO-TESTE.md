# Antes de criar o primeiro link de pedido — 3 coisas a decidir

O caminho novo está de pé: migração aplicada, verificador em `ok`, CI voltando ao verde. Antes de
criar o primeiro link de pedido em **sandbox**, três diferenças de comportamento mudaram em relação ao
fluxo antigo. Não são bugs — são decisões que agora estão expostas, e duas delas mexem com **dinheiro
de verdade** se o provedor estiver em `live`.

---

## 1. O dinheiro não vai mais para o vendedor — vai para a conta da plataforma

**Fluxo antigo (legado, `Mercadopago::charge`):** a preferência era criada com o `access_token` do
**vendedor** (OAuth Marketplace) e levava `marketplace_fee` = `mp_marketplace_fee_percent` (padrão
**10%**). Resultado: o valor caía na conta do vendedor e a plataforma recebia a taxa.

**Fluxo novo (ADR 0119, `OrderPaymentLink`):** a conta usada é a do provedor cadastrada em
`payments/jow` — a **conta própria da plataforma** — com o `access_token` dela e **sem**
`marketplace_fee`. Resultado: o valor inteiro cai na conta da plataforma. Não há split.

Isso não é descuido: o adapter do Mercado Pago diz, no próprio docblock, que **não** lê token de
vendedor nem adiciona taxa, e que o checkout público de vendedor continua no adapter Marketplace
legado até a migração dele ser aprovada. O que a ADR 0119 fez foi levar o **pedido** para o núcleo
novo — e o núcleo novo, hoje, só conhece conta de plataforma.

| | Antigo | Novo |
| --- | --- | --- |
| Token usado | vendedor (OAuth) | conta da plataforma |
| `marketplace_fee` | 10% (configurável) | **nenhuma** |
| Quem recebe | vendedor, taxa para a plataforma | plataforma, valor integral |

**O que decidir:** se o pedido é de um vendedor e o dinheiro tem de cair na conta dele, o caminho novo
**não serve ainda** — falta a peça "conta de vendedor por pedido". Se o pedido é da própria plataforma
(consultoria, orçamento próprio), o caminho novo está correto.

No **sandbox**, teste com a conta da plataforma e não se preocupe: nada cai em conta real. A decisão
importa antes de `live`.

---

## 2. O seletor da tela mostra provedores que vão falhar

Com Mercado Pago em sandbox, a conta** não** é habilitada a menos que `access_token` e
`webhook_secret` estejam salvos e válidos. Se não estiverem:

```
Link::BLOCKED  →  "no_gateway_account"  →  erro na tela, nenhuma cobrança criada
```

É o comportamento correto (fail closed), mas a mensagem ao operador é genérica. Se o seu teste travar
nesse ponto, confira em `payments/jow` → *Contas próprias do JOW* se o Mercado Pago aparece com badge
verde (habilitado) e credenciais salvas.

E há uma segunda trava: conta em ambiente **`live`** só cria cobrança se `REAMUR_PAYMENTS_ALLOW_LIVE`
estiver definida no `config.php`. Em `sandbox` a trava não se aplica — é o caso do seu teste.

---

## 3. O primeiro link cria a tentativa, mas nada muda no pedido até o pagamento

Ordem esperada no teste:

1. Tela → *Link de pagamento de pedido* → pedido → *Mercado Pago* → botão.
2. Esperado: "Link de checkout criado ou reutilizado".
3. `rms_payment_transaction` ganha uma linha: `source_type = 'order'`, `status = requires_action`
   (ou `pending`), `charge_amount` = total do pedido, `provider_reference` preenchida.
4. **O pedido continua na situação em que estava.** Ele só muda quando o pagamento for confirmado
   (webhook assinado ou reconciliação).

Se você esperar que o pedido mude de situação no passo 3, vai achar que falhou. Não falhou.

---

## O que eu preciso de você para o próximo passo

Rode o teste do item 3 e me diga **uma** de três coisas:

- **"criou"** → me mande a linha de `rms_payment_transaction` (as 4 colunas acima). Sigo para o teste
  do webhook/reconciliação.
- **"bloqueado / erro"** → me diga o texto exato da tela. Provavelmente é o item 2.
- **"nem aparece a seção"** → a tela só mostra a seção quando `gatewaySchemaReady()` é verdadeiro;
  nesse caso me diga se as tabelas existem no banco que o `localhost` usa (a confirmação que ficou
  pendente no pacote anterior).

## E a decisão do split, quando você quiser

Se a resposta for "o pedido é do vendedor", o trabalho é a **conta de vendedor por pedido**: escolher
o `mp_seller_id` dono do pedido, usar o token dele e voltar a aplicar `marketplace_fee`. Isso é
ADR própria (0120) — mexe em roteamento de dinheiro, não em encanamento. Diga se quer que eu abra
essa frente depois do teste em sandbox.
