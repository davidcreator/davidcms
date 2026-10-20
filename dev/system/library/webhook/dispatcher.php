<?php
namespace Reamur\System\Library\Webhook;

/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Webhook\Dispatcher (F5-01 / ADR 0033) — transporte PURO de entregas de
 * webhook: não toca o banco (quem persiste é o Model de cada aplicação —
 * regra 01 §1) e NUNCA lança exceção (falha de endpoint não pode derrubar
 * o fluxo de orçamento/pedido que o originou).
 *
 * Envelope JSON canônico:
 *   {
 *     "event":       "order_add",
 *     "delivery_id": "uuid-v4",            // 1 por EVENTO×ENDPOINT, estável nos retries
 *     "timestamp":   1729468800,           // unix do despacho (1ª tentativa)
 *     "data":        { ...payload de domínio... }
 *   }
 *
 * Headers emitidos:
 *   Content-Type: application/json
 *   User-Agent: ReamurCMS-Webhook/1.0
 *   X-Reamur-Event: <event>
 *   X-Reamur-Delivery: <delivery_id>       // idempotência no receptor
 *   X-Reamur-Signature: sha256=<HMAC do CORPO com secret do endpoint>
 *
 * Retries SÍNCRONOS: até 3 tentativas com backoff 0s/2s/4s; sucesso = HTTP 2xx
 * encerra imediatamente. Cada tentativa rende 1 resultado (o caller loga).
 * Fila/CRON foi decidida fora de escopo — ADR 0033 §Alternativas.
 */
class Dispatcher {

    /** @var int tentativas máximas por entrega (1 inicial + 2 retries) */
    private const MAX_ATTEMPTS = 3;

    /** @var int[] dormidas antes da tentativa N (índice 1-based → segundos) */
    private const BACKOFF = [1 => 0, 2 => 2, 3 => 4];

    /** @var int timeout de conexão (s) */
    private const CONNECT_TIMEOUT = 3;

    /** @var int timeout total por tentativa (s) */
    private const TOTAL_TIMEOUT = 5;

    /** @var string UA identificável nos logs do receptor */
    private const USER_AGENT = 'ReamurCMS-Webhook/1.0';

    /** @var \Reamur\System\Library\Log */
    private $log;

    public function __construct(object $log) {
        $this->log = $log;
    }

    /**
     * Despacha 1 evento para N endpoints ativos. Retorna resultados por
     * tentativa para o caller persistir em rms_webhook_log.
     *
     * @param string               $event  ex.: order_add
     * @param array<string,mixed>  $data   payload de domínio
     * @param array<int,array{webhook_id:int,url:string,secret:string}> $hooks
     * @return array<int,array{webhook_id:int,delivery:string,payload:string,
     *                         http_code:int,duration:float,attempt:int}>
     */
    public function dispatch(string $event, array $data, array $hooks): array {
        $results = [];

        foreach ($hooks as $hook) {
            $wid     = (int)$hook['webhook_id'];
            $url     = (string)$hook['url'];
            $secret  = (string)$hook['secret'];
            $deliver = $this->uuid4();

            $body = json_encode([
                'event'       => $event,
                'delivery_id' => $deliver,
                'timestamp'   => time(),
                'data'        => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $signature = hash_hmac('sha256', $body, $secret);

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                if (self::BACKOFF[$attempt] > 0) {
                    sleep(self::BACKOFF[$attempt]);
                }

                [$code, $duration] = $this->post($url, $body, $event, $deliver, $signature);

                $results[] = [
                    'webhook_id' => $wid,
                    'delivery'   => $deliver,
                    'payload'    => $body,
                    'http_code'  => $code,
                    'duration'   => $duration,
                    'attempt'    => $attempt,
                ];

                if ($code >= 200 && $code < 300) {
                    break; // sucesso: encerra os retries deste endpoint
                }
            }

            $last = end($results);

            if ($last['http_code'] < 200 || $last['http_code'] >= 300) {
                $this->log->write(
                    "webhook dispatch: endpoint #{$wid} esgotou " . self::MAX_ATTEMPTS .
                    " tentativas p/ {$event} (último HTTP {$last['http_code']}, delivery {$deliver})"
                );
            }
        }

        return $results;
    }

    /**
     * POST único com os headers do protocolo. Nunca lança — falha de rede
     * retorna http_code 0.
     *
     * @return array{0:int,1:float} [http_code, duration em segundos]
     */
    private function post(string $url, string $body, string $event, string $deliver, string $signature): array {
        try {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,      // 3xx não é sucesso nem seguido (SSRF-safety)
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
                CURLOPT_USERAGENT      => self::USER_AGENT,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Reamur-Event: ' . $event,
                    'X-Reamur-Delivery: ' . $deliver,
                    'X-Reamur-Signature: sha256=' . $signature,
                ],
            ]);

            curl_exec($ch);
            $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $duration = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            curl_close($ch);

            return [$code, $duration];
        } catch (\Throwable $e) {
            $this->log->write('webhook dispatcher exceção: ' . $e->getMessage());
            return [0, 0.0];
        }
    }

    /** Identidade única por entrega — receptor usa para deduplicar retries. */
    private function uuid4(): string {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
