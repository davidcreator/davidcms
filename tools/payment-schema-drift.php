#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * tools/payment-schema-drift.php — a divergência de prefixo entre o config.php
 * do servidor e os scripts SQL do repositório (KZ-64).
 *
 * O PROBLEMA QUE ESTE ARQUIVO FECHA
 *   Os SQL de `tools/upgrade_*.sql` escrevem `rms_` fixo no texto. Se o
 *   `DB_PREFIX` do servidor não for `rms_`, aplicar o script cria tabelas com o
 *   nome errado — ou falha com "#1109 Tabela desconhecida" quando alguém cola
 *   uma consulta também com `rms_`. O código PHP, esse, sempre acerta: ele
 *   monta o nome com `DB_PREFIX`. A divergência é só entre o SQL e o código.
 *
 * O QUE ELE FAZ
 *   Só LÊ. Conecta com as constantes do config.php, lista as tabelas do banco e
 *   diz, para cada tabela do núcleo novo de pagamentos: existe com o prefixo
 *   certo, existe com outro prefixo (resíduo) ou não existe.
 *
 * USO (no servidor, na raiz do site)
 *   php tools/payment-schema-drift.php --config=catalog/config.php
 *   php tools/payment-schema-drift.php --config=system/config.php --quiet
 *
 *   Compatibilidade: sem `--config`, as variáveis de ambiente REAMUR_CONFIG,
 *   REAMUR_DB_PREFIX, REAMUR_SKIP_DB e REAMUR_ASSUME_TABLES são aceitas.
 *
 *   `--tables=a,b,c` injeta a lista de tabelas (CI/teste, sem banco). É o que
 *   permite exercitar a matriz de veredictos sem uma instalação quebrada.
 *
 * SAÍDA: 0 = tudo certo ou só falta aplicar a migração
 *        1 = divergência de prefixo, ou configuração suspeita (a tabela de pedidos
 *            não existe com o prefixo do config.php)
 *        2 = erro de uso/conexão
 *
 * Não altera, não cria e não apaga nada. Não imprime senha nem DSN.
 */

const PAYMENT_CORE_TABLES = ['jow_gateway', 'payment_transaction', 'payment_gateway_event'];

/** Tabela obrigatória para o fluxo do pedido; faltando, a instalação/config está errada. */
const REQUIRED_TABLES = ['order'];
const EXPECTED_PREFIX = 'rms_';

/** @return array{path: string, prefix: string} */
function cli_config_path(array $argv): array {
    $config = '';

    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--config=')) {
            $config = substr($arg, strlen('--config='));
        }
    }

    if ($config === '') {
        $config = (string)(getenv('REAMUR_CONFIG') ?: '');
    }

    return ['path' => $config, 'prefix' => (string)(getenv('REAMUR_DB_PREFIX') ?: '')];
}

function load_config_constants(string $path): bool {
    if ($path === '') {
        return false;
    }

    if (!is_readable($path)) {
        fwrite(STDERR, "erro: config não encontrado ou sem leitura: {$path}\n");
        exit(2);
    }

    // O config.php do ReamurCMS só define constantes; incluí-lo aqui não
    // executa nada além disso.
    require $path;

    return true;
}

function db_connect(): ?mysqli {
    if (getenv('REAMUR_SKIP_DB')) {
        return null;
    }

    if (!extension_loaded('mysqli')) {
        fwrite(STDERR, "erro: extensão mysqli não carregada neste PHP\n");
        exit(2);
    }

    $host = defined('DB_HOSTNAME') ? (string)DB_HOSTNAME : 'localhost';
    $user = defined('DB_USERNAME') ? (string)DB_USERNAME : '';
    $pass = defined('DB_PASSWORD') ? (string)DB_PASSWORD : '';
    $name = defined('DB_DATABASE') ? (string)DB_DATABASE : '';
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;

    if ($name === '') {
        fwrite(STDERR, "erro: DB_DATABASE vazio — o config.php carregou?\n");
        exit(2);
    }

    try {
        $db = new mysqli($host, $user, $pass, $name, $port);
    } catch (\Throwable $e) {
        // A mensagem do mysqli pode conter o usuário, nunca a senha.
        fwrite(STDERR, 'erro: não conectou ao banco: ' . $e->getMessage() . "\n");
        exit(2);
    }

    if ($db->connect_errno) {
        fwrite(STDERR, 'erro: não conectou ao banco (código ' . (int)$db->connect_errno . ")\n");
        exit(2);
    }

    return $db;
}

/** @return list<string> */
function table_names(mysqli $db): array {
    $names = [];
    $result = $db->query('SHOW TABLES');

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_row()) {
            $names[] = (string)$row[0];
        }

        $result->free();
    }

    return $names;
}

function ends_with_table(string $table, string $bare): bool {
    return $table === $bare || str_ends_with($table, '_' . $bare);
}

function prefix_of(string $table, string $bare): ?string {
    if ($table === $bare) {
        return '';
    }

    return str_ends_with($table, '_' . $bare) ? substr($table, 0, -strlen($bare)) : null;
}

$quiet = in_array('--quiet', $argv, true);
$json = in_array('--json', $argv, true);
$options = cli_config_path($argv);
$loaded = load_config_constants($options['path']);

$prefix = defined('DB_PREFIX') ? (string)DB_PREFIX : $options['prefix'];

if (!$loaded && $prefix === '') {
    fwrite(STDERR, "erro: sem prefixo. Use --config=catalog/config.php (ou REAMUR_DB_PREFIX=rms_).\n");
    exit(2);
}

if (!$loaded) {
    fwrite(STDERR, "aviso: sem config.php — o prefixo vem de REAMUR_DB_PREFIX\n");
}

/**
 * Lista de tabelas injetada por `--tables=`/REAMUR_ASSUME_TABLES. Existe para
 * exercitar a matriz de veredictos em CI e em teste, sem banco: sem isso, o
 * caminho `config_mismatch` só seria alcançável numa instalação quebrada.
 *
 * @return list<string>
 */
function assumed_tables(array $argv): array {
    $raw = '';

    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--tables=')) {
            $raw = substr($arg, strlen('--tables='));
        }
    }

    if ($raw === '') {
        $raw = (string)(getenv('REAMUR_ASSUME_TABLES') ?: '');
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $t): bool => $t !== ''));
}

$db = db_connect();
$assumed = assumed_tables($argv);
$connected = $db !== null;

if ($connected) {
    $tables = table_names($db);
} else {
    $tables = $assumed;
}

$report = [
    'prefix' => $prefix,
    'expected_prefix' => EXPECTED_PREFIX,
    'tables_found' => [],
    'tables_missing' => [],
    'tables_wrong_prefix' => [],
    'required_missing' => [],
    'verdict' => 'ok',
];

$evaluateRequired = $connected || $assumed !== [];

foreach (array_merge(PAYMENT_CORE_TABLES, $evaluateRequired ? REQUIRED_TABLES : []) as $bare) {
    $isRequired = in_array($bare, REQUIRED_TABLES, true);
    $want = $prefix . $bare;
    $found = null;

    foreach ($tables as $table) {
        if (ends_with_table($table, $bare)) {
            $found = $table;

            if ($table === $want) {
                break;
            }
        }
    }

    if ($found === $want) {
        $report['tables_found'][] = $want;
        continue;
    }

    if ($isRequired) {
        $report['required_missing'][] = $want;
    } else {
        $report['tables_missing'][] = $want;
    }

    if ($found !== null && prefix_of($found, $bare) !== null) {
        $report['tables_wrong_prefix'][] = ['expected' => $want, 'found' => $found];
    }
}

if ($report['tables_wrong_prefix'] !== []) {
    $report['verdict'] = 'prefix_mismatch';
} elseif ($report['required_missing'] !== []) {
    // Faltar a tabela de pedidos não é "falta aplicar o núcleo novo": é o
    // config.php apontando para outro banco (ou outro prefixo). Confundir os
    // dois manda o operador aplicar o script no lugar errado — foi o que
    // aconteceu com as tabelas criadas e depois "não encontradas".
    $report['verdict'] = 'config_mismatch';
} elseif ($report['tables_missing'] !== []) {
    $report['verdict'] = 'migration_pending';
}

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
} elseif (!$quiet) {
    echo "prefixo do banco (config.php): {$prefix}\n";
    echo 'prefixo que os SQL do repositório escrevem: ' . EXPECTED_PREFIX . "\n\n";

    foreach ($report['tables_found'] as $table) {
        echo "  ok         {$table}\n";
    }

    foreach ($report['required_missing'] as $table) {
        echo "  AUSENTE    {$table}  (obrigatória — configuração/instalação)\n";
    }

    foreach ($report['tables_missing'] as $table) {
        echo "  ausente    {$table}\n";
    }

    foreach ($report['tables_wrong_prefix'] as $row) {
        echo "  DIVERGENTE esperado {$row['expected']} — encontrado {$row['found']}\n";
    }

    if (!$connected && $assumed === []) {
        echo "\n  (sem banco: a existência das tabelas não foi verificada — só o prefixo)\n";
    }

    echo "\n";
}

// Com --json a saída é só o documento JSON (o veredicto já vai dentro dele,
// no campo `verdict`); misturar texto ali quebraria qualquer consumidor.
$verdictLine = match ($report['verdict']) {
    'ok' => 'veredito: as três tabelas do núcleo novo existem com o prefixo do config.php',
    'migration_pending' => "veredito: migração PENDENTE (nada divergente) — aplique o núcleo novo no prefixo `{$prefix}`",
    'config_mismatch' => 'veredito: CONFIGURAÇÃO SUSPEITA — a tabela de pedidos não existe com este prefixo; o config.php aponta para outro banco?',
    default => 'veredito: DIVERGÊNCIA DE PREFIXO — existe tabela do núcleo com prefixo diferente do config.php',
};

if (!$json) {
    echo $verdictLine, "\n";
}

exit(in_array($report['verdict'], ['prefix_mismatch', 'config_mismatch'], true) ? 1 : 0);
