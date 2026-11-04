# Correção — "Mapa de sites inválido" ao personalizar um tema (Frente W)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `004a99cd88ef5e28a9b8dcc8a2a68f2755ef6d09` (frente V aplicada 7/7).  
**Sintoma relatado:** ao personalizar um tema, o Admin retornava
*"Mapa de sites inválido: revise host, slug e idioma."* e não salvava.

## 1. Causa raiz (reproduzida, não inferida)

`dev/admin/view/template/design/theme_form.twig` renderiza **8 blocos**
`name="sites[<id>][host|slug|language|enabled]"`, um para cada ID allowlistado.
`theme-sites.js` desabilita os campos das linhas desmarcadas no boot; se o script
não carregar, ou se o navegador enviar o formulário sem essa desabilitação,
**todos os 8 blocos chegam ao POST com `host=''`**.

`buildDraftConfig()` (em `dev/admin/controller/design/theme.php`) montava as 8
entradas e chamava `ThemeSites::normalize()`. Essa função é fail-closed e exige
`validHost($host)` para **cada** entrada — `host=''` falha, `normalize()`
devolve `null`, e o controller responde com `error_sites` **antes** de qualquer
`save()`.

Probe A/B executado sobre o `edit()` real do HEAD W: **9 de 12** POSTs falhavam,
incluindo `oito_vazios`, `um_valido_sete_vazios` e `cena_v_com_vazios` (a cena
persistente da frente V também ficava bloqueada). `twig_error=0` nos 12. Esse
cenário não estava coberto pelos probes A–V, que montavam POSTs **sem** a chave
`sites`; foi um ponto cego dos testes anteriores, não uma regressão introduzida
pela V.

## 2. Correção

| Arquivo | Mudança |
|---|---|
| `dev/admin/controller/design/theme.php` | Antes de `normalize()`, descarta a linha **sem host, sem slug e desmarcada**. Quando há erro real, acrescenta o **ID allowlistado** do bloco à mensagem (`(shop)`) para diagnóstico. Cache key do JS: `-theme-sites-t` → `-theme-sites-w`. |
| `dev/admin/view/js/theme-sites.js` | No `submit`, libera os campos das linhas **com host preenchido** (mesmo desabilitadas), preservando a política de sites temporariamente desligados; linhas vazias continuam `disabled`. |
| `docs/GUIA-MULTISITE.md` | Documenta a regra de linhas em branco e o limite que permanece. |
| `docs/CORRECAO-MAPA-DE-SITES.md` | Este documento (novo). |

**Nada foi relaxado.** Continuam inválidos e com **zero gravações**: host com
scheme, porta (`:8080`), underline, caminho, `..`, `.-`, `-.`, mais de 253
caracteres; slug fora de `^[a-z0-9_-]{1,48}$`; idioma fora de
`pt-br`/`en-us`/`es-eu`; mais de 8 sites; ID fora da allowlist; checkbox marcado
sem host; chave extra ou valor não escalar. `ThemeSites::normalize()`,
`hostKey()`, `match()`, `byId()`, `validateConfig()` e `MAP_KEYS` **não foram
alterados**. Nenhuma chave de tradução nova (418 ×3 intactas), nenhum `|raw`
novo, nenhuma migration, coluna, `schema.sql`, manifesto ou hash de release.

O servidor é soberano: com o JS antigo em cache ou com JavaScript desligado, a
correção do controller já resolve o bloqueio. O cliente é uma melhoria
independente (preservação de config), não um pré-requisito.

## 3. Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (3 substituem,
1 é novo). Não há patches nem manifestos a atualizar — os 4 paths estão
**LIVRES** nos 14 `CHECKSUMS*.txt` (90 paths cobertos). Depois:
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.

## 4. Limitações

PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**.
Não houve PHP nativo, Composer, PHPStan, MySQL, sessão Admin autenticada,
navegador real, DNS/vhost, E2E, staging, deploy ou CI remoto verificado.
**Nenhuma escrita no GitHub foi feita por nós.** F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`; BDREditor/F9-103 pausado;
P2/F9-18 não reabertos; a limitação de hreflang da frente N permanece.
**CI verde/health ≠ release aprovada.**
