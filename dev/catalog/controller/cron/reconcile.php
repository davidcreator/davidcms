<?php declare(strict_types=1);
/**
 * Reconciliação ativa do JOW-Payments (P0-b).
 *
 * Rota: cron/reconcile — token obrigatório, leitura apenas.
 *
 *   GET /catalog/index.php?route=cron/reconcile&token=SEU_TOKEN
 *   Authorization: Bearer SEU_TOKEN
 *
 * Parâmetros opcionais: limit (1..200, padrão 25), min_age (minutos, 1..10080,
 * padrão 15), lookback (dias, 1..90, padrão 7), format (text|json).
 *
 * O token é REAMUR_RECONCILE_TOKEN no config.php (mínimo 32 caracteres).
 * Sem token definido a rota responde 403 e não faz nada: falha fechada.
 *
 * Este controller só LÊ o estado no provedor. Nunca cria cobrança, nunca
 * captura e nunca reembolsa. O estado devolvido passa pelo mesmo caminho do
 * webhook (GatewayEventApplier), com as mesmas checagens de valor, id e
 * transição monotônica.
 */
namespace Reamur\Catalog\Controller\Cron;

use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayEventApplier;
use Reamur\System\Library\Payment\GatewayReconciliationReport;
use Reamur\System\Library\Payment\GatewayReconciliationService;
use Reamur\System\Library\Payment\InstalledGatewayAdapters;

class Reconcile extends \Reamur\System\Engine\Controller {
    private const MIN_TOKEN_LENGTH = 32;

    public function index(): void {
        $this->response->addHeader('Content-Type: text/plain; charset=utf-8');

        $expected = $this->expectedToken();

        if ($expected === '' || !hash_equals($expected, $this->presentedToken())) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->setOutput("forbidden\n");
            return;
        }

        try {
            $limit = $this->intParam('limit', 25);
            $minAge = $this->intParam('min_age', 15);
            $lookback = $this->intParam('lookback', 7);
            $format = (string)($this->request->get['format'] ?? 'text');
        } catch (\InvalidArgumentException) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->setOutput("invalid parameters\n");
            return;
        }

        if ($limit < 1 || $limit > 200 || $minAge < 1 || $minAge > 10080 || $lookback < 1 || $lookback > 90) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->setOutput("invalid parameters\n");
            return;
        }

        $this->load->model('checkout/gateway_payment');

        if (!$this->model_checkout_gateway_payment->gatewaySchemaReady()) {
            // Nothing to reconcile: the additive core migration is not installed.
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
            $this->response->setOutput("schema_missing\n");
            return;
        }

        try {
            $registry = InstalledGatewayAdapters::registry();
            $store = new DbGatewayAccountStore(
                $this->db,
                DB_PREFIX,
                $registry,
                new GatewayCredentialVault($this->secrets())
            );
            $ledger = new DbGatewayLedger($this->db, DB_PREFIX);

            $service = new GatewayReconciliationService(
                $registry,
                $ledger,
                new GatewayEventApplier($ledger)
            );

            $report = $service->reconcileAccountList($store, $limit, $minAge, $lookback);
        } catch (\Throwable $e) {
            $this->safeLog('JOW reconciliation failed: ' . GatewayReconciliationReport::safeReason($e->getMessage()));
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
            $this->response->addHeader('Retry-After: 60');
            $this->response->setOutput("retry\n");
            return;
        }

        $this->safeLog(sprintf(
            'JOW reconciliation: scanned=%d applied=%d unchanged=%d skipped=%d retry=%d failed=%d',
            $report->scanned,
            $report->applied,
            $report->unchanged,
            $report->skipped,
            $report->retry,
            $report->failed
        ));

        // A completed run with per-payment failures still answers 200: the
        // counters are the signal, and a single provider timeout must not turn
        // the cron red for every operator. Integrity/configuration failures do.
        if ($report->hasFailures()) {
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
        }

        $this->response->setOutput(
            $format === 'json' ? $this->jsonOutput($report) : $this->textOutput($report)
        );
    }

    private function textOutput(GatewayReconciliationReport $report): string {
        $lines = [
            'jow reconciliation ' . gmdate('Y-m-d\TH:i:s\Z'),
            sprintf(
                'scanned=%d applied=%d unchanged=%d skipped=%d retry=%d failed=%d',
                $report->scanned,
                $report->applied,
                $report->unchanged,
                $report->skipped,
                $report->retry,
                $report->failed
            ),
        ];

        foreach ($report->details as $detail) {
            if ($detail['outcome'] === GatewayReconciliationReport::UNCHANGED) {
                continue;
            }

            $state = $detail['status_before'] === ''
                ? '-'
                : ($detail['status_before'] === $detail['status_after']
                    ? $detail['status_before']
                    : $detail['status_before'] . '->' . $detail['status_after']);

            $lines[] = sprintf(
                '- tx=%d %s (%s) %s %s',
                $detail['transaction_id'],
                $detail['provider_code'],
                $state,
                $detail['outcome'],
                $detail['reason']
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function jsonOutput(GatewayReconciliationReport $report): string {
        return (string)json_encode(
            ['ok' => !$report->hasFailures(), 'generated_at' => gmdate('Y-m-d\TH:i:s\Z')] + $report->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    /** Overridable seam for tests; never called with a token from the request. */
    protected function expectedToken(): string {
        if (!defined('REAMUR_RECONCILE_TOKEN')) {
            return '';
        }

        $value = constant('REAMUR_RECONCILE_TOKEN');

        if (!is_string($value) || strlen($value) < self::MIN_TOKEN_LENGTH) {
            return '';
        }

        return $value;
    }

    private function presentedToken(): string {
        $header = (string)($this->request->server['HTTP_AUTHORIZATION'] ?? '');

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        $query = $this->request->get['token'] ?? null;

        return is_string($query) ? $query : '';
    }

    private function intParam(string $name, int $default): int {
        $value = $this->request->get[$name] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_scalar($value) || !ctype_digit((string)$value)) {
            throw new \InvalidArgumentException('reconcile: numeric parameter expected');
        }

        return (int)$value;
    }

    private function safeLog(string $line): void {
        try {
            $this->log->write($line);
        } catch (\Throwable) {
            // The batch must not depend on the logger.
        }
    }
}
