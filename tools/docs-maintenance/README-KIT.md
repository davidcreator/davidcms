# Kit de organização documental do ReamurCMS

Este kit foi feito para rodar **na raiz do clone Git `davidcms`**. Ele não copia o projeto para fora da árvore e não toca em banco, `dev/`, `CHECKSUMS`, CI, decisões, ADRs ou deploy.

## Regra de segurança

- O padrão é `--plan`: somente leitura.
- Não há aplicação automática ao extrair o ZIP.
- `--apply` exige ID explícito, valida hash/proveniência/referências, cria backup em `.git/docs-cleanup-backups/`, pede confirmação digitada e executa `git rm` somente nos caminhos da allowlist.
- Não faz commit nem push. Não há `--purge`.
- `--restore latest` restaura o último backup e deixa a restauração staged.
- Se uma verificação divergir, o script aborta sem tentar adivinhar.

## Uso

Na raiz do projeto, primeiro apenas revisar:

```bash
bash tools/docs-maintenance/organizar-docs.sh --plan
```

O script aceita estes IDs, cada um por decisão separada:

```bash
# Duplicata exata. A cópia F9-01 permanece porque o E2E a consome.
bash tools/docs-maintenance/organizar-docs.sh --apply --id f9-02-duplicado

# Arquivo integral histórico. Só fica elegível após corrigir/revisar todas as referências.
bash tools/docs-maintenance/organizar-docs.sh --apply --id frentes-integral
```

Para desfazer a última aplicação:

```bash
bash tools/docs-maintenance/organizar-docs.sh --restore latest
```

Depois de qualquer aplicação, revise `git status`, `git diff --cached` e os links. Este kit **não commita**.

## Allowlist inicial

| ID | Caminho | Ação | Condições para permitir |
|---|---|---|---|
| `f9-02-duplicado` | `docs/F9-02-Contrato-Campos-Personalizados.json` | Remoção reversível e staged | Hash conhecido, idêntico ao F9-01, fora de CHECKSUMS, sem referência de código/teste/docs; a menção no inventário histórico é permitida |
| `frentes-integral` | `docs/FRENTES-INTEGRAL.md` | Remoção reversível e staged | Hash conhecido; Git confirma que os 64 Markdown originais são recuperáveis; referências atuais precisam ser revisadas e retiradas antes. O script aborta enquanto houver referência |

**Fora da allowlist:** `docs/README.md`, sua cópia `RESTAURAR-docs-README.txt`, documentos de governança/checksum, `F9-27-*`, contratos/status JSON, F8/F11 protegidos, guias, scripts, patches e assets de tema. Não serão removidos por esse script.

## Consolidação editorial (manual, sem perda de conteúdo)

O `consolidacao-pendente.tsv` lista famílias que podem ser resumidas em um ponto canônico. O script não concatena automaticamente esses documentos: eles contêm estados, decisões e evidências diferentes, e uma concatenação mecânica pode apagar contexto ou aparentar mudar `PENDING_DECISION`, `BLOCKED` ou `NOT_RUN`.

O fluxo recomendado é: preparar o novo texto → comparação linha a linha → revisar referências → aprovação do responsável → só então retirar os documentos substituídos. Os textos originais continuam no Git e os artefatos em `CHECKSUMS` não são reemitidos.

## Requisitos

Git Bash/WSL ou Bash com `git`, `sha256sum`, `tar`, `grep`, `awk`, `sort` e `ls`. Execute a partir de um checkout Git completo; a remoção de `FRENTES-INTEGRAL` valida a disponibilidade do commit de histórico `0ae835b5`.
