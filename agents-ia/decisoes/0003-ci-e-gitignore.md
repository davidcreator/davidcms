# ADR 0003 — Correção do CI e do `.gitignore`

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-15 |
| **Proponente** | orquestrador (origem: Auditoria L5/L6, Guia-Composer §5–6) |

## Contexto

O workflow `.github/workflows/php.yml` **falhava desde o primeiro run**: executa `composer validate`/`install` na raiz do repositório, mas o manifesto vive em `dev/system/storage/`. Além disso, o `.gitignore` ignora `/system/storage/vendor` — caminho que não corresponde a `dev/system/storage/vendor`, o que deixou todo o vendor do Twig commitado (~11 mil arquivos). Ambos estavam registrados como "pendências conhecidas", indecisos.

## Decisão

1. **Workflow corrigido agora:** `defaults.run.working-directory: dev/system/storage`, `actions/cache@v4`, setup do PHP 8.2 com extensões, caminho de cache correto — mais dois jobs novos: **checagem de sintaxe `php -l`** em todos os `.php` de `dev/` **e link check** dos docs (excluindo domínios fictícios do projeto, ex.: `reamurcms.com`);
2. **`.gitignore` corrigido agora:** `**/system/storage/vendor/` + caches/logs/temp do storage;
3. **Limpeza do histórico do vendor fica para a Fase 1** (`git rm -r --cached dev/system/storage/vendor` + commit), sem reescrever histórico passado.

## Alternativas consideradas

1. **Mover o composer.json para a raiz** — rejeitada: quebra o desenho intencional de concentrar artefatos no `storage/` (ver Guia-Composer §1);
2. **Rewrite de histórico (filter-repo)** — rejeitada por ora: repo público com clone ativo; ganho pequeno frente ao risco.

## Consequências

- ✅ CI volta a ser sinal confiável (verde tem significado);
- ✅ Erros de sintaxe PHP e links quebrados deixam de passar silenciosamente;
- ⚠️ Histórico continua pesado até a limpeza da Fase 1 (aceito).

## Adendo F1-5 (2026-10-16 — Fase 1 · Tarefa 5)

- **Limpeza do vendor (decisão 3): ENCERRADA.** Verificação na árvore da `main`: `dev/system/storage/vendor` não consta versionado e `**/system/storage/vendor/` permanece no `.gitignore` — nenhum `git rm --cached` adicional necessário. Histórico antigo segue pesado, conforme aceito na decisão;
- **PHPStan destravado no CI** com `continue-on-error: true`: os paths do `phpstan.neon` (`engine/`, `library/`, `helper/`) dependem do núcleo (Fase 1 · Tarefa 6); o flag sai quando o primeiro run com o núcleo ficar verde. `phpstan.neon` não foi alterado (paths já são o alvo correto).
- **Adendo F1-6c:** condição atingida na Tarefa 6c — núcleo completo no repo e `composer analyse` rodado localmente com **0 erros** (24 arquivos, level 2). `continue-on-error` **removido**: static-analysis passa a bloquear merges. As constantes do Contrato §1.1 usadas pelo núcleo em tempo de execução são simuladas para o analisador via `phpstan-bootstrap.php` (`bootstrapFiles`) — padrão indicado no user guide do PHPStan.

## Documentos atualizados

- `.github/workflows/php.yml` (corrigido)
- `.gitignore` (corrigido)
- `agents-ia/regras/00-contexto-global.md` (pendências L5/L6 encerradas)
- `docs/Guia-Composer.md` §5–6 (referência ao ADR)
