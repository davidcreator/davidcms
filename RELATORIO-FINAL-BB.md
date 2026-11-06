# Relatório Final — Frente BB (rename R2 · Fase B1)

**Data:** 2026-11-06 · **HEAD:** `700d104cc6717e79c1677397ffb771491b456d1a`
**CI remoto:** 7/7 success · **Tipo:** **código aplicável** — nomes visíveis na UI.

## Por que avancei sem a resposta do formulário

Você aplicou a BA e pediu para prosseguir pela quarta vez consecutiva. A
pergunta sobre o alcance do R2 (B1 / B1+B2 / B1+B2+B3) estava travando o
trabalho. **B1 é subconjunto de todas as três opções** — executá-lo não decide
nada por inferência: apenas adianta o que é comum, deixando B2 e B3 para a sua
decisão explícita.

## O que foi feito

Substituição dos nomes **onde o usuário lê**, nos **9 arquivos do Admin**:

| De | Para |
|---|---|
| `BDREditor` | **`BDREditor-Content`** |
| `KAZTheme-Editor` | **`KAZEditor-Theme`** |
| `KAZTheme` (isolado, 2 rótulos) | **`KAZEditor-Theme`** |

**66 ocorrências → 0 remanescentes.**

| Arquivo | Ocorrências |
|---|---|
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 8 |
| `dev/admin/view/template/cms/page_form.twig` | 1 |
| `dev/admin/view/template/cms/page_content_form.twig` | 2 |
| `dev/admin/language/{pt-br,es-eu,en-us}/cms/page.php` | 5 / 4 / 4 |
| `dev/admin/language/{pt-br,es-eu,en-us}/blog/blog.php` | 14 / 14 / 14 |

Foram atualizados: o nome na topbar do editor de temas, textos de painel,
`aria-label` de toolbar e canvas, mensagens de validação de documento e strings
de ajuda/intro nos três idiomas, além de comentários de código que citavam o
nome antigo.

### Invariantes estruturais (verificados arquivo a arquivo)

- **0** ocorrências em chaves `$_['...']` — nenhuma chave de idioma mudou;
- `class="..."`, `id="..."`, atributos `data-*` e contagem de `|raw`
  **idênticos** ao original;
- sem `BDREditor-Content-Content`, `KAZEditor-Theme-Editor` ou
  `KAZEditor-Thene` (ordem de substituição controlada);
- nenhum dos 9 arquivos aparece em qualquer `CHECKSUMS*.txt` → **nenhum
  manifesto a regenerar**.

## O que foi PRESERVADO (B2 e B3 não rodaram)

| Preservado | Motivo |
|---|---|
| Documentos históricos `docs/F9-*`, `docs/F11-*`, `RELATORIO-FINAL-*`, ADRs | reescrever história exigiria decisão explícita (B2) |
| Contratos por nome: `BDREditor-Table-Contract.json`, `-Verification.json`, `-Base-Hashes.json` | contratos congelados / `frozen_artifacts_sha256` |
| Nomes de arquivos e identificadores: `blog-block-editor-kaz.js`, `kaz-theme-editor.js`, `kaz_theme_editor.twig`, `kaz-theme-editor-ergonomia.js` | B3 — alto risco (`<script src>`, probes, 61 checkers e2e) |
| 14 manifestos `CHECKSUMS*.txt`, hashes, `tools/decision-matrix.json` | integridade do CI |
| `dev/catalog/view/template/partial/theme-scene-block.twig` | catálogo público; era só comentário Twig invisível |
| `dev/install/sql/**`, `schema.sql`, `.github/workflows/**`, DOM, renderer, JS de ~600 KB | fora de escopo |

Inventário original do R2: **179 arquivos / 762 ocorrências**. Após o B1
restam **113 ocorrências** em documentos/contratos/identificadores para B2+B3.

## Validação

| Verificação | Resultado |
|---|---|
| `php -l` (PHP 8.5.12 WASM) nos 6 `.php` de idioma | ✅ 6/6 |
| Twig: `if/endif`, `for/endfor`, `block/endblock` balanceados | ✅ 3/3 |
| Invariantes DOM/chaves/`|raw` | ✅ 9/9 arquivos |
| `tools/verify-rename-b1.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes |
| `repo-health.sh` na réplica fiel | ✅ **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## Limites

Nenhuma escrita do agente no GitHub, nenhum migration, contrato congelado,
hash, `schema.sql`, workflow, DOM, renderer ou catálogo alterado. Nenhuma
conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão
das **10 `PENDING_DECISION`**, nenhuma declaração de release.
**Aceite visual** da seção "Ferramentas internas" e do B1 no Admin real segue
**pendente** — não foi declarado aprovado.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito **APROVADO** da AP mantido. CI verde,
guardas estáticos e PHP WASM **não significam release aprovada**.

## Próximos passos (dependem de você)

1. **B2** — renomear arquivos de `docs/` e contratos por nome (regenera 13
   manifestos e atualiza links) — sim ou não?
2. **B3** — renomear identificadores de código — sim ou não?
3. **Aceite visual** no Admin real: seção + nomes novos.
4. Passos do roteiro: health do Admin real (2), decisões D01–D10 com o pacote
   **AR** (3), verificação F918 com o **AS** e staging (4).
