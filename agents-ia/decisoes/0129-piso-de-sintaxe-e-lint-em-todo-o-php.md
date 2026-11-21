# 0129 — O piso de sintaxe é o PHP do CI, e o lint varre todo o PHP do repositório

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-11-20 |
| **Frente** | KZ-64 · CI (F11-04 / F11-07) |
| **Decide** | onde mora a versão de PHP que o código tem de respeitar, e o que o job `php-lint` é obrigado a abrir |
| **Não decide** | subir ou não o piso para 8.3/8.4 — isso depende da versão de PHP do servidor de produção, que é do responsável (§6) |

## 1. O vermelho não era o pacote novo

O `ReamurCMS-KZ64-contratos-ci` foi aplicado e **não está no GitHub**: `origin/main` continuava em `39c8007` e nenhum dos três branches tinha o arquivo da ADR 0128. O CI estava vermelho havia **cinco runs** antes disso — 528 (`c040bb0`), 529, 530, 531, 532 — todos com o mesmo `Process completed with exit code 255` no job `composer`, passo `Run unit tests (PHPUnit)`. O run verde mais recente é o 527 (`56a610a`).

O log bruto do Actions exige direito de admin no repositório, então a busca foi pelo caminho inverso: clone limpo em `39c8007`, `composer install`, `composer test`.

```
An error occurred inside PHPUnit.
Message:  syntax error, unexpected token "->"
Location: /tmp/dcms/tests/Unit/Payment/GatewayReconciliationServiceTest.php:189
returned with error code 255
```

Linha 189: `$report = new GatewayReconciliationService( … )->reconcileAccount( … );`. Escrever `new X(...)->método()` **sem parênteses em volta do `new`** é sintaxe do PHP 8.4. O runner do CI fixa **8.2**. Em 8.2 aquilo não é teste que falha: é arquivo que não parseia, e o PHPUnit morre no carregador de suíte, **antes do primeiro teste**. Suite inteira = zero executada, e o log do job não diz arquivo nem linha.

**O arquivo é meu.** Está no commit `c040bb0`, que é o `git am` do pacote que entreguei (ADR 0124). Varredura dos **561** `.php` do repositório sob 8.2: exatamente **um** reprova — este. A correção é o parêntese: `(new GatewayReconciliationService( … ))->reconcileAccount( … )`, que vale em 8.0, 8.2 e 8.4.

## 2. Por que quatro camadas deixaram passar

- **`php-lint`** — o passo fazia `find dev -type f -name '*.php'`. `tests/` e `tools/` nunca foram lintados. O arquivo estava exatamente na pasta que o lint não abria.
- **`static-analysis`** — o `phpstan.neon` analisa `dev/system/{engine,library,helper}`. `tests/` fora, de novo.
- **`composer validate --strict`** — `composer.json` declara `"php": ">=8.0.0"`, então nada recusa o 8.2, e nada exige sintaxe de 8.2 dele.
- **minha verificação** — rodei a suíte inteira em **PHP 8.4.28**, o único interpretador do ambiente: 1978 testes verdes, zero pulados, e **não prova nada** contra um CI em 8.2. Este é o defeito central da minha entrega anterior, e ele não estava em lugar nenhum dos guardas que a ADR 0128 acabou de pôr no CI.

A ADR 0128 tratou de "guarda que existe e não roda no CI". Aqui é o vizinho: **guarda que roda no CI, mas num chão diferente do chão que quebra.** Os dois são o mesmo formato de mentira — cobertura que parece maior do que é.

## 3. A decisão

**a) O piso passa a ser uma linha só, e é a linha do CI.** `.github/workflows/php.yml` ganha `env: CI_PHP_VERSION: '8.2'` no topo; os três jobs que ligam PHP (`composer`, `php-lint`, `static-analysis`) passam a usar `${{ env.CI_PHP_VERSION }}`. Antes cada um repetia `'8.2'` à mão: subir o piso significava lembrar de três lugares, e esquecer um produz exatamente o quadro desta ADR — teste em 8.4, lint em 8.2.

**b) O `php-lint` varre `dev/`, `tests/` e `tools/`**, com contagem **por diretório**: se um `find` não devolve arquivo, o passo falva (`lint vazio nao e aprovação` já existia para o caso geral; agora vale diretório por diretório, senão renomear uma pasta tira a pasta do lint em silêncio).

**c) O auditor do workflow passa a conferir o chão, não só o YAML.** Seção 10 do `tools/verify-ci-workflow.sh`: nenhum `php-version` literal sobrando, `CI_PHP_VERSION` declarado uma vez, todo `setup-php` com versão declarada, e — a parte que impede a repetição deste bug — **o conjunto de diretórios que o lint varre tem de ser igual ao conjunto de diretórios que têm `.php` na árvore**, calculado na hora com `find`. Criar `scripts/` com PHP novo sem incluir no lint quebra o `repo-guards` no mesmo commit.

## 4. Consertado de passagem, porque é o mesmo defeito

A regra nova do item (c) exigiu rodar o `verify-docs-index.php` com o índice na mão, e ele **nem olhava o formato da linha**. Quatro linhas do índice de ADRs estavam fora da tabela de 4 colunas: `0106` e `0124` com canos dentro do resumo (`pt-br`, `en-us`, `es-eu` e `pending|requires_action|authorized`) quebrando a célula, e `0127`/`0128` **sem Status e sem Data** — as duas últimas sou eu, dos pacotes anteriores. Uma linha quebrada na tabela é a ADR existindo só para quem já sabe o número do arquivo: o mesmo defeito que a ADR 0126 registrou para "ADR fora do índice", um andar abaixo.

Acrescentado ao `tools/verify-docs-index.php`: `linhasMalformadas()` — 4 células e as duas últimas não vazias, contando canos **não escapados** (`\|` é texto; contar todo `|` reprovaria linha certa e empurraria para encurtar o resumo, que é o contrário do que se quer). As quatro linhas foram reparadas, não encurtadas.

O auditor ganhou argumento opcional de índice candidato (`php tools/verify-docs-index.php --strict agents-ia/decisoes/README.md`), no mesmo estilo do auditor do workflow.

## 5. O que não foi feito, de propósito

- **Não subi o piso para 8.4.** Consertar o código para caber no 8.2 é uma linha; subir o piso é mudar o requisito do servidor, e eu não sei a versão de PHP da produção. A escolha errada aqui quebra o deploy, não o CI.
- **Não alterei `composer.json`** (`"php": ">=8.0.0"`). A declaração é mais antiga que o CI e não é verificada por nada: mexer nela é promessa sobre ambiente de produção, não correção de código.
- **Não pus o lint de sintaxe dentro do `repo-health.sh`.** O contrato do orquestrador é não executar PHP, e ele continua valendo; quem executa PHP é o job `php-lint`.
- **Nada toca banco, captura ou estorno.** Este pacote não muda uma linha de `dev/`.

## 6. Pendência que continua aberta

`tools/decision-matrix.json`: `revalidated_by` das reemissões da ADR 0128 segue como *pendente de confirmação do responsável*. Não assino conferência que não fiz, e este pacote não muda isso. Continua em aberto também: frase falsa do `release-readiness-report.sh` sobre `PENDING_DECISION`, P3 (executar o runbook), P4 (credenciais/sandbox) e D01 (R2/R3).

## 7. Verificação

PHP **8.2.36** (o piso do CI), clone limpo, `composer install` no `dev/system/storage`:

| Medição | Antes | Depois |
| --- | --- | --- |
| `composer test` (job `composer`, PHP 8.2) | **exit 255**, 0 testes executados | **OK: 1980 testes / 46.479 asserções** em 7,4 s, zero pulados, exit 0 |
| `php -l` nos 561 `.php` do repo em 8.2 | 1 reprova (`GatewayReconciliationServiceTest.php:189`) | **561 / 561 ok** |
| `tools/verify-ci-workflow.sh` | 44 PASS | **52 PASS / 0 FAIL** |
| `tools/verify-docs-index.php --strict` | OK, mas não olhava formato de linha | OK com **129** linhas × 4 colunas conferidas |
| `tools/repo-health.sh` | 8 PASS / 0 FAIL / 0 SKIP | **8 PASS / 0 FAIL / 0 SKIP**, checksums 85/85 |

Teste de mutação, cada um revertido depois: fixar `php-version: '8.4'` em um dos jobs → **2 FAIL**; tirar `tests` da varredura → **2 FAIL**, com o nome dos dois conjuntos na saída; reintroduzir a linha 189 sem parênteses e rodar o passo `php-lint` como ele é escrito no CI → **exit ≠ 0 apontando o arquivo**. Suíte sob 8.2 com o bug de volta: **255**, igual ao GitHub — o reproduzido é o mesmo que o run 532 mostrou, não um vizinho.

**Números medidos no meu ambiente, não no runner.** O `php-lint` com o bug de volta foi executado com o corpo exato do passo (os dois `find`, a contagem por diretório, `xargs -0 -n1 -P4 php -l`), em 8.2 — reprova apontando o arquivo. O que continua sendo inferência é o comportamento do runner: `ubuntu-24.04` com `setup-php` pode trazer extensões que o meu não tem. Fica dito em vez de ficarem 5 runs para aprender.

O pacote anterior (ADR 0128) também não está no GitHub: `main` em `39c8007`, e nenhum branch tem `agents-ia/decisoes/0128-*.md`. Este patch é **empilhado sobre aquele commit** — aplicar os dois na ordem, ou o `repo-guards` vai reprovar os manifestos e a paridade de guardas que a 0128 criou.
