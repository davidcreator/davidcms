# Runbook de Cutover — ReamurCMS para produção (F6-03)

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS |
| **Entrega** | F6-03 (Fase 6 · hardening e cutover) |
| **Dependências** | F6-01 aplicada (hardening), F6-02 com critério de saída verde (§11 do roteiro), mapa 301 do ADR 0038 no ar |

---

## 0. Leia primeiro

Este runbook executa a **virada do site novo para produção**. Ordem
obrigatória das seções: 1 (pré-voo) → 2 (backup) → 3 (decisão de
topologia) → 4 (deploy) → 5 (pós-cutover) → 6 (monitoramento 30 dias).
A seção 7 (rollback) é o botão de emergência — leia **antes** de começar.

> 🔒 **Regra de ouro:** nenhum passo destrutivo (apagar pasta antiga,
> dropar banco, trocar DNS) antes do backup da seção 2 estar **feito e
> testado**.

---

## 1. Pré-voo (todos bloqueadores)

- [ ] **1.1** Roteiro F6-02 executado e §11 devolvida: blocos A–E + G com
       todos os 🔴 ✔.
- [ ] **1.2** Código no HEAD final da Fase 6 (F6-01 + F6-02 + F6-03
       aplicados; `git log --oneline -3` confere).
- [ ] **1.3** Upgrades SQL aplicados no banco de produção-destino
       (`rms_webhook`, `rms_webhook_log`, `rms_mp_seller`, `rms_mp_payment`
       existem — ver §2.3 do roteiro F6-02 para os arquivos).
- [ ] **1.4** Credenciais **de produção** do Mercado Pago em mãos (App ID +
       Client Secret prod) E URL pública HTTPS definida — o pagamento real
       só corta junto com o site. **Se ainda não tiver:** go-live possível
       com o Bloco F pendente, mas os botões 💳 ficam sem uso (não gera
       link errado — o sistema bloqueia com erro honesto).
- [ ] **1.5** `REAMUR_ERROR_DISPLAY` = produção: nos `config.php` de
       produção a linha deve estar **`false` ou ausente** (F6-01 — seguro
       por omissão).
- [ ] **1.6** E-mail SMTP de produção (F4-05) testado no XAMPP com as
       credenciais definitivas (contato depende disso para notificar).
- [ ] **1.7** Janela de cutover escolhida (horário de baixo tráfego) e
       **freeze de conteúdo** combinado: ninguém edita o site antigo
       durante a virada (Análise §10).

## 2. Backup (feito E testado)

- [ ] **2.1 Arquivos do site atual** (o que estiver no ar hoje):
  - XAMPP/local: copie a pasta `C:\xampp\htdocs\davidcms` inteira para
    `backup-pre-cutover-<AAAA-MM-DD>\` fora do htdocs.
  - Hospedagem (cPanel): Gerenciador de Arquivos → compactar a pasta
    pública → baixar o `.zip`; ou FTP baixando tudo.
- [ ] **2.2 Banco de dados**:
  - phpMyAdmin → banco do site → **Exportar** → método Rápido, formato
    SQL → baixar `backup-<data>.sql`.
  - (linha de comando, se tiver: `mysqldump -u root reamur > backup.sql`)
- [ ] **2.3 Teste de restore (não pule!):** no phpMyAdmin, crie um banco
       descartável `restore_test` e **importe** o `backup-<data>.sql`
       nele. Deve terminar sem erro. Confira 2 tabelas (`rms_order`
       com seus orçamentos). Pode dropar o banco de teste depois.
- [ ] **2.4** Anote onde os backups ficaram (pasta/pen drive/nuvem) — o
       rollback (§7) depende deles.

## 3. Decisão de topologia (escolha A **ou** B — uma vez)

| | **B — Subpasta (recomendada)** | **A — Raiz pura do domínio** |
|---|---|---|
| Domínio aponta para | pasta que **contém** `dev/` (ex.: `public_html/davidcms/`) | `dev/` virando a raiz pública |
| Home pública fica | `https://dominio.com/dev/catalog/` (e `/` redireciona p/ ela automaticamente) | `https://dominio.com/` direto |
| Admin | `https://dominio.com/dev/admin/` | exige subdomínio (`admin.dominio.com`) ou porta separada — **trabalho extra de vhost** |
| Mapa 301 do ADR 0038 | **funciona sem edição** (CMSBASE resolve) | funciona, e você pode **opcionalmente** encurtar destinos (`catalog/fluxa`→`/fluxa`) |
| Esforço/risco | Baixo | Médio (vhost + admin fora + https em 2 vhosts) |

**Recomendo B para o go-live** (é o formato que você já opera no XAMPP e
nas baterias). A migração para A fica como melhoria futura — o §9 da
Análise ("home na raiz") vira realidade trocando o docroot e ajustando os
destinos das regras (me chama que é meia hora com testes).

- [ ] **3.1** Decisão marcada: ☐ B (recomendada) · ☐ A (com plano de
       vhost/subdomínio para o admin)

## 4. Deploy (passo a passo)

- [ ] **4.1** Subir o código final para o destino (FTP/cPanel/clone git):
       a pasta `davidcms/` com `dev/`, `agents-ia/`, `docs/` etc.
- [ ] **4.2** Banco: criar o banco de produção; importar o schema/upgrade
       **na ordem da cadeia** (igual você já fez no XAMPP: schema inicial
       → todos `upgrade_*.sql` idempotentes).
- [ ] **4.3** `dev/catalog/config.php` e `dev/admin/config.php` de
       **produção**: host/banco/usuário/senha **de produção** e a URL pública
       (sem `localhost`!). Conferir `REAMUR_ERROR_DISPLAY` (item 1.5).
       🔴 Senhas de produção ≠ senhas do XAMPP. Não commite config no git.
- [ ] **4.4** Permissões: `dev/system/storage/` (e subpastas) gravável
       pelo PHP (cache, logs, session).
- [ ] **4.5** HTTPS: emitir/ativar o certificado do domínio (painel da
       hospedagem, geralmente 1 clique). **Só depois** do cert válido:
  - [ ] forçar HTTPS (opção "Force HTTPS" do painel, ou peça o bloco de
        redirect 80→443 — fica fora do `.htaccess` até o cert existir);
  - [ ] conferir cadeado em `https://dominio.com/dev/catalog/`.
- [ ] **4.6** Ajustar a **URL pública** nas configs (4.3) para o domínio
       final já com `https://`.
- [ ] **4.7** Site antigo: com o novo validado (§5), aposentar (mover para
       pasta `_old` ou apontar o domínio de vez). **Não apague** nada
       antes do §5.9.

## 5. Pós-cutover: verificação (antes de aposentar o antigo)

Rode em qualquer máquina (ajuste `DOMINIO`):

```bash
# Mapa 301 (os Location devem apontar as keywords novas do site NOVO)
curl -sI "https://DOMINIO/dev/catalog/tools/open?id=1" | grep -i "HTTP\|location"
curl -sI "https://DOMINIO/dev/catalog/portfolio/jwa" | grep -i "HTTP\|location"
curl -sI "https://DOMINIO/dev/catalog/portfolio/pitbul-color" | grep -i "HTTP\|location"
# Canônicas diretas (200)
curl -s -o /dev/null -w "%{http_code}\n" https://DOMINIO/dev/catalog/cookies
curl -s -o /dev/null -w "%{http_code}\n" https://DOMINIO/dev/catalog/fluxa
# Webhook MP atende POST (sem redirect)
curl -s -X POST "https://DOMINIO/dev/catalog/index.php?route=checkout/payment.notify&type=payment&data.id=0" \
  -H "Content-Type: application/json" -d '{"type":"payment","data":{"id":"0"}}'
```

- [ ] **5.1** 301 do mapa retornam `301` com `Location` correto (3 primeiros comandos).
- [ ] **5.2** Home, `cookies`, `politicas-internas`, `jwa`, `fluxa` = **200** com conteúdo certo no navegador.
- [ ] **5.3** Headers F6-01 presentes (DevTools → Network, igual ao A9 do roteiro F6-02).
- [ ] **5.4** Admin abre, login funciona, módulos carregam.
- [ ] **5.5** Jornada pública real: envie um **contato** e um **orçamento** de teste pelo site no ar → chegam em Mensagens/Pedidos + e-mail ao dono (SMTP 1.6). Marque-os como "TESTE CUTOVER" para excluir depois.
- [ ] **5.6** Webhooks (se ativos): verifique a última entrega em Admin → Webhooks (HTTP 200 no receptor real).
- [ ] **5.7** Mercado Pago produção (quando credenciais prontas): confira `docs/MercadoPago-Marketplace.md` — salvar config **env=prod**, redirect URI `https://DOMINIO/...callback` **cadastrada no app do MP**, conectar vendedor real, gerar link num pedido de teste e pagar valor mínimo real; conferir notify→status Pago. 🔴 Só depois disso anuncie os links 💳.
- [ ] **5.8** error.log de produção (`dev/system/storage/logs/error.log`) sem erros novos inesperados.
- [ ] **5.9** Tudo verde → pode aposentar o site antigo (4.7). Guardou os backups? (§2.4)

## 6. Monitoramento — primeiros 30 dias

| Frequência | O que fazer | Como |
|---|---|---|
| Diário (semana 1), depois semanal | `error.log` sem erros novos | abrir `dev/system/storage/logs/error.log` |
| Semanal | Caçar 404 de URLs antigas | log de acesso da hospedagem: procurar ` 404 ` com `/catalog/` nas URLs → se aparecer URL legada nova, adiciono regra no mapa |
| Semanal | Jornadas críticas vivas | refazer C2 (contato) e C6 (orçamento) do roteiro F6-02 em 3 minutos |
| Semanal | Webhooks entregando | Admin → Webhooks → entregas recentes 2xx |
| Contínuo | Uptime | UptimeRobot (grátis) monitorando a home 5/5 min, alerta no seu e-mail |
| Uma vez | SEO | Google Search Console: enviar o **sitemap novo** (`…/catalog/index.php?route=common/sitemap`), acompanhar cobertura/trocas de URL (os 301 consolida em ~2-4 semanas) |
| Uma vez | Observação CSP | com 30 dias de `Content-Security-Policy-Report-Only` em produção, me devolve os relatos → transformamos em CSP enforce (dívida ADR 0037) |

## 7. Rollback (botão de emergência)

**Critério:** jornada crítica (contato/orçamento) quebrada em produção que
não se resolve em 30 min de diagnóstico, OU loss de dados.

1. Apontar o domínio de volta para o site antigo (desfazer passo 4.7).
2. Se o banco de produção foi corrompido: dropar e **importar o backup**
   do §2.2 (testado no §2.3).
3. Comunicar: me mande o que falhou + trecho do `error.log`. O site novo
   volta ao ar com a correção sem pressa (modo manutenção: freeze vale
   até o re-cutover).
4. **Não** mexa no código de produção "no calor" — correção nasce no
   laboratório, com bateria cobrindo o caso.

---

*Critério de encerramento da Fase 6: §5 completa verde + 30 dias de
monitoramento §6 sem 🔴 novo. O pagamento real (5.7) tem critério próprio
dentro do Bloco F.*
