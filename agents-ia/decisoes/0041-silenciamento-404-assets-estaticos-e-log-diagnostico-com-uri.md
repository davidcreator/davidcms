# ADR 0041 — Silenciamento de 404 de assets estáticos e log de rota com REQUEST_URI

| Campo | Valor |
|---|---|
| Status | Aceito |
| Data | 2026-10-21 |
| Entrega | F6-06 (Pós-Fase 6 · polimento) |
| Prova | `tools/e2e/e2e_f606.sh` **10/10** + suíte completa **28/321/0** |

## Contexto

O `error.log` do XAMPP (commitado acidentalmente no push F6-05, o que fez
o problema aflorar) trazia **27 entradas em 2 dias** de
`Route not found: catalog/view/js/three/mainjs.index` — em bursts de 1-4
por sessão de navegação. Investigação:

1. O arquivo `catalog/view/js/three/main.js` **existe** e é referenciado
   corretamente pelo `header.twig` com extensão e import map; os scenes
   importam só `.js` completos.
2. O formato do log vem de `system/engine/action.php`:
   `Route not found: %s.%s` → rota `catalog/view/js/three/mainjs`, método
   `index`. O router (`startup/router.php::resolve()`) sanitiza o path
   **removendo `.`** para montar a rota interna — logo, a URI real era
   irreversivelmente mascarada (`main.js`, `main-js`, `main.js.` →
   todas viram `mainjs`). O emissor exato permanece não-reprodutível.
3. Risk: o dispatcher loga **toda** sonda de asset faltante (browsers,
   scanners em produção), e o runbook §5.8 exige `error.log` limpo como
   métrica diária de saúde — ruído constante destrói essa métrica.

## Decisão

1. **404 nativo para assets estáticos faltantes** (`catalog/.htaccess` e
   `admin/.htaccess`, antes do front controller):
   ```
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule \.(?:js|css|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|tiff?|otf|eot|mp4|webm|m4a|mp3)$ - [R=404,L]
   ```
   Arquivos existentes nunca são atingidos (`!-f` sai antes); faltantes
   recebem 404 binário do Apache **sem PHP, sem dispatcher, sem log**.
   `xml`, `txt` e `json` ficam **fora de propósito**: rotas dinâmicas
   reais com ponto (`sitemap.xml`) precisam continuar chegando ao app
   (check 6 da f606 trava isso contra regressão).
2. **Log do dispatcher enriquecido**: `system/engine/router.php` passa a
   escrever `<mensagem> — request: <REQUEST_URI>` no fallback. A mensagem
   continua primeiro (greps antigos compatíveis); a URI revela a URL
   original mesmo quando a sanitização mascara a rota. Cobre o caso dos
   **probes sem extensão** (ex.: `main-js`), que não podem entrar na regra
   de extensão: caem na 404 humana da app (experiência ok) e o log passa
   a ser auto-diagnosticável.
3. Sem SQL, sem mudança de template, sem mudança de rotas — a 404 humana
   estilizada continua para navegação real (check 7).

## Consequências

- `error.log` de produção volta a significar algo: qualquer linha nova é
  evento real, com a URI original ao lado.
- Probes/scanners de internet (`wp-content/foo.js`, `.env`, etc.) vão
  bater no 404 nativo — de graça.
- Não elimina 100% das causas possíveis (probe sem ponto cai na app),
  mas qualquer recorrência traz a evidência da própria origem.
- Zero impacto no mapa 301 do ADR 0038 (avaliado antes e com padrões
  disjuntos), zero impacto em `sitemap.xml`.

## Provas

- `e2e_f606.sh` **10/10**: asset real 200 (`main.js` com conteúdo),
  asset faltante 404 nativo sem markup da app (catalog **e** admin), log
  sem ruído nos probes, `sitemap.xml` dinâmico intacto, 404 humana
  amigável preservada, log com `request: URI`, sanidade home/SEO/301.
- Suíte completa pós-fix: **28 baterias / 321 asserts / 0 fail**.
