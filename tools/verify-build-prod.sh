#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# verify-build-prod.sh — ADR 0092 (S10 + S11)
# Prova que tools/build_prod.sh entrega em prod/ SOMENTE os pacotes de
# `require` do composer.lock, e que o veredito da própria build reprova
# quando sobra pacote de desenvolvimento.
#
#   bash tools/verify-build-prod.sh
#
# Precisa de: bash, rsync, php, Composer 2 e do vendor de dev/ instalado COM
# os pacotes de desenvolvimento (`composer install` em dev/system/storage).
# Pré-requisito ausente = FALHA (verificação vazia não é aprovação).
#
# Efeitos: recria prod/ (artefato derivado, fora do git). Não altera dev/,
# não acessa banco. Só usa a rede se o vendor de dev/ divergir do lock.
# Variáveis: PHP_BIN (padrão: php), COMPOSER_BIN (padrão: composer).
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1
PHP_BIN="${PHP_BIN:-php}"
DEV_STO="dev/system/storage"
PRD_STO="prod/system/storage"
OUT="$(mktemp)"; TMPD="$(mktemp -d)"; trap 'rm -rf "$OUT" "$TMPD"' EXIT

PASS=0; FAIL=0
ck(){ if [ "$1" -eq 0 ]; then printf '  PASS  %s\n' "$2"; PASS=$((PASS+1)); else printf '  FAIL  %s\n' "$2"; FAIL=$((FAIL+1)); fi; }
resumo(){ echo; echo "== resumo: $PASS PASS / $FAIL FAIL =="; }

echo "== ADR 0092 — build de produção sem pacotes de desenvolvimento =="

echo "-- 0. Pré-requisitos --"
command -v rsync >/dev/null 2>&1; ck $? "rsync disponível"
command -v "$PHP_BIN" >/dev/null 2>&1; ck $? "php disponível ($PHP_BIN)"
# shellcheck disable=SC2086
set -- ${COMPOSER_BIN:-composer}
command -v "${1:-composer}" >/dev/null 2>&1; ck $? "Composer disponível (${COMPOSER_BIN:-composer})"
[ -f "$DEV_STO/vendor/autoload.php" ] && [ -d "$DEV_STO/vendor/phpunit/phpunit" ] && [ -d "$DEV_STO/vendor/phpstan/phpstan" ]
ck $? "vendor de dev/ instalado com PHPUnit e PHPStan (senão não há o que provar)"
if [ "$FAIL" -ne 0 ]; then resumo; exit 1; fi

sha(){ sha256sum "$1" | cut -d' ' -f1; }
DEV_ANTES="$(sha "$DEV_STO/vendor/composer/installed.json")"
LOCK_ANTES="$(sha "$DEV_STO/composer.lock")"

echo "-- 1. Sem Composer a build para ANTES de copiar --"
mkdir -p prod && : > prod/.sentinela-verify-build
COMPOSER_BIN=/nao/existe/composer bash tools/build_prod.sh >"$OUT" 2>&1; RC=$?
[ "$RC" -ne 0 ]; ck $? "build sem Composer termina com erro (exit $RC)"
grep -q 'Composer não encontrado' "$OUT"; ck $? "mensagem diz o que falta e como apontar o executável"
[ -f prod/.sentinela-verify-build ]; ck $? "prod/ não foi tocada (o rsync não chegou a rodar)"

echo "-- 2. Controle negativo: se o Composer não remover nada, o veredito reprova --"
# `true` no lugar do Composer = o passo de limpeza não faz nada e o vendor
# copiado de dev/ fica inteiro. O veredito TEM de acusar.
COMPOSER_BIN=true bash tools/build_prod.sh >"$OUT" 2>&1; RC=$?
[ "$RC" -ne 0 ]; ck $? "build com vendor inteiro termina com erro (exit $RC)"
grep -q 'NÃO DEVERIA EXISTIR: .*/vendor/phpunit$' "$OUT"; ck $? "veredito acusa vendor/phpunit"
grep -q 'NÃO DEVERIA EXISTIR: .*/vendor/phpstan$' "$OUT"; ck $? "veredito acusa vendor/phpstan"
for b in phpunit phpstan phpstan.phar php-parse; do
  grep -q "NÃO DEVERIA EXISTIR: .*/vendor/bin/$b\$" "$OUT"; ck $? "veredito acusa vendor/bin/$b"
done
grep -q 'vendor instalado COM pacotes de desenvolvimento' "$OUT"; ck $? "veredito lê o registro do Composer ('dev' => true)"
grep -q 'pacote de desenvolvimento registrado em' "$OUT"; ck $? "veredito lê o registro por pacote ('dev_requirement' => true)"
grep -q 'BUILD COM PROBLEMAS' "$OUT" && ! grep -q 'OK — build pronta' "$OUT"; ck $? "termina em BUILD COM PROBLEMAS, nunca em OK"
[ ! -e "$PRD_STO/composer.json" ] && [ ! -e "$PRD_STO/composer.lock" ]; ck $? "manifestos não ficam em prod/ nem quando a build falha"

echo "-- 2b. Composer que termina com erro reprova a build, mesmo tendo limpado o vendor --"
# Invólucro: roda o Composer de verdade (o vendor fica correto) e devolve
# erro. Também exercita COMPOSER_BIN com mais de uma palavra.
REAL_COMPOSER="${COMPOSER_BIN:-composer}"
printf '#!/usr/bin/env bash\n%s "$@"\nexit 3\n' "$REAL_COMPOSER" > "$TMPD/composer-com-erro.sh"
COMPOSER_BIN="bash $TMPD/composer-com-erro.sh" bash tools/build_prod.sh >"$OUT" 2>&1; RC=$?
[ "$RC" -ne 0 ]; ck $? "build termina com erro quando o Composer falha (exit $RC)"
grep -q 'Composer falhou ao montar o vendor de produção' "$OUT" && ! grep -q 'OK — build pronta' "$OUT"
ck $? "mensagem diz que prod/ não está pronta; nunca OK"
[ ! -d "$PRD_STO/vendor/phpunit" ]; ck $? "COMPOSER_BIN com mais de uma palavra foi de fato executado (vendor limpo)"
[ ! -e "$PRD_STO/composer.json" ] && [ ! -e "$PRD_STO/composer.lock" ]; ck $? "manifestos removidos de prod/ também nesse caminho"

echo "-- 3. Build real --"
: > prod/.sentinela-verify-build
bash tools/build_prod.sh >"$OUT" 2>&1; RC=$?
[ "$RC" -eq 0 ]; ck $? "tools/build_prod.sh termina com sucesso (exit $RC)"
grep -q 'OK — build pronta' "$OUT"; ck $? "veredito da build: OK — build pronta"
if [ "$RC" -ne 0 ]; then sed 's/^/     | /' "$OUT" | tail -20; fi
[ ! -e prod/.sentinela-verify-build ]; ck $? "prod/ foi regerada (sentinela removida pelo rsync --delete)"

echo "-- 4. Conteúdo do vendor de produção x composer.lock --"
LOCK="$DEV_STO/composer.lock" VENDOR="$PRD_STO/vendor" "$PHP_BIN" -d display_errors=stderr <<'PHP' >"$OUT"
<?php
// Uma linha por verificação: "ok<TAB>rótulo" ou "ko<TAB>rótulo".
$r = static function (bool $ok, string $label): void { echo ($ok ? 'ok' : 'ko'), "\t", $label, "\n"; };
$lock   = json_decode((string) file_get_contents(getenv('LOCK')), true);
$vendor = rtrim((string) getenv('VENDOR'), '/');
$run    = array_column($lock['packages'] ?? [], 'version', 'name');
$dev    = array_column($lock['packages-dev'] ?? [], 'version', 'name');
$r(count($run) > 0 && count($dev) > 0, 'lock lido: ' . count($run) . ' pacotes de produção e ' . count($dev) . ' de desenvolvimento');

$inst = json_decode((string) @file_get_contents($vendor . '/composer/installed.json'), true);
$have = array_column($inst['packages'] ?? [], 'version', 'name');

$sobra = array_values(array_filter(array_keys($dev), static fn (string $n): bool => is_dir("$vendor/$n") || isset($have[$n])));
$r($sobra === [], 'nenhum pacote de desenvolvimento em prod/ (pasta ou registro)' . ($sobra ? ' — sobrou: ' . implode(', ', $sobra) : ''));

$falta = array_values(array_filter(array_keys($run), static fn (string $n): bool => !is_dir("$vendor/$n") || !isset($have[$n])));
$r($falta === [], 'todos os pacotes de produção presentes' . ($falta ? ' — faltou: ' . implode(', ', $falta) : ''));

$dif = [];
foreach ($run as $n => $v) { if (isset($have[$n]) && $have[$n] !== $v) { $dif[] = "$n {$have[$n]} != $v"; } }
$r($dif === [], 'versões em prod/ iguais às do composer.lock' . ($dif ? ' — ' . implode('; ', $dif) : ''));

$r(!array_diff(array_keys($have), array_keys($run)), 'nada em prod/ além do que o lock lista em "packages"');
$r(($inst['dev'] ?? null) === false, 'installed.json registra "dev": false');
$php = @include $vendor . '/composer/installed.php';
$r(is_array($php) && ($php['root']['dev'] ?? null) === false, "installed.php registra 'dev' => false");

$bins = array_map('basename', glob($vendor . '/bin/*') ?: []);
$r(!array_intersect($bins, ['phpunit', 'phpstan', 'phpstan.phar', 'php-parse']), 'vendor/bin sem phpunit, phpstan, phpstan.phar e php-parse');
PHP
RC=$?
[ "$RC" -eq 0 ] && [ -s "$OUT" ]; ck $? "conferência do lock executou (exit $RC)"
while IFS=$'\t' read -r st label; do
  [ "$st" = ok ]; ck $? "$label"
done <"$OUT"

echo "-- 5. O autoload de produção funciona e não enxerga ferramentas de dev --"
AUTOLOAD="$PRD_STO/vendor/autoload.php" "$PHP_BIN" -d display_errors=stderr <<'PHP' >"$OUT"
<?php
$r = static function (bool $ok, string $label): void { echo ($ok ? 'ok' : 'ko'), "\t", $label, "\n"; };
require getenv('AUTOLOAD');
$r(class_exists(\Twig\Environment::class), 'Twig\Environment carrega (única dependência que o runtime usa hoje)');
$twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => 'ola {{ nome|upper }}']));
$r($twig->render('t', ['nome' => 'prod']) === 'ola PROD', 'Twig renderiza um template com o vendor de produção');
foreach (['PHPUnit\Framework\TestCase', 'PhpParser\ParserFactory', 'SebastianBergmann\Diff\Differ', 'DeepCopy\DeepCopy'] as $c) {
    $r(!class_exists($c), "$c não existe no autoload de produção");
}
$r(!function_exists('DeepCopy\deep_copy'), 'nenhum arquivo de função de pacote dev é incluído a cada requisição');
$dev = array_filter(get_included_files(), static fn (string $f): bool => (bool) preg_match('#/vendor/(phpunit|phpstan|myclabs|sebastian|nikic|phar-io|theseer)/#', $f));
$r($dev === [], 'nenhum arquivo de pacote dev foi incluído' . ($dev ? ' — ' . implode(', ', $dev) : ''));
PHP
RC=$?
[ "$RC" -eq 0 ] && [ -s "$OUT" ]; ck $? "processo PHP com o autoload de produção terminou sem erro (exit $RC)"
while IFS=$'\t' read -r st label; do
  [ "$st" = ok ]; ck $? "$label"
done <"$OUT"

echo "-- 6. Sobras e efeitos colaterais --"
[ ! -e "$PRD_STO/composer.json" ] && [ ! -e "$PRD_STO/composer.lock" ]; ck $? "composer.json e composer.lock não ficam em prod/"
[ "$(sha "$DEV_STO/vendor/composer/installed.json")" = "$DEV_ANTES" ] && [ -d "$DEV_STO/vendor/phpunit/phpunit" ]
ck $? "vendor de dev/ intacto (PHPUnit e PHPStan continuam lá para os testes)"
[ "$(sha "$DEV_STO/composer.lock")" = "$LOCK_ANTES" ]; ck $? "composer.lock de dev/ não foi alterado pela build"

resumo
echo "   prod/: $(du -sm prod 2>/dev/null | cut -f1) MB, $(find prod -type f 2>/dev/null | wc -l) arquivos."
[ "$FAIL" -eq 0 ]
