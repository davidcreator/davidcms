<?php declare(strict_types=1);
/**
 * KZ-64 — pré-voo dos testes antes da produção (P4).
 *
 * Responde, em uma execução e sem credencial de provedor, as perguntas da
 * bateria A–M que dependem do BANCO e da CONFIGURAÇÃO — e diz o que ainda
 * precisa de sandbox. É o "passo 0" do roteiro
 * (docs/integracoes/JOW-Payments-Testes-Antes-da-Producao.md).
 *
 * Uso:
 *   php tools/payment-piloto.php --config=catalog/config.php
 *   php tools/payment-piloto.php --config=... --json
 *   php tools/payment-piloto.php --config=... --url=https://site/catalog/index.php
 *
 * Códigos de saída (mesma convenção do payment-schema-drift e do
 * legacy-payment-drain):
 *   0  pronto: nada vermelho
 *   1  há item de atenção que impede um teste de provar o que promete
 *   2  erro de uso ou de conexão
 *
 * LEITURA APENAS. Este script não cria, não altera e não apaga nada: nenhum
 * INSERT/UPDATE/DELETE/DDL existe aqui, e o teste fixa isso. As perguntas que
 * ele faz são todas `SELECT`, `SHOW` e a leitura do config.
 *
 * Nada aqui conversa com provedor: nenhuma chamada de rede.
 */

const READY = 'ready';
const ATENCAO = 'atencao';
const BLOQUEADO = 'bloqueado';

/** @return array<string, mixed> */
function readConfigFile(string $path): array
{
    if (!is_file($path)) {
        return ['error' => 'config não encontrado: ' . $path];
    }

    $config = [];
    include $path;

    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX', 'DB_PORT', 'HTTP_SERVER', 'HTTPS_SERVER'] as $key) {
        if (defined($key)) {
            $config[$key] = constant($key);
        }
    }

    return $config;
}

function connect(array $settings): mysqli
{
    return new mysqli(
        (string)($settings['DB_HOSTNAME'] ?? 'localhost'),
        (string)($settings['DB_USERNAME'] ?? ''),
        (string)($settings['DB_PASSWORD'] ?? ''),
        (string)($settings['DB_DATABASE'] ?? ''),
        (int)($settings['DB_PORT'] ?? 3306)
    );
}

function tableExists(mysqli $db, string $table): bool
{
    $result = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");

    if ($result === false) {
        return false;
    }

    $found = $result->num_rows > 0;
    $result->free();

    return $found;
}

function columnExists(mysqli $db, string $table, string $column): bool
{
    $result = $db->query(
        "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $db->real_escape_string($column) . "'"
    );

    if ($result === false) {
        return false;
    }

    $found = $result->num_rows > 0;
    $result->free();

    return $found;
}

/** @return array<string, mixed> */
function scalar(mysqli $db, string $sql): array
{
    $result = $db->query($sql);

    return ($result === false || $result->num_rows === 0) ? [] : (array)$result->fetch_assoc();
}

/**
 * Linha de resultado. `estado` é READY, ATENCAO ou BLOQUEADO; `testes` são os
 * testes da bateria A–M a que o item se refere.
 *
 * @return array<string, mixed>
 */
function item(string $id, string $titulo, string $estado, string $detalhe, array $testes = [], string $acao = ''): array
{
    return [
        'id' => $id,
        'titulo' => $titulo,
        'estado' => $estado,
        'detalhe' => $detalhe,
        'testes' => $testes,
        'acao' => $acao,
    ];
}

/** @return list<array<string, mixed>> */
function preflight(mysqli $db, string $prefix, string $configPath, string $publicUrl): array
{
    $itens = [];
    $nucleo = ['payment_transaction', 'payment_gateway_event', 'jow_gateway'];

    // ── 1. migração do núcleo (teste 1.1 do roteiro) ────────────────────────
    $ausentes = [];

    foreach ($nucleo as $tabela) {
        if (!tableExists($db, $prefix . $tabela)) {
            $ausentes[] = $prefix . $tabela;
        }
    }

    $itens[] = $ausentes === []
        ? item('schema', 'Tabelas do núcleo novo', READY, 'as três tabelas existem: ' . implode(', ', array_map(static fn ($t) => $prefix . $t, $nucleo)), ['A', 'B', 'E', 'H', 'J'])
        : item('schema', 'Tabelas do núcleo novo', BLOQUEADO, 'faltam: ' . implode(', ', $ausentes), ['A', 'B', 'E', 'H', 'J'], 'aplicar a migração aditiva do núcleo (manual, ver tools/passo2-criar-tabelas-do-nucleo.sql)');

    // ── 2. coluna de âncora consultável (teste G) ───────────────────────────
    if (tableExists($db, $prefix . 'payment_transaction')) {
        $temAncora = columnExists($db, $prefix . 'payment_transaction', 'provider_checkout_id');

        $itens[] = $temAncora
            ? item('ancora', 'Coluna `provider_checkout_id`', READY, 'existe: Stripe e PayPal reconciliam sem `missing_provider_anchor`', ['G'])
            : item('ancora', 'Coluna `provider_checkout_id`', ATENCAO, 'ausente: Stripe e PayPal vão responder `missing_provider_anchor` (comportamento declarado, não defeito)', ['G'], 'aplicar tools/passo3-coluna-da-reconciliacao.sql se for testar G com reconciliação');
    }

    // ── 3. contas de gateway e o que falta em cada uma ──────────────────────
    $exigidos = [
        'mp' => ['access_token', 'webhook_secret'],
        'stripe' => ['secret_key', 'webhook_secret'],
        'paypal' => ['client_id', 'client_secret', 'webhook_id'],
    ];

    if (tableExists($db, $prefix . 'jow_gateway')) {
        $contas = $db->query(
            "SELECT `gateway_id`, `provider_code`, `account_label`, `environment`, `status`, `credentials_encrypted_json`"
            . " FROM `" . $prefix . "jow_gateway` ORDER BY `gateway_id`"
        );

        $linhas = $contas === false ? [] : (array)$contas->fetch_all(MYSQLI_ASSOC);

        if ($linhas === []) {
            $itens[] = item('contas', 'Contas de gateway', ATENCAO, 'nenhuma conta cadastrada: A, B, C, D, E, G, J e M não têm como rodar', ['A', 'B', 'C', 'D', 'E', 'G', 'J', 'M'], 'cadastrar a conta de sandbox em JOW Payments → Contas próprias do JOW');
        }

        foreach ($linhas as $conta) {
            $provider = (string)($conta['provider_code'] ?? '');
            $id = (int)($conta['gateway_id'] ?? 0);
            $rotulo = (string)($conta['account_label'] ?? '');
            $ambiente = (string)($conta['environment'] ?? '');
            $habilitada = (int)($conta['status'] ?? 0) === 1;

            // O JSON é cifrado: dá para conferir QUAIS campos existem sem revelar
            // valor nenhum. Segredo nunca é impresso por este script.
            $campos = [];

            if (is_string($conta['credentials_encrypted_json'] ?? null) && $conta['credentials_encrypted_json'] !== '') {
                $bruto = json_decode((string)$conta['credentials_encrypted_json'], true);
                $campos = is_array($bruto) ? array_keys($bruto) : [];
            }

            $faltando = array_values(array_diff($exigidos[$provider] ?? [], $campos));
            $testes = match ($provider) {
                'mp' => ['A', 'B', 'C', 'D', 'E', 'J'],
                'stripe' => ['G'],
                'paypal' => ['G'],
                default => [],
            };

            $detalhe = sprintf(
                'gateway_id %d, rótulo "%s", ambiente %s, %s, campos presentes: %s',
                $id,
                $rotulo,
                $ambiente !== '' ? $ambiente : '(vazio)',
                $habilitada ? 'habilitada' : 'DESABILITADA',
                $campos === [] ? '(nenhum)' : implode(', ', $campos)
            );

            $estado = READY;

            if (!$habilitada || $faltando !== []) {
                $estado = ATENCAO;
                $detalhe .= $faltando === [] ? '' : '; FALTAM: ' . implode(', ', $faltando);
            }

            if ($ambiente !== 'sandbox' && $ambiente !== '') {
                $estado = BLOQUEADO;
                $detalhe .= '; ambiente não é sandbox — a regra de ouro do roteiro proíbe testar aqui';
            }

            $itens[] = item('conta-' . $id, 'Conta ' . $provider . ' #' . $id, $estado, $detalhe, $testes, $estado === READY ? '' : 'reconfigurar a conta em JOW Payments');
        }
    } else {
        $itens[] = item('contas', 'Contas de gateway', BLOQUEADO, 'tabela ' . $prefix . 'jow_gateway não existe', ['A', 'B', 'C', 'D', 'E', 'G', 'J', 'M'], 'aplicar a migração do núcleo');
    }

    // ── 4. token do cron (testes E e F) ─────────────────────────────────────
    $token = getenv('REAMUR_RECONCILE_TOKEN');
    $tokenConfig = defined('REAMUR_RECONCILE_TOKEN') ? (string)constant('REAMUR_RECONCILE_TOKEN') : '';

    if ($token === false) {
        $token = '';
    }

    $token = $token !== '' ? $token : $tokenConfig;

    $itens[] = strlen($token) >= 32
        ? item('token', 'Token do cron de reconciliação', READY, 'definido, ' . strlen($token) . ' caracteres (valor nunca é impresso)', ['E', 'F'])
        : item('token', 'Token do cron de reconciliação', BLOQUEADO, $token === '' ? 'não definido' : 'definido com ' . strlen($token) . ' caracteres (mínimo 32)', ['E', 'F'], 'gerar com php -r "echo bin2hex(random_bytes(24)), PHP_EOL;" e gravar em catalog/config.php');

    // ── 5. URL pública (teste B: o provedor precisa alcançar) ───────────────
    $ehLocal = preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\]|.*\.local)(:|\/|$)#i', $publicUrl) === 1;
    $https = str_starts_with($publicUrl, 'https://');

    if ($publicUrl === '') {
        $itens[] = item('url', 'URL pública do webhook', BLOQUEADO, 'não informada e não encontrada no config', ['B', 'C', 'D', 'E'], 'informar --url=https://seu-site/catalog/index.php (ou túnel, se for máquina local)');
    } elseif ($ehLocal || !$https) {
        $itens[] = item('url', 'URL pública do webhook', BLOQUEADO, $publicUrl . ($ehLocal ? ' (host local: o provedor não alcança)' : ' (sem HTTPS)'), ['B', 'C', 'D', 'E'], 'expor com túnel HTTPS e usar o host do túnel na URL do webhook');
    } else {
        $itens[] = item('url', 'URL pública do webhook', READY, $publicUrl, ['B', 'C', 'D', 'E']);
    }

    // ── 6. resíduos de teste anteriores (o que a bateria não pode confundir) ─
    if (tableExists($db, $prefix . 'payment_transaction')) {
        $abertas = scalar($db, "SELECT COUNT(*) AS n FROM `" . $prefix . "payment_transaction` WHERE `status` IN ('pending','requires_action','authorized')");
        $pagas = scalar($db, "SELECT COUNT(*) AS n FROM `" . $prefix . "payment_transaction` WHERE `status` IN ('paid','partially_refunded')");

        $itens[] = item(
            'fila',
            'Tentativas já no ledger',
            READY,
            'abertas: ' . (int)($abertas['n'] ?? 0) . ' · pagas (janela de estorno): ' . (int)($pagas['n'] ?? 0)
                . '. Limpe o que for de teste antes de rodar A–M, senão o relatório do cron conta o que não é do teste.',
            ['E', 'F']
        );
    }

    // ── 7. a conta desligada no fim (critério de aceite do roteiro) ──────────
    if (tableExists($db, $prefix . 'jow_gateway')) {
        $ligadas = scalar($db, "SELECT COUNT(*) AS n FROM `" . $prefix . "jow_gateway` WHERE `environment` = 'sandbox' AND `status` = 1");

        $itens[] = item(
            'desligar',
            'Contas de sandbox ligadas agora',
            READY,
            (int)($ligadas['n'] ?? 0) . ' conta(s). Ao fim da bateria, desligue: deixá-las ligadas faz o cron bater no provedor de teste à toa.',
            ['A', 'B', 'E']
        );
    }

    // ── 8. links legados (teste K: link antigo continua abrindo) ─────────────
    if (tableExists($db, $prefix . 'mp_payment')) {
        $legado = scalar($db, "SELECT COUNT(*) AS n, MAX(`date_added`) AS d FROM `" . $prefix . "mp_payment`");

        $itens[] = item(
            'legado',
            'Links no contrato antigo (`rms_mp_payment`)',
            READY,
            (int)($legado['n'] ?? 0) . ' link(s), o mais novo em ' . ((string)($legado['d'] ?? '') !== '' ? (string)$legado['d'] : '(nenhum)')
                . '. O teste K precisa de pelo menos um link antigo vivo; se não houver, o teste não prova nada.',
            ['K'],
            (int)($legado['n'] ?? 0) === 0 ? 'criar um link legado de teste, ou marcar o K como não aplicável nesta instalação' : ''
        );
    }

    // ── 9. o que nenhuma configuração resolve ───────────────────────────────
    $itens[] = item(
        'sandbox',
        'O que só a sandbox prova',
        ATENCAO,
        'assinatura real do provedor, entrega no endereço publicado, reenvio após 503 e o cron rodando de verdade. '
            . 'Nenhum ajuste de banco ou de config substitui isso.',
        ['B', 'C', 'D', 'E']
    );

    return $itens;
}

/** @param list<array<string, mixed>> $itens */
function resumo(array $itens): array
{
    $contagem = [READY => 0, ATENCAO => 0, BLOQUEADO => 0];

    foreach ($itens as $item) {
        $contagem[$item['estado']] = ($contagem[$item['estado']] ?? 0) + 1;
    }

    return $contagem;
}

function main(array $argv): int
{
    $config = 'catalog/config.php';
    $json = false;
    $url = '';

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--config=')) {
            $config = substr($arg, 9);
        } elseif (str_starts_with($arg, '--url=')) {
            $url = substr($arg, 6);
        } elseif ($arg === '--json') {
            $json = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "uso: php tools/payment-piloto.php --config=catalog/config.php [--url=https://site/catalog/index.php] [--json]\n";
            echo "códigos: 0 pronto · 1 atenção · 2 erro de uso/conexão\n";

            return 0;
        }
    }

    $settings = readConfigFile($config);

    if (isset($settings['error'])) {
        fwrite(STDERR, $settings['error'] . "\n");

        return 2;
    }

    if ($url === '') {
        $url = (string)($settings['HTTPS_SERVER'] ?? $settings['HTTP_SERVER'] ?? '');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $db = connect($settings);
    } catch (Throwable $e) {
        fwrite(STDERR, 'conexão falhou: ' . $e->getMessage() . "\n");

        return 2;
    }

    $prefix = (string)($settings['DB_PREFIX'] ?? '');
    $itens = preflight($db, $prefix, $config, $url);
    $contagem = resumo($itens);
    $bloqueado = $contagem[BLOQUEADO] > 0;

    if ($json) {
        echo json_encode([
            'prefix' => $prefix,
            'url' => $url,
            'resumo' => $contagem,
            'veredicto' => $bloqueado ? 'nao_pronto' : ($contagem[ATENCAO] > 0 ? 'pronto_com_atencao' : 'pronto'),
            'itens' => $itens,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        return $bloqueado ? 1 : 0;
    }

    echo "Pré-voo dos testes A–M (leitura apenas)\n";
    echo '  prefixo das tabelas: ' . ($prefix !== '' ? $prefix : '(nenhum)') . "\n";
    echo '  url pública:         ' . ($url !== '' ? $url : '(não informada)') . "\n\n";

    $rotulos = [READY => 'PRONTO   ', ATENCAO => 'ATENÇÃO  ', BLOQUEADO => 'BLOQUEADO'];

    foreach ($itens as $item) {
        echo $rotulos[$item['estado']] . ' ' . $item['titulo'] . "\n";
        echo '           ' . $item['detalhe'] . "\n";

        if ($item['testes'] !== []) {
            echo '           testes: ' . implode(', ', $item['testes']) . "\n";
        }

        if ($item['acao'] !== '') {
            echo '           ação:   ' . $item['acao'] . "\n";
        }

        echo "\n";
    }

    echo 'RESUMO: ' . $contagem[READY] . ' pronto(s), ' . $contagem[ATENCAO] . ' atenção, ' . $contagem[BLOQUEADO] . " bloqueado(s)\n";

    if ($bloqueado) {
        echo "VEREDICTO: NÃO PRONTO. Há item bloqueado: a bateria mediria a coisa errada.\n";

        return 1;
    }

    echo "VEREDICTO: pronto para a bateria. O que falta é credencial, e o roteiro diz o que cada teste prova.\n";

    return 0;
}

exit(main($argv));
