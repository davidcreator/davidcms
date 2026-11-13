# ADR 0092 — Build de produção sem pacotes de desenvolvimento; aviso de segurança em dependência

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S10 + S11, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Achados S10 e S11 da análise do repositório, reproduzidos em `edcddae` antes de qualquer mudança: `tools/build_prod.sh` executado como está e `composer audit` sobre o `composer.lock`. Complementa as ADRs 0039 (build de produção só com o runtime), 0081 (dependências em `dev/system/storage/`) e 0082 (contrato do CI). |

## O que foi encontrado

### S10 — a build levava ferramentas de desenvolvimento

`tools/build_prod.sh` copiava `dev/system/storage/vendor/` inteiro para `prod/`. O veredito da build dizia "OK" e o roteiro de produção afirmava que a build subia sem PHPStan.

| Medida em `edcddae` | Resultado |
| --- | --- |
| `prod/` | 132 MB, 7.324 arquivos |
| `prod/system/storage/vendor/` | 111 MB, 6.056 arquivos, 47 pacotes |
| Pacotes de `require-dev` dentro dele | 27: `phpunit/phpunit`, `phpstan/phpstan` (23 MB), `nikic/php-parser`, `myclabs/deep-copy`, `sebastian/*`, `phar-io/*`, `theseer/tokenizer` e outros |
| `vendor/bin/` | `phpunit`, `phpstan`, `phpstan.phar`, `php-parse`, além de `jp.php`, `latte-lint`, `pscss` |
| Arquivos de pacote de desenvolvimento incluídos **a cada requisição** pelo autoload | 3: `phpstan/phpstan/bootstrap.php`, `phpunit/phpunit/src/Framework/Assert/Functions.php`, `myclabs/deep-copy/src/DeepCopy/deep_copy.php` |

A pasta `system/` tem `Require all denied` no `.htaccess`, então sob Apache esses arquivos não são servidos pela web. O problema é o servidor carregar, e executar em parte, código que o site não usa. O `Guia-Composer.md` (boas práticas, item 5) já pedia `--no-dev` no deploy.

### S11 — aviso de segurança em uma dependência

`composer audit` terminava com erro e um aviso: `symfony/filesystem` v7.4.21 (PKSA-g499-cm4q-v35y / GHSA-6prc-h956-hxjq, publicado em 2026-11-04, sem CVE e sem severidade atribuída), corrigido na v7.4.23. O pacote entra só por exigência do `aws/aws-sdk-php`. Nenhum arquivo de `dev/` fora do `vendor/` referencia `Aws\` nem `Symfony\`, então o trecho afetado não é alcançado pelo CMS hoje. O CI não rodava `composer audit`.

### Pacotes declarados e não usados

Dos 9 pacotes de `require`, o código referencia só `twig/twig` (`dev/system/library/template/twig.php` e `theme_sandbox.php`). `aws/aws-sdk-php`, `guzzlehttp/guzzle`, `guzzlehttp/promises`, `guzzlehttp/psr7`, `guzzlehttp/uri-template`, `mustache/mustache`, `latte/latte` e `scssphp/scssphp` não aparecem em nenhum arquivo de `dev/` fora do `vendor/`. Medido em cópia de teste: sem os 8, o vendor de produção cai para 2 MB (4 pacotes), os 566 testes e o PHPStan passam e o `composer audit` fica limpo.

## Decisão

1. **A build leva só os pacotes de `require`.** Depois de copiar `dev/`, `tools/build_prod.sh` copia `composer.json` e `composer.lock` para `prod/system/storage/`, roda `composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader` ali e apaga os dois manifestos. O Composer remove da cópia os pacotes de `require-dev`, acerta qualquer versão diferente do lock e regrava o autoload. `dev/` não é tocado.
2. **Composer passa a ser pré-requisito da build**, conferido antes do `rsync`. Sem ele, a build termina com erro e não toca em `prod/`. A variável `COMPOSER_BIN` aponta outro executável (por exemplo `COMPOSER_BIN="php /caminho/composer.phar"`).
3. **Falha do Composer reprova a build**, mesmo que o vendor tenha ficado certo. Os manifestos saem de `prod/` em qualquer caminho de saída.
4. **Veredito da build mais estrito.** Reprova quando existe `vendor/phpunit`, `vendor/phpstan`, `vendor/bin/{phpunit,phpstan,phpstan.phar,php-parse}` ou `composer.lock` em `prod/`, e quando o registro do Composer (`vendor/composer/installed.php`) não diz `'dev' => false` ou lista algum pacote com `'dev_requirement' => true`.
5. **`symfony/filesystem` v7.4.21 → v7.4.23** no `composer.lock`. É a única entrada de pacote alterada. `composer.json` não muda.
6. **Os 8 pacotes sem uso ficam** (decisão do mantenedor). Estão descritos no `Guia-Composer.md` como integrações previstas. A remoção fica registrada aqui como opção medida, para quando o mantenedor quiser.
7. **CI, job `composer`:** dois passos novos. `tools/verify-build-prod.sh` roda a build de verdade e confere `prod/` contra o lock. `composer audit --locked` roda como **aviso** (decisão do mantenedor): um aviso de segurança aparece como anotação no run e não deixa o CI vermelho.
8. **`tools/verify-ci-workflow.sh`** ganha a seção 9 (4 verificações, 35 no total): o workflow executa `verify-build-prod.sh`, o arquivo existe, há um passo de `composer audit` e ele não reprova.

## Efeito

| Medida | Antes | Depois |
| --- | --- | --- |
| `prod/` | 132 MB, 7.324 arquivos | 97 MB, 5.639 arquivos |
| `prod/system/storage/vendor/` | 111 MB, 6.056 arquivos, 47 pacotes | 75 MB, 4.371 arquivos, 20 pacotes |
| `vendor/bin/` | 7 executáveis | `jp.php`, `latte-lint`, `pscss` (dos pacotes de `require`) |
| Arquivos incluídos pelo autoload a cada requisição | 22, sendo 3 de pacotes de desenvolvimento | 19, nenhum de pacote de desenvolvimento |
| `composer audit` | 1 aviso, termina com erro | nenhum aviso |

- **Comportamento do site:** igual. Na instalação de teste, a página inicial, a página de erro 404, o login do admin e o painel responderam o mesmo com o vendor antigo e com o vendor da build nova; a única diferença no HTML foi o token CSRF.
- **Quem gera a build precisa do Composer** no mesmo terminal em que roda o script.
- **Depois de aplicar esta entrega, rode `composer install --working-dir=dev/system/storage`** para trazer o `symfony/filesystem` novo ao vendor local. Se isso não for feito, a build baixa a versão do lock para dentro de `prod/` (precisa de rede) e o vendor de `dev/` fica como estava.
- O autoload de produção passa a ser o otimizado (mapa de classes; o PSR-4 continua como reserva).
- Sem tabela, coluna, migration, rota ou configuração nova. Nenhum arquivo PHP do CMS foi alterado.

## Verificação

- **`tools/verify-build-prod.sh`: 46 verificações.** Sem Composer, a build para antes de copiar. Com um Composer que não remove nada, o veredito reprova e cita cada item. Com um Composer que limpa o vendor e devolve erro, a build reprova. Na build real: nenhum dos 27 pacotes de `require-dev` em `prod/`, os 20 de `require` presentes nas versões do lock, o autoload de produção carrega e renderiza um template Twig, `PHPUnit\Framework\TestCase` e afins não existem, e o vendor e o lock de `dev/` ficam intactos.
- **Contra o `build_prod.sh` anterior** o mesmo script reprova em 27 das 46 verificações.
- **Mutação:** 12 alterações no `build_prod.sh`, cada uma desfazendo uma parte da correção. 11 são reprovadas. A que passa é tirar `composer.lock` da lista do veredito: os dois manifestos são apagados juntos e a presença de `composer.json` já é acusada.
- **Vendor local atrasado em relação ao lock** (situação de quem aplica esta entrega): com rede, `prod/` recebe a v7.4.23 e `dev/` fica na v7.4.21; sem rede e sem cache, a build termina com erro e mensagem.
- **Sem rede**, com o vendor de `dev/` em dia: a build completa (`COMPOSER_DISABLE_NETWORK=1`).
- **`verify-ci-workflow.sh`:** 35 verificações; o workflow anterior reprova em 3 e um `composer audit` bloqueante reprova em 1.
- PHPUnit em PHP 8.2 e 8.3: 566 testes. PHPStan sem erros. `composer validate --strict` válido.

## Limites

- **Não exercitado no Windows** (Git Bash, WSL) nem com o Composer instalado pelo XAMPP. Se o script disser "Composer não encontrado", use `COMPOSER_BIN`.
- **`verify-build-prod.sh` não roda no job `repo-guards`:** precisa do vendor instalado, por isso roda no job `composer`.
- **`composer audit` como aviso** depende de alguém olhar as anotações do run. Com falha de rede no passo, o aviso também aparece.
- **Os pacotes de `require` sem uso** continuam indo para o servidor (75 MB, dos quais o AWS SDK ocupa 71 MB) e continuam sujeitos a avisos de segurança futuros.
- **Versões novas disponíveis e não aplicadas:** `aws/aws-sdk-php` 3.403.5 e `latte/latte` 3.1.7 dentro das restrições atuais; há majors novas de Guzzle, scssphp, PHPStan e PHPUnit. Fora do escopo.
- **`system/` fechado pela web** depende do `.htaccess` (Apache e compatíveis). Em nginx, a regra tem de estar na configuração do servidor. Não foi conferido no servidor de produção.
- `agents-ia/regras/00-contexto-global.md` lista `phpdocumentor/phpdocumentor` como dependência; ele não está no `composer.json` (o guia manda usar o PHAR). Não corrigido aqui.

## Pendência registrada

- **Pasta `install` do site antigo em `davidalmeida.xyz`** (ADR 0091): o mantenedor decidiu deixá-la até a troca do site antigo pelo novo. Em 2026-11-13 ela ainda respondia 200.
