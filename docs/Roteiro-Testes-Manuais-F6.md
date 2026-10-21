# Roteiro de Testes Manuais — Jornadas ReamurCMS (F6-02)

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS |
| **Entrega** | F6-02 (Fase 6 · hardening e cutover) |
| **Pré-requisito de código** | HEAD com F6-01 aplicada (hardening + remoções de legado, ADR 0037) |
| **Ambiente-alvo** | XAMPP local (Windows) — Apache + MariaDB + PHP 8.x |

---

## 1. Para que serve este roteiro

Validar **manualmente**, no seu XAMPP, todas as jornadas de negócio do site
antes do cutover (F6-03). As baterias automatizadas já cobrem 281 asserts no
laboratório; aqui confirmamos a experiência real: cliques, telas, flashes,
e-mails e o que só o olho humano pega.

**Como registrar:** para cada passo, marque ✔ (passou) ou ✖ (falhou) na
coluna de registro e anote evidência (print, nº do pedido, horário). Ao
final, preencha o resumo da §11. Se algo falhar, siga a §12 ("como
reportar") — não tente consertar no código durante o teste.

**Legenda de prioridade:**
- 🔴 **bloqueador** — se falhar, para tudo e me reporta;
- 🟡 **importante** — anote e continue;
- 🟢 **cosmético/desejável** — anote e siga.

---

## 2. Antes de começar (setup)

- [ ] **2.1** XAMPP rodando: Apache + MySQL verdes no painel de controle.
- [ ] **2.2** Código do HEAD pós-F6-01 em `C:\xampp\htdocs\davidcms\`
       (se atualizou agora: extração do zip F6-01 + remoções do
       `REMOVER-F6-01.txt` executadas).
- [ ] **2.3** Upgrades SQL já aplicados — no phpMyAdmin, banco do site,
       confira que existem as tabelas `rms_webhook`, `rms_webhook_log`,
       `rms_mp_seller`, `rms_mp_payment`. **Se faltar `rms_mp_*`:** rode no
       phpMyAdmin → aba SQL os arquivos
       `dev/install/sql/upgrade_f5_02_mp_marketplace_foundation.sql` e
       `dev/install/sql/upgrade_f5_03_mp_checkout.sql` (nessa ordem; são
       idempotentes). **Se faltar `rms_webhook*`** (vieram do schema na
       F5-01): me avise antes de testar que eu te passo o CREATE exato —
       não recrie à mão.
- [ ] **2.4** Abra `dev/system/storage/logs/error.log` e anote a última
       linha/horário — tudo que aparecer **depois** disso durante os testes
       é suspeito até prova em contrário (registramos na §11).
- [ ] **2.5** Navegador limpo (janela anônima para as jornadas públicas;
       janela normal logada no admin). DevTools aberto na aba Network quando
       o passo pedir cabeçalhos.

**Convenções deste roteiro:** `<base>` = a URL do seu site, ex.
`http://localhost/davidcms/dev`. `<admin>` = `<base>/admin`.

---

## 3. Bloco A — Instalação e primeiro acesso

> Se você já tem uma instalação funcionando e não quer reinstalar, pule para
> o A3 (mas anote que pulou). A jornada de instalação limpa vale ser refeita
> ao menos uma vez num banco novo, porque o wizard mudou (F6-01: seed do
> `REAMUR_ERROR_DISPLAY`).

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| A1 | 🔴 | Instalação limpa: crie banco vazio no phpMyAdmin, apague `dev/catalog/config.php`, `dev/admin/config.php` e `dev/system/storage/install.lock` (se existirem), abra `<base>/install/` | Wizard abre no passo 1 (licença/termos) | |
| A2 | 🔴 | Avance: passo 2 (pré-requisitos) | Itens listados; nenhuma falha crítica de ambiente | |
| A3 | 🔴 | Passo 3: preencha banco (host `localhost`, usuário `root`, senha do MySQL do XAMPP), dados do admin (anote usuário/senha!) e e-mail | Sem erro de validação para entradas válidas; entrada inválida (ex.: e-mail quebrado) mostra erro amigável SEM stack trace | |
| A4 | 🔴 | Concluir até passo 4 | Mensagem de sucesso + links p/ site e admin; **sem** warnings PHP na tela | |
| A5 | 🟡 | Aplique os upgrades SQL da §2.3 (banco novo não os tem) | Tabelas `rms_webhook*`, `rms_mp_*` criadas | |
| A6 | 🟡 | Tente reabrir `<base>/install/` com o `install.lock` presente | **Página 403** informando que o sistema já está instalado (wizard selado) — o form NÃO reaparece | |
| A7 | 🔴 | Login admin em `<admin>` com o usuário criado | Entra no **Painel** com a grade **Módulos** | |
| A8 | 🟡 | Senha errada 3× | Erro amigável, sem stack; conta não quebra (login certo depois entra) | |
| A9 | 🟢 | DevTools → Network → recarregue qualquer página do admin e veja os response headers | Presentes: `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy-Report-Only` | |
| A10 | 🟡 | Logout | Volta à tela de login; usar "voltar" do navegador NÃO devolve área logada (pede login de novo) | |

**Nota A9:** os 3 primeiros headers são da F2-18; `Permissions-Policy` e
`CSP-Report-Only` são **novos da F6-01**. Se faltar algum, anote qual.

---

## 4. Bloco B — Backoffice: conteúdo do site

A grade de módulos do Painel é a âncora de navegação. Cada módulo abaixo:
criar → salvar → conferir no **site público** (front).

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| B1 | 🔴 | **Categorias**: criar categoria "Teste F6" | Aparece na grade; sem erro | |
| B2 | 🔴 | **Portfólio**: criar projeto "Projeto Teste F6" na categoria acima (título, descrição, imagem se quiser) | Salvo na grade; projeto aparece no front em `<base>` / página de projetos com link funcionando | |
| B3 | 🟡 | **Páginas**: criar página "Página Teste F6" | Acessível no front pela rota da página; conteúdo renderiza | |
| B4 | 🟡 | **Depoimentos** e **Logos de Clientes**: criar 1 de cada | Aparecem no front (seção correspondente da home) | |
| B5 | 🟡 | **Idiomas** (front): trocar PT-BR → English → Español pelos seletores do cabeçalho | Interface e seus textos de conteúdo mudam; voltar ao PT-BR | |
| B6 | 🟢 | Home (front): rolar a página inteira | Hero 3D carrega, sem "buracos", sem erros visíveis de CSS/JS (console do DevTools sem erros vermelhos graves) | |
| B7 | 🟡 | Editar o projeto B2 (mudar título), salvar, ver no front | Mudança refletida na hora (sem cache cego) | |
| B8 | 🟡 | Excluir a página B3 | Some do front (404 amigável ao acessar a URL antiga — anote a URL p/ F6-03) | |
| B9 | 🟢 | **Ferramentas**: abrir o módulo e ver as ações disponíveis | Tela abre sem erro (não execute ações destrutivas agora) | |

---

## 5. Bloco C — Jornadas públicas críticas (sem login)

> Estas duas jornadas **são o negócio**: contato e orçamento. Use a janela
> anônima. Se falharem, é 🔴 bloqueador total.

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| C1 | 🔴 | `<base>` → formulário de contato ("Let's have a chat?"): envie com e-mail inválido | Erro de validação ao lado do campo; formulário **repopulado** (não perde o que você digitou); sem stack trace | |
| C2 | 🔴 | Contato válido: nome, e-mail válido, mensagem "Teste F6 contato" | Flash de sucesso e redirect (a página recarrega limpa, mensagem some) | |
| C3 | 🔴 | No admin → **Mensagens**: a mensagem C2 está lá, status nova/não lida, com o IP registrado | Registro presente e íntegro | |
| C4 | 🟡 | E-mail de notificação ao dono do site chegou? | **Se SMTP estiver configurado (F4-05):** e-mail na caixa. **Se não estiver:** nada chega e NENHUM erro aparece na tela pública (a falha é silenciosa por design — pode constar no `error.log`; não é falha do teste) | |
| C5 | 🔴 | Orçamento: página de orçamento → envie sem escolher serviço | Erro de validação claro | |
| C6 | 🔴 | Orçamento válido: escolha 2 serviços + briefing "Teste F6 orçamento", contatos válidos | Flash de sucesso **com o número do pedido**; anote o nº | |
| C7 | 🔴 | Admin → **Pedidos**: abra o pedido do passo C6 | Total 0,00 (orçamento), dados do cliente, itens/serviços e histórico inicial presentes | |
| C8 | 🟡 | Rate limit: envie contato/orçamento **6 vezes seguidas** no minuto | A 6ª (ou antes) é bloqueada com mensagem amigável de limite; NADA é gravado duplicado | |
| C9 | 🟢 | Acesse uma URL que não existe, ex. `<base>/pagina-inexistente` | Página 404 amigável do site (não erro cru do Apache/PHP) | |
| C10 | 🟢 | Anti-spam (opcional, via DevTools): no form de contato, torne o campo oculto `company` visível/insira valor e envie | Aparenta sucesso, mas NADA aparece em **Mensagens** (bot é enganado) | |

---

## 6. Bloco D — Segurança, usuários e ACL

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| D1 | 🔴 | **Grupos de Usuários**: criar grupo "Redator F6" com acesso APENAS a `catalog/project` e `cms/page` (marque acesso e modificar só neles) | Grupo salvo | |
| D2 | 🔴 | **Usuários**: criar usuário "redator.f6" nesse grupo, senha forte | Usuário salvo | |
| D3 | 🔴 | Logout → login como redator.f6 | Painel mostra **só** Portfólio e Páginas; demais módulos NÃO aparecem (ACL manda na âncora) | |
| D4 | 🔴 | Como redator.f6, tente abrir direto a URL `<admin>/index.php?route=setting/setting` | Página de **permissão negada** amigável (não erro PHP) | |
| D5 | 🟡 | **Editar perfil** (redator.f6): trocar a própria senha | Sucesso; logout → login com senha NOVA funciona; senha velha não | |
| D6 | 🟡 | Voltar ao admin principal e **excluir** redator.f6 + grupo (limpeza) | Removidos sem erro | |
| D7 | 🔴 | Modo de exibição de erros (F6-01): abra `dev/catalog/config.php` e veja a linha `REAMUR_ERROR_DISPLAY`. **Instalação nova (Bloco A): vem `true`** (modo dev); **instalação antiga: não tem a linha** (= false, seguro por omissão). Navegue pelo site | Com o valor atual, nenhum warning/notice crú aparece na navegação normal do público. **Antes do cutover:** essa linha precisa estar `false` (ou removida) — o F6-03 cobra isso no checklist | |
| D8 | 🟡 | (Opcional) Com o define em `true`, force um erro bobo (ex.: rota admin inexistente estando logado) | Detalhe técnico aparece (modo dev explícito); **volte para `false` ao final** | |

---

## 7. Bloco E — Webhooks (F5-01)

> Testa o sistema de eventos do CMS. Use um receptor: recomendo
> <https://webhook.site> (copie "Your unique URL").

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| E1 | 🟡 | Admin → **Webhooks** → novo endpoint: evento `order_add`, URL `https://` do webhook.site (o cadastro **só aceita HTTPS**), descrição "Teste F6", status ativo | Salvo na grade; **o segredo não aparece em claro na grade** | |
| E2 | 🔴 | Repita o passo C6 (novo orçamento) | No webhook.site chega um POST com headers `X-Reamur-Event: order_add`, `X-Reamur-Delivery`, `X-Reamur-Signature` e corpo JSON com os dados do pedido | |
| E3 | 🟡 | Admin → Webhooks → **entregas** do endpoint ("Entregas de …") | A entrega consta com HTTP 200 (2xx = sucesso) | |
| E4 | 🟡 | Edite o endpoint para uma URL que passa na validação mas falha na entrega (ex.: `https://localhost:9/nada`), gere outro orçamento | Nenhum erro na tela pública; após as retentativas automáticas, a entrega consta esgotada/HTTP 0 na tela de entregas; `error.log` tem a linha honesta do esgotamento | |
| E5 | 🟢 | Desative o endpoint E4 (ou restaure a URL boa) | Grade reflete; limpeza concluída | |

---

## 8. Bloco F — Mercado Pago Marketplace (F5-02 + F5-03)

> ⚠️ **Situação desta entrega:** a validação com o **sandbox real do MP**
> depende das suas credenciais de teste (pendente). O bloco está dividido:
> **F0 sempre é testável**; F1+ só quando as credenciais existirem — pode
> executar este bloco depois e me devolver só a tabela dele.

### F0 — Sem credenciais (testável agora)

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| F0.1 | 🟡 | Admin → **Mercado Pago Marketplace** sem config salva | Aviso de config pendente + botão **Conectar vendedor DESABILITADO** | |
| F0.2 | 🟡 | Tente salvar config inválida: `mp_app_id` com letras e/ou redirect `http://...` | Rejeitado com erro amigável; nada gravado (recarregue e confira campos vazios) | |
| F0.3 | 🔴 | Admin → **Pedidos** → no pedido do C6 clique **💳 Link de pagamento MP** (sem vendedor conectado) | Redireciona à tela do Marketplace com o flash **"Nenhum vendedor conectado ativo. Conecte um vendedor primeiro."**; nenhum link quebrado é criado | |

### F1 — Com credenciais sandbox (quando houver)

Pré-requisitos desta parte (do `docs/MercadoPago-Marketplace.md`):
1. App de **teste** no painel Mercado Pago Developers → `App ID` e
   `Client Secret` de teste;
2. **Conta de teste vendedor** e **conta de teste comprador** (o painel
   cria ambas);
3. **URL pública HTTPS**: o MP não conversa com `localhost`. Use um túnel,
   ex.: `ngrok http 80` (XAMPP) → URL `https://xxxx.ngrok-free.app`. O
   `redirect_uri` do OAuth **precisa começar com https://** (validação do
   sistema). Configure o site para responder pelo túnel durante este bloco.

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| F1 | 🔴 | Salvar config: App ID numérico, Client Secret (≥16), env `test`, redirect URI `https://<túnel>/admin/index.php?route=setting/marketplace.callback` | Salvo; **o segredo nunca volta preenchido** na tela; botão Conectar habilita | |
| F2 | 🔴 | Conectar vendedor → autorizar com a **conta de teste vendedor** | Redireciona ao MP, volta ao admin; grade mostra o vendedor (nickname/e-mail) — **tokens nunca aparecem** | |
| F3 | 🔴 | Admin → **Pedidos** → **💳 Link de pagamento MP** no pedido C6 (ou novo orçamento) → depois abra **Mercado Pago Marketplace** | Flash "Link de pagamento criado: …"; seção **Links de pagamento (Checkout Pro)** mostra o pedido com valor, **fee 10%**, vendedor, status `pending` e o link **público** (`catalog/...checkout/payment.pay\|TOKEN`). Clicar de novo no botão do mesmo pedido **reutiliza** o link (não duplica) | |
| F4 | 🔴 | Abrir o link público em janela anônima | Redireciona (302) ao checkout **sandbox** do MP | |
| F5 | 🔴 | Pagar como **comprador de teste** (cartão de teste aprovado do MP) | Pagamento aprovado no MP | |
| F6 | 🟡 | Notificação: com o túnel no ar, o MP chama `checkout/payment.notify` sozinho. **Sem túnel:** simule pelo prompt (Prompt de Comando) — substitua `ID` pelo id do pagamento (visível no painel MP do vendedor) e ajuste a base:<br>`curl -X POST "https://<túnel-ou-base>/catalog/index.php?route=checkout/payment.notify&type=payment&data.id=ID" -H "Content-Type: application/json" -d "{\"type\":\"payment\",\"data\":{\"id\":\"ID\"}}"` | Resposta `ok:processed`; reenviar o mesmo → `ok:already-processed` (idempotente) | |
| F7 | 🔴 | Pedido no admin: status mudou p/ **Pago** (4), histórico com o payment_id; grade marketplace mostra payment approved | Tudo refletido; webhook order_history_edit (se E1 ativo) também dispara | |
| F8 | 🟡 | Link público do pedido pago, de novo | Não permite pagar 2× (aviso de já pago ou not_found, conforme o caso) | |
| F9 | 🟢 | Reembolsar pelo painel MP e re-sincronizar (se houver ação) | Fora de escopo F5 — anote comportamento se testar | |

**Se F2 falhar com erro de redirect_uri:** confira se a URL cadastrada no
app do MP Developers é EXATAMENTE a `https://…/admin/index.php?route=…`.

---

## 9. Bloco G — Saneamento geral (fecha o roteiro)

| # | 🔴🟡🟢 | Passo | Resultado esperado | ✔/✖ |
|---|--------|-------|--------------------|-----|
| G1 | 🔴 | Abra `error.log` e compare com o marcador da §2.4 | Nenhum erro novo além dos **esperados** (E4 webhook esgotado; C4 rate limit; falha de e-mail sem SMTP — anotados como esperados). Qualquer outro erro novo = reportar (§12) | |
| G2 | 🟡 | `<base>/index.php?route=common/sitemap` (ou link do rodapé) | Mapa do site renderiza com as páginas/projetos atuais — sem itens mortos | |
| G3 | 🟢 | Responsivo rápido: DevTools modo mobile na home + formulário de contato | Layout não quebra; formulário utilizável | |

---

## 10. Reexecutar depois (regressão)

Sempre que uma correção entrar após este roteiro, reexecutar **no mínimo**
o bloco da área tocada + Bloco C (jornadas críticas) + G1 (error.log).

---

## 11. Resumo para devolver (preencha)

- Data do teste: ____  Ambiente: XAMPP (PHP __.__ / MariaDB __.__)
- Blocos executados: A B C D E G ☐ | F0 ☐ | F1–F9 ☐ (se houver credenciais)
- Passos com ✖ (liste): ________________________________________
- Erros novos no `error.log` (hora + primeira linha): ______________
- URLs antigas **anotadas** (páginas/projetos removidos — insumo p/ F6-03):
  ________________________________________
- **Veredito:** ☐ apto a seguir p/ F6-03 · ☐ apto com ressalvas (quais?) · ☐ bloqueado

## 12. Como reportar uma falha

1. Nº do passo (ex.: C6) + o que você fez exatamente;
2. O que apareceu (print se possível) **vs.** o resultado esperado do passo;
3. Trecho do `dev/system/storage/logs/error.log` no horário do teste;
4. NÃO altere código/banco depois do erro — me manda o conjunto acima que eu
   reproduzo no laboratório antes de propor correção.

---

*Critério de saída da F6-02: blocos A–E + G com 🔴 todos ✔. O Bloco F
completo (sandbox real) é pré-requisito separado do cutover de pagamentos,
não deste roteiro — segue aguardando suas credenciais de teste do MP.*
