# Índice — Frente BF (correção do botão + Gerenciador de Temas)

Pacote: `ReamurCMS-Frente-BF-Gerenciador-Temas.zip` · HEAD `6ee26372…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em `CHECKSUMS*.txt` · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BF.md` | Causa raiz do bug, gerenciador, segurança, validação |
| 2 | `INDICE-BF.md` | Este índice |
| 3 | `dev/admin/view/js/theme-package.js` | Evento real no botão + inspeção Twig/ThreeJS |
| 4 | `dev/admin/view/template/design/theme_list.twig` | Botão `data-theme-import-open`, cartões, prévia enriquecida |
| 5 | `dev/admin/controller/design/theme.php` | `index()` injeta allowlists e contagens |
| 6 | `dev/admin/language/en-us/design/theme.php` | 25 rótulos EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 25 rótulos PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 25 rótulos ES-EU |
| 9 | `tools/verify-theme-manager.cjs` | Verificador Node puro |
| 10 | `evidencia/jsdom-harness.txt` | 47 OK / 0 FALHAS |
| 11 | `evidencia/validacao.txt` | `php -l` 4/4 + guardas |

## O bug

`href="#theme-import"` → `<details>` **fechado** = nenhum evento visível.
Agora: `data-theme-import-open` + `panel.open = true` + foco na textarea,
com `href` mantido como fallback sem JS.

## Gerenciador

| Capacidade | Conteúdo |
|---|---|
| **Pacotes Twig** | 25 fontes allowlistadas como strings validadas |
| **Pacotes ThreeJS** | 5 cenas + 6 presets + `kaz_scene` como dados |
| **Prévia Twig** | nº de fontes, lista de chaves, status allowlist |
| **Prévia ThreeJS** | cena, slot, camadas/versão, tipos, status allowlist |
| **Demais blocos** | tokens, condições, seções, componentes, schedule, mídia, sites |

Allowlists injetadas pelo servidor (`template_keys_list`, `scene_list`).
O cliente **sinaliza**, o `ThemePackage::decode()` **decide**.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow,
model ou biblioteca precisa ser tocado.

```bash
node tools/verify-theme-manager.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`node --check` ✅ · `php -l` **4/4** · **jsdom 47 OK / 0 FALHAS**
(reprodução do bug → clique abre) · verificador 0 FALHAS ·
`verify-checksums` 142 arquivos / 0 divergentes · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não cria pacotes com `.twig`/`.js` executáveis, não faz upload de ZIP, não
escreve em disco, não amplia `TEMPLATE_KEYS`, não faz migration, não altera
contrato congelado, não decide D01–D10, não escreve no GitHub pelo agente e
**não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**
