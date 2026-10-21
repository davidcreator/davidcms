# Runbook de Cutover — ReamurCMS para produção (F6-03)

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS |
| **Entrega** | F6-03 (Fase 6 · hardening e cutover) — rev. F6-04: modelo build→deploy (ADR 0039) |
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

## 3. O que sobe para o servidor (modelo build → deploy, ADR 0039 — **decidido**)

**Produção NÃO é a pasta `dev/`.** Você sobe apenas a build de produção,
gerada com 1 comando a partir da sua pasta local (XAMPP):

```bash
~/davidcms$ bash tools/build_prod.sh
```

Isso recria `~/davidcms/prod/` com **somente o runtime** (`catalog/`,
`admin/`, `system/`, `.htaccess`, `index.php`, `index.html`, `robots.txt`)
— e **sem**: `install/` (o wizard nunca vai ao ar), `tests/`,
planejamento/agentes/docs, configs reais, phpstan/composer, logs e sessões
antigas. O script termina com um veredito automático (algo obrigatório
faltando ou algo proibido presente = ele acusa). `agents-ia/`, `docs/`,
`tools/`, `dev/` inteiro: **ficam só na sua máquina** (e no git privado),
nunca no ar.

| | Valor em produção (davidalmeida.xyz) |
|---|---|
| Pasta que recebe o **conteúdo** de `prod/` | a raiz pública do domínio (ex.: `public_html/`) |
| Home pública | `https://davidalmeida.xyz/catalog/` (e `/` redireciona p/ ela — vem no `index.php` da build) |
| Admin | `https://davidalmeida.xyz/admin/` |
| Mapa 301 do ADR 0038 | **funciona sem edição nenhuma** (CMSBASE resolve o prefixo real; provado em `/prodtest/` pela f604) |

- [ ] **3.1** Build gerada e veredito "OK — build pronta" ✔
- [ ] **3.2** (opcional) Zip da build para upload mais rápido:
      `cd prod/ && zip -qr ../build-prod-AAAA-MM-DD.zip .` — no servidor,
      extrair o zip na raiz pública. Os próprios zips ficam fora do git.

## 4. Deploy (passo a passo)

- [ ] **4.1** Subir o **conteúdo de `prod/`** para a raiz pública do
       domínio (FTP/cPanel ou zip do 3.2 + "Extract" no cPanel). O resultado
       SEMPRE é: `public_html/{catalog,admin,system,index.php,index.html,
       robots.txt,.htaccess}` (sem nenhuma pasta `prod/` ou `dev/` no meio).
- [ ] **4.2** Banco de produção **via SQL, sem wizard** (install/ não foi
       ao ar). No phpMyAdmin do painel da hospedagem, nessa ordem:
  1. criar o banco vazio (utf8mb4);
  2. importar `install/sql/schema.sql` (o arquivo está no seu XAMPP — os
     SQLs **não** precisam subir para o servidor);
  3. **semeá-lo como o wizard faria** (antes dos upgrades!): no seu XAMPP,
     rode `php tools/seed_admin_sql.php SEU-USUARIO "SENHA-FORTE" SEU-EMAIL`
     → ele gera o SQL pronto (grupo *Administrator* + usuário com bcrypt);
     cole no phpMyAdmin da hospedagem;
  4. importar todos os `upgrade_*.sql` **em ordem alfabética** (são
     idempotentes; os grants só funcionam depois do passo 3).
- [ ] **4.3** Configs: na raiz pública, renomeie `catalog/config.php.EXEMPLO`
       → `config.php` e `admin/config.php.EXEMPLO` → `config.php`, editando
       os `{{...}}` reais. ⚠ `HTTP_SERVER`/`HTTPS_SERVER` **terminam com**
       `/catalog/` e `/admin/` respectivamente (nunca a raiz `https://dominio/`
       sozinha — o router subtrai essa base; detalhe na ADR 0039). Confira
       credenciais do banco **de produção** (≠ XAMPP) e deixe
       `REAMUR_ERROR_DISPLAY` **off** (vem doc no próprio exemplo). Não
       commite esses configs no git.
- [ ] **4.4** Permissões: `system/storage/` (e subpastas cache/ logs/
       session/ temp/) gravável pelo PHP do servidor.
- [ ] **4.5** HTTPS: emitir/ativar o certificado do domínio (painel da
       hospedagem, geralmente 1 clique). **Só depois** do cert válido:
  - [ ] forçar HTTPS (opção "Force HTTPS" do painel);
  - [ ] conferir cadeado em `https://davidalmeida.xyz/catalog/`.
- [ ] **4.6** Ajustar a **URL pública** nas configs (4.3) já com `https://`.
- [ ] **4.7** Site antigo: com o novo validado (§5), aposentar (mover para
       pasta `_old` ou apontar o domínio de vez). **Não apague** nada
       antes do §5.9.

## 5. Pós-cutover: verificação (antes de aposentar o antigo)

Rode em qualquer máquina (ajuste `DOMINIO`):

```bash
# Mapa 301 (os Location devem apontar as keywords novas do site NOVO)
curl -sI "https://DOMINIO/catalog/tools/open?id=1" | grep -i "HTTP\|location"
curl -sI "https://DOMINIO/catalog/portfolio/jwa" | grep -i "HTTP\|location"
curl -sI "https://DOMINIO/catalog/portfolio/pitbul-color" | grep -i "HTTP\|location"
# Canônicas diretas (200)
curl -s -o /dev/null -w "%{http_code}\n" https://DOMINIO/catalog/cookies
curl -s -o /dev/null -w "%{http_code}\n" https://DOMINIO/catalog/fluxa
# Webhook MP atende POST (sem redirect)
curl -s -X POST "https://DOMINIO/catalog/index.php?route=checkout/payment.notify&type=payment&data.id=0" \
  -H "Content-Type: application/json" -d '{"type":"payment","data":{"id":"0"}}'
```

- [ ] **5.1** 301 do mapa retornam `301` com `Location` correto (3 primeiros comandos).
- [ ] **5.2** Home, `cookies`, `politicas-internas`, `jwa`, `fluxa` = **200** com conteúdo certo no navegador.
- [ ] **5.3** Headers F6-01 presentes (DevTools → Network, igual ao A9 do roteiro F6-02).
- [ ] **5.4** Admin abre, login funciona, módulos carregam.
- [ ] **5.5** Jornada pública real: envie um **contato** e um **orçamento** de teste pelo site no ar → chegam em Mensagens/Pedidos + e-mail ao dono (SMTP 1.6). Marque-os como "TESTE CUTOVER" para excluir depois.
- [ ] **5.6** Webhooks (se ativos): verifique a última entrega em Admin → Webhooks (HTTP 200 no receptor real).
- [ ] **5.7** Mercado Pago produção (quando credenciais prontas): confira `docs/MercadoPago-Marketplace.md` — salvar config **env=prod**, redirect URI `https://DOMINIO/...callback` **cadastrada no app do MP**, conectar vendedor real, gerar link num pedido de teste e pagar valor mínimo real; conferir notify→status Pago. 🔴 Só depois disso anuncie os links 💳.
- [ ] **5.8** error.log de produção (`system/storage/logs/error.log` no servidor) sem erros novos inesperados.
- [ ] **5.9** Tudo verde → pode aposentar o site antigo (4.7). Guardou os backups? (§2.4)

## 6. Monitoramento — primeiros 30 dias

| Frequência | O que fazer | Como |
|---|---|---|
| Diário (semana 1), depois semanal | `error.log` sem erros novos | abrir `system/storage/logs/error.log` no servidor |
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
