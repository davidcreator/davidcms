# Comandos Bash

Execute todos a partir da raiz do checkout Git `davidcms`.

```bash
# 1. Conferência sem escrita (modo padrão)
bash tools/docs-maintenance/organizar-docs.sh --plan

# 2. Simular elegibilidade do JSON duplicado; confirmar apenas após revisar a saída
bash tools/docs-maintenance/organizar-docs.sh --apply --id f9-02-duplicado
# Digite exatamente: LIMPAR DOCS <HEAD exibido>

# 3. Revisar a remoção do arquivo integral; o comando aborta enquanto houver referências
bash tools/docs-maintenance/organizar-docs.sh --apply --id frentes-integral

# 4. Desfazer a última remoção aprovada (restaura cópia e stageia a restauração)
bash tools/docs-maintenance/organizar-docs.sh --restore latest

# 5. Depois de uma decisão/aplicação, revisar staged diff e estado do repo
git status --short
git diff --cached --stat
git diff --cached --check
```

O comando 2 ou 3 só altera o Git se todas as verificações passarem e a frase de confirmação for digitada. Nenhum comando faz commit, push, `DROP`, ou mexe em instalação/banco.
