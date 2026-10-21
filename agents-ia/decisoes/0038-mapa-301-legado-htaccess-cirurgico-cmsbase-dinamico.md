# ADR 0038 — Mapa 301 do legado: regras cirúrgicas no catalog/.htaccess com base dinâmica (CMSBASE)

- **Status:** Aceita — 2026-10-21 (F6-03, parte 1/2)
- **Contexto:** Análise §9 define o mapa de redirecionamentos do site
  legado (`/catalog/tools/open?id=N`, `/catalog/portfolio/{slug}`, …) para
  as URLs do ReamurCMS antes do cutover. Investigação ao vivo no lab
  mostrou fatos que moldam a solução:
  (a) as keywords de segmento único (`jwa`, `cookies`, `fluxa`, …)
      resolvem **dentro do app** — caminho real hoje é `/catalog/<kw>`,
      não `/<kw>` (a decisão "home na raiz" do §9 é de topologia de
      hospedagem, ver `docs/Runbook-Cutover-F6.md`);
  (b) `dev/catalog/.htaccess` já traz o front controller
      (`^(.*)$ index.php`), e regras per-dir do diretório mais profundo
      vencem — regras no `dev/.htaccess` **nem eram avaliadas** para essas
      URLs (descoberto em teste: tudo 404);
  (c) `/catalog/index.php` é o endpoint legítimo do **POST de notificação
      do Mercado Pago** (F5-03) e de webhooks/URLs gramaticais;
  (d) `mod_rewrite` não vinha habilitado no Apache do lab (o XAMPP e
      hospedagens cPanel trazem por padrão).

- **Decisões:**

  1. **Regras 301 vivem em `dev/catalog/.htaccess`, ANTES do front
     controller**, e são **cirúrgicas**: somente `tools/open` (por id) e
     `portfolio/{slug}`. **Sem catch-all em /catalog/** — um catch-all
     quebraria o POST do MP, as entregas de webhook e as URLs
     gramaticais que ainda funcionam.

  2. **Ferramentas por id (determinístico):** `id=1..4` →
     `fluxa/quotia/vertex/solis` (ids e nomes reais, seed F3-01);
     id ausente/desconhecido → home pública (301, nunca 404). Id aceito
     em qualquer posição da query (`(?:^|&)id=N(?:&|$)`) — o formato
     documentado em produção era `?id=N&src=home`. Query **descartada**
     no destino (`?` final) — não vazar `?id=` na URL canônica.

  3. **Portfólio com slug preservado:** `portfolio/{slug}` → `{slug}` —
     uma regra cobre os 10 projetos reais, **incluindo os slugs
     históricos** (`pitbul-color` com typo, `farmer`, `chameleon`, `rc`,
     `classic-car`) preservados de propósito no seed F3-01 exatamente
     para este mapa.

  4. **Base dinâmica `CMSBASE` em vez de `RewriteBase` fixa:** destinos
     relativos sem RewriteBase vazavam o **caminho físico**
     (`/var/www/html/...`) no `Location` (comprovado em teste). A
     solução: `RewriteCond %{REQUEST_URI} ^(.*/)catalog/` +
     `[E=CMSBASE:%1]` e destinos `%{ENV:CMSBASE}catalog/...` — o prefixo
     real da URL é capturado do próprio request, então **a mesma regra
     funciona em subpasta (XAMPP `/davidcms/dev/`) e na raiz de um
     domínio sem edição**. Zero ajuste de htaccess no cutover.

  5. **Desvios do §9 registrados (honesto, com justificativa):**
     - `/catalog/cookies` e `/catalog/politicas-internas`: **já são** as
       URLs canônicas atuais (200 via `seo_url`). O 301 para raiz
       (`/cookies`…) hoje apontaria para 404 — o §9 pressupunha a
       topologia raiz-pura; quando/SE o cutover adotá-la, essas duas
       regras entram (runbook §topologias).
     - âncoras (`/catalog/#profile` → `/#…`): fragmento **não chega ao
       servidor**; o browser reaplica o `#fragment` após qualquer 301 —
       tratamento é client-side (fora de escopo server).
     - Blog externo (davidcreator.com): fora de escopo (Análise §9/§12,
       backlog pós-lançamento).

  6. **mod_rewrite + AllowOverride** são pré-requisito declarado
     (XAMPP/cPanel: já ativo; lab: `rebuild_lab.sh` agora roda
     `a2enmod rewrite`). O bloco fica sob `<IfModule mod_rewrite.c>`
     para degradar sem 500 se ausente.

- **Consequências:** `dev/.htaccess` permanece mínimo (inalterado); o
  front controller e todas as rotas existentes ficam intactos; nenhuma
  mudança de código PHP; sem SQL; sem segredos. Efeito prático: as URLs
  legadas indexadas/linkadas passam a 301 para as canônicas reais.
- **Provas:** `tools/e2e/e2e_f603.sh` **10/10** (301+Location+follow 200
  em tools/portfolio/legado/home-fallback; POST notify não redirecionado;
  gramatical/cookies sem redirect-loop; admin e jornadas intactas) e
  suíte completa verde após a mudança.
