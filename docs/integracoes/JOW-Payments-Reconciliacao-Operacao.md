# Reconciliação ativa — como ligar e o que checar

**Para que serve:** o webhook avisa quando um pagamento acontece. Quando o aviso **não chega** — provedor
fora do ar, firewall, URL errada — a reconciliação é quem descobre. Ela só **lê** o provedor; nunca cria
cobrança, nunca captura, nunca reembolsa.

**Sem ela:** pagamento aprovado fica `pending` no CMS até o provedor reenviar o aviso. O cliente paga, o
pedido não muda, e o primeiro a saber é ele — pelo WhatsApp.

---

## 1. O que precisa existir

| Item | Onde | Observação |
| --- | --- | --- |
| `REAMUR_RECONCILE_TOKEN` | `catalog/config.php` | **32 caracteres no mínimo**. Menos que isso = 403 e nada acontece |
| Rota publicada | `https://SEU-SITE/catalog/index.php?route=cron/reconcile` | Já existe no código; não precisa habilitar módulo |
| Chamada periódica | crontab do servidor | É o que falta configurar |
| Pelo menos uma conta habilitada | tela **JOW Payments** | Sem conta habilitada o relatório vem vazio (não é erro) |

### Gerar o token

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Grave em `catalog/config.php`, **fora** de qualquer arquivo versionado:

```php
define('REAMUR_RECONCILE_TOKEN', 'cole-aqui-os-64-caracteres');
```

> O token dá acesso de **leitura** ao estado de pagamentos. Não é senha de admin, mas trate como
> segredo: quem tem o token vê quais tentativas existem e em que situação estão.

---

## 2. A linha do crontab

```cron
*/15 * * * * curl -fsS -H "Authorization: Bearer SEU_TOKEN" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile&limit=50" >/dev/null 2>&1
```

- **`*/15`** — a cada 15 minutos. É o mesmo horizonte do `min_age` padrão (15): não adianta rodar mais
  rápido, a tentativa ainda é nova demais para ser considerada perdida.
- **`-f`** — falha em HTTP 4xx/5xx (sem isso, um 403 passa como sucesso silencioso).
- **`-s`** — sem barra de progresso.
- **`>/dev/null`** — sem e-mail a cada 15 minutos. **Mas** veja a §5: você precisa de um sinal.

### Variante com registro (recomendada no começo)

```cron
*/15 * * * * curl -fsS -H "Authorization: Bearer SEU_TOKEN" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile&limit=50&format=json" \
  >> /var/log/jow-reconcile.log 2>&1
```

Depois de uma semana com o log limpo, dá para voltar ao silencioso.

---

## 3. Teste de fumaça, antes de confiar no cron

Rode na mão, três vezes, e confira o código de saída:

```bash
# 1) Token válido → 200 com o relatório
curl -s -o /dev/null -w '%{http_code}\n' \
  -H "Authorization: Bearer SEU_TOKEN" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile&format=json"

# 2) Token errado → 403 (se der 200, o token não está sendo exigido: PARE)
curl -s -o /dev/null -w '%{http_code}\n' \
  -H "Authorization: Bearer token-errado" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile"

# 3) Sem token nenhum → 403
curl -s -o /dev/null -w '%{http_code}\n' "https://SEU-SITE/catalog/index.php?route=cron/reconcile"
```

| Resultado | Significa |
| --- | --- |
| `200` / `403` / `403` | **correto** — a rota está protegida e viva |
| `200` no teste 2 | **pare** — a guarda não está ativa; não configure o cron |
| `500` no teste 1 | veja o log do PHP; normalmente é a tabela de gateway ausente (rode o verificador de schema) |
| `404` em todos | a rota não está publicada nesta instalação |

Este é o **teste F** do roteiro de validação: `403 / 400 / 200 / 503`.

---

## 4. O que o relatório diz

Cada execução devolve quantas tentativas foram conferidas e o que mudou. `format=json` é o que você
quer no log; `text` (padrão) é legível na hora do teste.

O que ela **faz**: lê o estado no provedor, passa pelo mesmo caminho do webhook (`GatewayEventApplier`)
com as mesmas checagens de valor, id e transição monotônica — ou seja, **não abre porta nova** para
gravar errado.

O que ela **não faz**: capturar ordem autorizada do PayPal (fica `authorized`, de propósito), reembolsar,
ou criar cobrança. A ADR 0118 fixa isso por contrato.

---

## 5. Como saber que ela está funcionando sem abrir o log todo dia

Três sinais, do mais barato ao mais confiável:

1. **O log existe e está vazio** — `curl -f` só escreve em caso de falha. Log de 0 bytes depois de um dia
   é boa notícia.
2. **Conte as linhas de "recuperado"** uma vez por semana: se aparecer um pagamento recuperado e o cliente
   nunca reclamou, a rede funcionou.
3. **Teste E do roteiro** (o único que prova de verdade): desligue o webhook de propósito, faça um
   pagamento sandbox, rode a reconciliação com `min_age=1` e veja a tentativa sair de `pending` para
   `paid` **pela reconciliação**, não pelo aviso.

---

## 6. Erros comuns, e o que cada um quer dizer

| Sintoma | Causa |
| --- | --- |
| Tudo `403` | token ausente, com menos de 32 caracteres, ou `Authorization` comido por proxy (o Apache às vezes descarta o header — use `?token=`) |
| `400` | parâmetro fora da faixa (`limit` 1..200, `min_age` 1..10080, `lookback` 1..90) |
| Relatório vazio, `200` | nenhuma conta de gateway habilitada, ou nenhuma tentativa pendente na janela (`lookback`, padrão 7 dias) |
| `503` | sem credencial utilizável na conta — a mesma causa que bloqueia o link novo |
| Nada acontece, cron "roda" | `curl` sem `-f`: o 403 passou como sucesso. Coloque `-f` |

---

## 7. Checklist de go-live (o que fica de fora do código)

- [ ] `REAMUR_RECONCILE_TOKEN` definido em `catalog/config.php`, 32+ caracteres, gerado com `random_bytes`
- [ ] Os três `curl` da §3 conferidos na mão
- [ ] Linha do crontab instalada e **confirmada com `crontab -l`**
- [ ] `format=json` com log no começo, para ver o cron realmente rodando
- [ ] Webhook do provedor configurado **separadamente** (esta rota não substitui o aviso; ela cobre a falta dele)
- [ ] Teste E executado ao menos uma vez
