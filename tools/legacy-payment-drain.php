<?php declare(strict_types=1);
/**
 * ADR 0122 (F3) — a drenagem de `rms_mp_payment` é uma MEDIÇÃO, não uma remoção.
 *
 * Responde três perguntas, sem tocar em nada:
 *
 *   1. quantos links legados existem;
 *   2. quantos foram abertos nos últimos N dias (padrão 90);
 *   3. qual a data de criação do link mais novo.
 *
 * Por que isso é suficiente: um link legado só importa enquanto alguém ainda
 * pode abri-lo. Link criado há mais de N dias e nunca aberto está morto — se
 * nenhum link foi aberto na janela, a tabela pode ficar intocada. Mantê-la
 * custa nada; apagá-la por estética custa a rede de segurança de um cliente
 * que abra um e-mail antigo.
 *
 * Uso:
 *   php tools/legacy-payment-drain.php --config=catalog/config.php
 *   php tools/legacy-payment-drain.php --config=... --days=90 --json
 *
 * Códigos de saída (mesma convenção do payment-schema-drift):
 *   0  medido: nenhum link aberto na janela OU a tabela não existe (nada a fazer)
 *   1  medido: há link aberto na janela — NÃO apagar
 *   2  erro de uso ou de conexão
 */

const DEFAULT_DAYS = 90;

/** @return array<string, mixed> */
function readConfig(string $path): array
{
    if (!is_file($path)) {
        return ['error' => 'config não encontrado: ' . $path];
    }

    $config = [];
    // O config.php do ReamurCMS define constantes; incluí-lo é a forma de ler
    // sem duplicar a lógica de descoberta do arquivo.
    include $path;

    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX', 'DB_PORT'] as $key) {
        if (defined($key)) {
            $config[$key] = constant($key);
        }
    }

    if (!isset($config['DB_DATABASE'])) {
        return ['error' => 'config sem DB_DATABASE'];
    }

    return $config;
}

function tableExists(mysqli $db, string $table): bool
{
    $escaped = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '" . $escaped . "'");

    if ($result === false) {
        return false;
    }

    $found = $result->num_rows > 0;
    $result->free();

    return $found;
}

/** @return array{total: int, abrindo: int, mais_novo: string, abertos_na_janela: int} */
function measure(mysqli $db, string $table, int $days): array
{
    $total = (int)($db->query('SELECT COUNT(*) AS n FROM `' . $table . '`')->fetch_assoc()['n'] ?? 0);
    $maisNovo = (string)($db->query('SELECT MAX(`date_added`) AS d FROM `' . $table . '`')->fetch_assoc()['d'] ?? '');

    // "Aberto na janela" = link cuja data de modificação foi tocada na janela.
    // O legado grava `date_modified` a cada abertura e a cada mudança de
    // situação vinda do provedor; é o sinal mais próximo de "alguém usou".
    $abertos = (int)($db->query(
        'SELECT COUNT(*) AS n FROM `' . $table . '`'
        . ' WHERE `date_modified` >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)'
    )->fetch_assoc()['n'] ?? 0);

    $pendentes = (int)($db->query(
        'SELECT COUNT(*) AS n FROM `' . $table . '`'
        . " WHERE `status` NOT IN ('approved', 'refunded', 'cancelled', 'rejected')"
    )->fetch_assoc()['n'] ?? 0);

    return ['total' => $total, 'abrindo' => $pendentes, 'mais_novo' => $maisNovo, 'abertos_na_janela' => $abertos];
}

function main(array $argv): int
{
    $config = 'catalog/config.php';
    $days = DEFAULT_DAYS;
    $json = false;

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--config=')) {
            $config = substr($arg, 9);
        } elseif (str_starts_with($arg, '--days=')) {
            $days = (int)substr($arg, 7);
        } elseif ($arg === '--json') {
            $json = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "uso: php tools/legacy-payment-drain.php --config=catalog/config.php [--days=90] [--json]\n";
            return 0;
        }
    }

    if ($days < 1 || $days > 3650) {
        fwrite(STDERR, "dias fora da faixa (1..3650)\n");

        return 2;
    }

    $settings = readConfig($config);

    if (isset($settings['error'])) {
        fwrite(STDERR, $settings['error'] . "\n");

        return 2;
    }

    $prefix = (string)($settings['DB_PREFIX'] ?? '');
    $table = $prefix . 'mp_payment';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $db = new mysqli(
            (string)($settings['DB_HOSTNAME'] ?? 'localhost'),
            (string)($settings['DB_USERNAME'] ?? ''),
            (string)($settings['DB_PASSWORD'] ?? ''),
            (string)$settings['DB_DATABASE'],
            (int)($settings['DB_PORT'] ?? 3306)
        );
    } catch (Throwable $e) {
        fwrite(STDERR, "conexão falhou: " . $e->getMessage() . "\n");

        return 2;
    }

    if (!tableExists($db, $table)) {
        $payload = ['table' => $table, 'exists' => false, 'veredicto' => 'nothing_to_do'];

        if ($json) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "Tabela `" . $table . "` não existe: nada a drenar.\n";
        }

        return 0;
    }

    $data = measure($db, $table, $days);
    $veredicto = $data['abertos_na_janela'] > 0 ? 'keep' : 'drained';

    $payload = ['table' => $table, 'exists' => true, 'janela_dias' => $days, 'veredicto' => $veredicto] + $data;

    if ($json) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

        return $veredicto === 'keep' ? 1 : 0;
    }

    echo 'Tabela `' . $table . "`\n";
    echo '  links no total:                     ' . $data['total'] . "\n";
    echo '  sem situação final (pending/outros): ' . $data['abrindo'] . "\n";
    echo '  tocados nos últimos ' . $days . ' dias:      ' . $data['abertos_na_janela'] . "\n";
    echo '  link mais novo criado em:            ' . ($data['mais_novo'] !== '' ? $data['mais_novo'] : '(nenhum)') . "\n\n";

    if ($veredicto === 'keep') {
        echo "VEREDICTO: NÃO apagar. Há link tocado na janela de " . $days . " dias:\n";
        echo "  alguém ainda pode abrir um destes. Amplie a janela e repita.\n";

        return 1;
    }

    echo "VEREDICTO: drenada. Nenhum link foi tocado nos últimos " . $days . " dias.\n";
    echo "  Isto NÃO autoriza apagar: manter a tabela custa nada e é a rede de\n";
    echo "  segurança de um cliente que abra um e-mail antigo. A remoção, se um\n";
    echo "  dia acontecer, é manual, com backup e fora deste script.\n";

    return 0;
}

exit(main($argv));
