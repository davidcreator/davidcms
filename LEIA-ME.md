# KZ-64 — P0 do relatório: bug de status + reconciliação ativa

**Pacote:** `ReamurCMS-KZ64-P0-reconciliacao.zip`
**Base:** `main` em `536a7f4` (test(Payment): add unit test for Jow multi-gateway wiring)
**Data:** 2026-11-20
**Escopo:** apenas pagamentos/JOW. Nenhuma migração aplicada, nenhuma chamada real a provedor, nenhum deploy.

---

## O que tem aqui

| Caminho | Para que serve |
| --- | --- |
| `LEIA-ME.md` | este arquivo — passo a passo |
| `RELATORIO-KZ64.md` | relatório técnico da entrega (o que mudou, por quê, evidências, limites) |
| `patches/0001-*.patch` | **P0-a** — correção do status por provedor (3 arquivos) |
| `patches/0002-*.patch` | **P0-b** — reconciliação ativa (25 arquivos) |
| `arquivos/` | os 28 arquivos no estado final, para copiar/colar se preferir ao patch |
| `sql/upgrade_jow_payments_reconcile.sql` | migração aditiva e opcional (cópia do arquivo do patch) |
| `aplicar.sh` | aplica os dois commits com verificação prévia |
| `verificar.sh` | `php -l`, PHPUnit, PHPStan e guards de integridade |
| `CHECKSUMS-PACOTE.txt` | sha256 de cada arquivo deste pacote |

---

## Passo 0 — Descompactar dentro do repositório

```bash
cd /caminho/do/davidcms
unzip /caminho/ReamurCMS-KZ64-P0-reconciliacao.zip
# cria ./ReamurCMS-KZ64-P0-reconciliacao/
```

Os scripts usam o repositório como pasta-mãe do pacote. Se preferir manter o
pacote fora do repo, use `git am` com o caminho completo (Passo 2/3) — funciona
igual, porque os patches têm prefixo `a/`…`b/`.

## Passo 1 — Conferir a base

```bash
cd /caminho/do/davidcms
git status --short          # precisa estar vazio
git log --oneline -1        # esperado: 536a7f4 test(Payment): add unit test for Jow multi-gateway wiring
```

Se a `main` já avançou, os patches ainda aplicam desde que nenhum dos 28 arquivos tenha mudado. Para testar sem aplicar:

```bash
P=ReamurCMS-KZ64-P0-reconciliacao
git apply --check $P/patches/0001-fix-status-cobranca-por-provedor.patch
git apply --check $P/patches/0002-feat-reconciliacao-ativa.patch
```

Atalho que faz tudo (confere, aplica os dois commits e roda a verificação):

```bash
P=ReamurCMS-KZ64-P0-reconciliacao
bash $P/aplicar.sh            # ou: bash $P/aplicar.sh --check  (só confere)
```

## Passo 2 — Aplicar o P0-a primeiro (o bug de dinheiro)

```bash
P=ReamurCMS-KZ64-P0-reconciliacao
git am $P/patches/0001-fix-status-cobranca-por-provedor.patch
```

São 3 arquivos:

- `dev/catalog/model/checkout/gateway_payment.php` — o `WHERE` deixa de ser `method = 'mp'`;
- `dev/catalog/controller/checkout/gateway.php` — passa o `provider_code` verificado;
- `tests/Unit/Payment/JowChargeStatusSyncTest.php` — regressão dos três provedores.

Verificação mínima (não precisa de banco nem de rede):

```bash
php -l dev/catalog/model/checkout/gateway_payment.php
cd dev/system/storage && composer test -- --filter JowChargeStatusSync
```

Espere `OK (5 tests, ...)`. Se estiver bom, siga; se não, `git reset --hard HEAD~1` e me avise.

## Passo 3 — Aplicar o P0-b

```bash
git am $P/patches/0002-feat-reconciliacao-ativa.patch
```

Verificação:

```bash
bash $P/verificar.sh
```

O que `verificar.sh` roda:

```bash
php -l nos arquivos tocados
cd dev/system/storage && composer test        # esperado: 1881 testes, 0 falhas
cd dev/system/storage && composer analyse     # esperado: [OK] No errors
bash tools/verify-checksums.sh                # esperado: 0 divergente(s)
```

> Se o seu PHP não tiver a extensão `gd` com JPEG, o teste
> `CoreServicesSecurityTest::testImageProcessingAndValidationBounds` falha com
> `Call to undefined function imagejpeg()` — é ambiente, não código (aconteceu
> igual no meu build estático).

## Passo 4 — Ativar a reconciliação (só depois dos passos 2 e 3 verdes)

1. **Migração (recomendada, opcional):**

```bash
mysql -u USUARIO -p SEU_BANCO < tools/upgrade_jow_payments_reconcile.sql
```

É aditiva e idempotente (`information_schema` + `PREPARE`): cria
`provider_checkout_id` e o índice `provider_checkout` em `rms_payment_transaction`.
Ajuste `rms_` se o `DB_PREFIX` for outro. O script termina com um `SELECT` de
conferência.

> Sem a migração nada quebra: Mercado Pago reconcilia pelo id da ordem que já
> existe; Stripe e PayPal reportam `missing_provider_anchor` no relatório.

2. **Token** em `catalog/config.php` (ou `admin/config.php`; no repositório o lugar do arquivo é `dev/system/config/`), 32+ caracteres:

```php
define('REAMUR_RECONCILE_TOKEN', 'gere-um-segredo-aleatorio-de-32-ou-mais-caracteres');
```

Sugestão para gerar: `php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"`

3. **Teste manual**, uma vez:

```bash
curl -sS "https://davidalmeida.xyz/catalog/index.php?route=cron/reconcile&format=json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

Sem token deve responder `403 forbidden`. Com token, o JSON com os contadores.

4. **Agendar** (crontab, a cada 10 minutos):

```cron
*/10 * * * * curl -fsS -m 120 "https://davidalmeida.xyz/catalog/index.php?route=cron/reconcile" -H "Authorization: Bearer SEU_TOKEN" >/dev/null
```

## Passo 4.5 — Não comitar a pasta do pacote

O `git am` já criou os dois commits. A pasta `ReamurCMS-KZ64-P0-reconciliacao/`
continua como *untracked* — apague ou exclua antes de qualquer `git add -A`:

```bash
rm -rf ReamurCMS-KZ64-P0-reconciliacao
# ou, para manter por perto sem sujar o repo:
echo "ReamurCMS-KZ64-P0-reconciliacao/" >> .git/info/exclude
```

## Passo 5 — Subir para o GitHub

Como `git am` já criou os dois commits:

```bash
git push origin main          # ou: git checkout -b feature/kz64-p0 && git push -u origin feature/kz64-p0
```

Sugestão: abrir PR de `feature/kz64-p0` para `main` usando o
`pull_request_template.md`. Pontos do DoD que este pacote já cobre:
`php -l` limpo, PHPStan nível 2 sem erros, suíte verde, nenhum `vendor/`
versionado, docs no mesmo commit, CSRF/ACL intactos (não há tela nova no admin),
templates sem alteração.

**Um item do DoD que eu não consigo fechar sozinho:** o template pede "ADR
relacionado" e o `decision-matrix.json` está congelado em `1f864b80` (F11-11).
Esta frente toda (núcleo multi-gateway + 3 adapters + reconciliação) segue sem
ADR. Recomendo registrar o **ADR 0118** depois do merge — está na lista de
próximos passos do relatório.

---

## Rollback

Antes do push:

```bash
git reset --hard 536a7f4         # descarta os dois commits (o que está no disco volta ao HEAD)
```

Depois do push: `git revert <sha-do-p0b> <sha-do-p0a>` (reverter na ordem inversa).

Se você aplicou a migração e quiser desfazer (não é necessário — a coluna é
inofensiva sem o código novo):

```sql
ALTER TABLE `rms_payment_transaction` DROP KEY `provider_checkout`;
ALTER TABLE `rms_payment_transaction` DROP COLUMN `provider_checkout_id`;
```

---

## Se preferir não usar patch

`arquivos/` tem a mesma árvore final. Copie por cima:

```bash
cp -a ReamurCMS-KZ64-P0-reconciliacao/arquivos/. /caminho/do/davidcms/
```

E comite como quiser. O conteúdo é byte a byte igual ao que os patches produzem
(verificado por `diff` no pacote).

---

## O que NÃO foi feito (de propósito)

- Nenhuma migração foi executada; nenhuma cobrança, captura ou reembolso.
- Nenhum pagamento real; nenhum teste toca a rede (os clients são fakes).
- Nenhuma tela nova no admin (o gatilho é o cron; a UI do JOW não mudou).
- Nenhuma alteração em `rms_mp_payment`, `rms_jow_charge` (além do status que o
  webhook já sincronizava) ou no checkout público Marketplace.
- Nada de F9-27, staging ou deploy.
