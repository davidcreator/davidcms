# RELATORIO KZ-35 — JOW-payments (interno): cobranças + PIX Copia e Cola + relatório

**Data:** 2026-11-17 · **Base:** HEAD `f662780` · **Pacote:** `ReamurCMS-Frente-KZ35-Jow-Payments.zip`

## Módulo admin `payments/jow` (menu Vendas → Pagamentos (JOW))
- **Cobranças**: descrição, valor, método (PIX/cartão/boleto), e-mail do pagador, vencimento; status (pending/paid/expired/refunded/canceled)
- **PIX Copia e Cola**: BR Code EMV gerado em PHP puro (TLV + CRC16-CCITT-FALSE — validado contra o vetor oficial `123456789 → 29B1`), usando a chave PIX padrão + dados do recebedor
- **Chaves PIX**: cadastro (e-mail/telefone/CPF/CNPJ/aleatória) com titular; uma chave aleatória é criada na migração
- **Relatório de recebimentos**: totales por status × método
- **Dados do recebedor**: nome/cidade/MCC usados no payload PIX
- Ferramenta **interna**: não aparece no catálogo público (só `internal-tools.json` do admin)

## Aplicar
```bash
git commit -m "feat(payments): JOW-payments interno (cobranças, PIX EMV, relatório) (KZ-35)"
mysql -u USUARIO -p davidcms < tools/upgrade_kz35_jow_payments.sql
```
Depois: Admin → Vendas → Pagamentos (JOW).
