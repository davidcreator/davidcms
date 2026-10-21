# ADR 0040 — Verificação remota de produção (`check_producao.sh`) e ciclo pós-hibernação

| Campo | Valor |
|---|---|
| Status | Aceito |
| Data | 2026-10-21 |
| Entrega | F6-05 (Fase 6 · hardening e cutover) |
| Prova | `tools/check_producao.sh` **15/15** contra deploy simulado (`/prodtest`) + suíte 27 e2e intacta |

## Contexto

O cutover (runbook §5) exige uma bateria de verificações HTTP **de fora**
no domínio real, sem credenciais e sem tocar nada. Até aqui ela era uma
lista manual de comandos. Também foi necessário recompor o lab do sandbox
após uma hibernação — que expôs dois efeitos colaterais do ambiente
descartável duramente capturados nesta rodada.

## Decisão

1. **`tools/check_producao.sh <dominio>`** — verificação remota, read-only,
   sem sessão, idempotente, estilo e2e (PASS/FAIL/WARN, exit ≠ 0 em falha).
   15 checks: catálogo/TLS; força HTTPS (HTTP aberto = FAIL, sem :80 = WARN);
   raiz→catalog; aliases contato/orçamento; SEO de projeto (projeto-prova
   parametrizável via `PROJ=`); mapa 301 ×2 + follow (ADR 0038 em prefixo
   real); sitemap.xml + XML válido; robots.txt portátil (sem vestígio de
   ambiente dev/localhost); admin protegido (dashboard sem sessão nunca
   200 com "Modules"); `system/` selado (framework.php e default.php
   nunca 200); logs/config sem conteúdo sensível via web; **erro limpo**
   (rota inexistente = 404 sem stack/path — prova do `error_display` off);
   headers de segurança (nosniff fail; frame/referrer warn — o cPanel pode
   setá-los no painel); ausências dev-only (`install/ tests/ agents-ia/
   docs/ .git/ prod/ planejamento/` ≠ 200/30x — **prova de fora que só a
   build subiu**); TTFB (WARN apenas).
2. **Uso pós-cutover** (runbook §5): `bash tools/check_producao.sh
   https://davidalmeida.xyz` substitui a lista manual (que fica como
   diagnóstico de segunda camada). Serve também com `http://` em host de
   teste (os checks de TLS degradam para skip/aviso automaticamente).
3. **Provedor**: a hospedagem decide o destino dos WARNs — cPanel
   compartilhado (escolha do dono do projeto) é o alvo: "Force HTTPS",
   redirects e demais botões do painel cobrem os avisos não-fatais.
4. **Pós-hibernação do sandbox (lição registrada)**: o snapshot best-effort
   (~cap) guarda arquivos, mas **cortou** `prod/system/storage/vendor/
   composer/*` (a pasta ficou com o stub `autoload.php` e sem
   `autoload_real.php`) e a ADR 0039 recém-criada. Recuperação: rebuild
   do lab (`tools/e2e/rebuild_lab.sh`, que reinstala pacotes + refaz
   vendor do dev/) → `bash tools/build_prod.sh` (prod/ é artefato derivado
   — regenerável em segundos) → ADR recuperada do zip da entrega.
   Conclusão operacional: **nunca tratar prod/ como fonte de verdade**;
   quem importa é dev/ + o script (já gitignored por decisão da ADR 0039).

## Consequências

- O cutover ganha um "go / no-go" objetivo: **15 verdes ou não sobe**.
- O script roda de qualquer máquina (XAMPP, sandbox, CI) sem dependências
  além de curl+bash (+bc só p/ métrica); falha ≠ dano (GETs + um POST de
  notify com `data.id=0`, já tolerado e provado na f604).
- Os avisos não reprovam de propósito: itens dependem do painel do cPanel
  (Force HTTPS, headers), não da build.
- A bateria `e2e_f604.sh` (10/10) continua intacta — camada LOCAL da
  build; `check_producao.sh` é a camada REMOTA do domínio real.

## Provas

- `bash tools/check_producao.sh http://127.0.0.1:8090/prodtest` →
  **15 pass / 0 fail / 0 avisos** (deploy simulado da build + banco
  wizard-less, exatamente como o cPanel receberá).
- Debug desta rodada documentado no registro: antes da recomposição do
  vendor, o check 1 (`catalogo-online`) flagou o 500 causado pela pasta
  truncada — a ferramenta diagnóstica funcionando no primeiro uso.
