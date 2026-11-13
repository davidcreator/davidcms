<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Mercado Pago (Checkout Pro, marketplace com comissão) como meio de
 * pagamento do contrato (ADR 0097, EX-03; regras de origem: ADR 0035, 0094).
 *
 *   blockedBy()     precisa de um vendedor conectado cujo token abra
 *   charge()        cria a preference com o token do vendedor
 *   notification()  acha o número do pagamento, confere a assinatura e
 *                   CONSULTA o Mercado Pago; o corpo do aviso nunca é prova
 */
namespace Reamur\System\Library\Payment;

use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Security\SecretBox;

final class Mercadopago implements Method {
    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    private ?MercadopagoMarketplace $api;

    /**
     * Tudo chega por função, e só é buscado quando preciso: um aviso que
     * nem é de pagamento não custa consulta ao banco.
     *
     * @param \Closure(): array<string, mixed>           $settings      configurações do CMS (rms_setting, code 'config')
     * @param \Closure(): list<array<string, mixed>>     $activeSellers vendedores ativos, por ordem de cadastro, com o token aberto
     * @param \Closure(int): array<string, mixed>        $sellerByUser  vendedor ativo pelo usuário do Mercado Pago; [] se não há
     * @param MercadopagoMarketplace|null                $api           só para testes; em uso normal sai das configurações
     */
    public function __construct(
        private SecretBox $secrets,
        private \Closure $settings,
        private \Closure $activeSellers,
        private \Closure $sellerByUser,
        ?MercadopagoMarketplace $api = null,
    ) {
        $this->api = $api;
    }

    /**
     * Cliente da API a partir das configurações gravadas. O segredo do
     * aplicativo está cifrado no banco e é aberto só aqui (ADR 0094).
     *
     * @param array<string, mixed> $settings
     */
    public static function client(array $settings, SecretBox $secrets): MercadopagoMarketplace {
        return new MercadopagoMarketplace([
            'app_id'        => (string)($settings['mp_app_id'] ?? ''),
            'client_secret' => $secrets->open((string)($settings['mp_client_secret'] ?? ''), SecretBox::settingContext('mp_client_secret')),
            'redirect_uri'  => (string)($settings['mp_redirect_uri'] ?? ''),
            // Só existem se alguém gravar as chaves à mão (ambiente de teste);
            // nenhuma tela as oferece.
            'token_url'     => (string)($settings['mp_token_url'] ?? ''),
            'users_me_url'  => (string)($settings['mp_users_me_url'] ?? ''),
            'api_url'       => (string)($settings['mp_api_url'] ?? ''),
        ]);
    }

    public function code(): string {
        return 'mp';
    }

    public function blockedBy(): string {
        $seller = $this->firstSeller();

        if ($seller === []) {
            return 'no_seller';
        }

        return !empty($seller['token_locked']) ? 'token_locked' : '';
    }

    public function charge(ChargeRequest $request): Charge {
        $seller = $this->firstSeller();

        if ($seller === [] || !empty($seller['token_locked'])) {
            return Charge::failed('');
        }

        $percent = (float)str_replace(',', '.', (string)($this->settings()['mp_marketplace_fee_percent'] ?? '10'));
        $percent = max(0.0, min(100.0, $percent));
        $fee     = round($request->amount * $percent / 100, 2);

        $api        = $this->api();
        $preference = $api->createPreference([
            'items' => [[
                'title'       => $request->title,
                'quantity'    => 1,
                'currency_id' => 'BRL',
                'unit_price'  => $request->amount,
            ]],
            'marketplace_fee'    => $fee,
            'external_reference' => (string)$request->orderId,
            'notification_url'   => $request->notifyUrl,
        ], (string)($seller['access_token'] ?? ''));

        $id   = (string)($preference['id'] ?? '');
        $url  = (string)($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '');
        $http = (int)($preference['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300 || $id === '' || $url === '') {
            return Charge::failed($api->extractErrorMessage($preference));
        }

        return Charge::created($id, $url, $fee, (int)($seller['mp_seller_id'] ?? 0), 'BRL');
    }

    public function notification(Notification $notification): Verdict {
        $decoded = $notification->body !== '' ? json_decode($notification->body, true) : null;
        $body    = is_array($decoded) ? $decoded : [];
        $id      = $this->paymentId($notification, $body);

        if ($id <= 0) {
            return Verdict::answer('ignored'); // merchant_order e afins: 200 para o MP parar de reenviar
        }

        // ADR 0094: segredo de assinatura gravado que NÃO ABRE (chave do
        // config.php trocada ou ausente) não vale como "sem segredo": a
        // conferência seria pulada. O MP reenvia depois.
        $context = SecretBox::settingContext('mp_webhook_secret');
        $stored  = (string)($this->settings()['mp_webhook_secret'] ?? '');

        if ($this->secrets->locked($stored, $context)) {
            return Verdict::retry('secret-locked', "o segredo de assinatura gravado não abre com a REAMUR_SECRET_KEY do catalog/config.php (payment $id); notificação devolvida para reenvio");
        }

        // A assinatura é conferida ANTES de qualquer chamada ao Mercado Pago.
        if (!self::signatureValid($this->secrets->open($stored, $context), $id, $notification->header('x-signature'), $notification->header('x-request-id'))) {
            return Verdict::answer('signature-rejected', "assinatura rejeitada (payment $id)");
        }

        // Dono do pagamento: o user_id do aviso (formato novo) aponta direto;
        // sem ele (formato antigo), tenta os vendedores ativos e para no
        // primeiro em que o pagamento existe. Vendedor cujo token não abre
        // não é consultado.
        $seller  = [];
        $payment = [];
        $locked  = false;
        $userId  = is_scalar($body['user_id'] ?? null) ? (int)$body['user_id'] : 0;

        if ($userId > 0) {
            $seller = (array)($this->sellerByUser)($userId);
        }

        if ($seller !== [] && !empty($seller['token_locked'])) {
            $locked = true;
        } elseif ($seller !== []) {
            $payment = $this->confirmed($id, (string)($seller['access_token'] ?? ''));
        }

        if ($payment === []) {
            foreach ((array)($this->activeSellers)() as $candidate) {
                if ($seller !== [] && (int)$candidate['mp_seller_id'] === (int)$seller['mp_seller_id']) {
                    continue;
                }

                if (!empty($candidate['token_locked'])) {
                    $locked = true;
                    continue;
                }

                $payment = $this->confirmed($id, (string)($candidate['access_token'] ?? ''));

                if ($payment !== []) {
                    break;
                }
            }
        }

        if ($payment === [] && $locked) {
            return Verdict::retry('token-locked', "token de vendedor gravado não abre com a REAMUR_SECRET_KEY do catalog/config.php (payment $id); notificação devolvida para reenvio");
        }

        if ($payment === []) {
            return Verdict::answer('not-ours', "payment $id não confirmado em nenhum seller ativo");
        }

        // external_reference = número do pedido, gravado ao criar a cobrança.
        $orderId = (int)($payment['external_reference'] ?? 0);

        if ($orderId <= 0) {
            return Verdict::answer('no-reference', "payment $id sem external_reference");
        }

        return Verdict::confirmed($orderId, (string)$id, (string)($payment['status'] ?? 'pending'));
    }

    /**
     * Cabeçalho x-signature ("ts=...,v1=...") contra o segredo configurado:
     * HMAC sha256 de "id:<pagamento>;request-id:<x-request-id>;ts:<ts>;".
     * Sem segredo configurado não há o que conferir (a consulta ao MP continua valendo).
     */
    public static function signatureValid(string $secret, int $paymentId, string $signatureHeader, string $requestId): bool {
        if ($secret === '') {
            return true;
        }

        if ($paymentId <= 0 || preg_match('/[\r\n\0]/', $requestId) === 1) {
            return false;
        }

        $ts = '';
        $v1 = '';

        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($k === 'ts') {
                $ts = $v;
            } elseif ($k === 'v1') {
                $v1 = $v;
            }
        }

        if ($ts === '' || !ctype_digit($ts) || $v1 === '' || strlen($v1) !== 64 || !ctype_xdigit($v1)) {
            return false;
        }

        $manifest = 'id:' . $paymentId . ';request-id:' . $requestId . ';ts:' . $ts . ';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($v1));
    }

    /**
     * Número do pagamento no aviso: formato novo (?type=payment&data.id=) e
     * antigo (?topic=payment&id=, por GET ou POST). 0 = não é aviso de pagamento.
     *
     * @param array<array-key, mixed> $body corpo JSON já lido ([] se não havia)
     */
    private function paymentId(Notification $notification, array $body): int {
        $get  = $notification->get;
        $post = $notification->post;

        $sources = [
            $get['data.id'] ?? null,
            $get['data_id'] ?? null, // o PHP troca o ponto de ?data.id= por _
            $get['id']      ?? null,
            $post['id']     ?? null,
        ];

        if (isset($body['data']['id'])) {
            $sources[] = $body['data']['id'];
        }

        $queryType = $get['type'] ?? $get['topic'] ?? '';
        $bodyType  = $body['type'] ?? $body['topic'] ?? '';
        $type      = (string)((is_scalar($queryType) && (string)$queryType !== '') ? $queryType : $bodyType);

        if (!in_array($type, ['payment', ''], true)) {
            return 0;
        }

        foreach ($sources as $source) {
            $id = is_scalar($source) ? (int)$source : 0;

            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * Só vale como verdade uma resposta 2xx com pagamento bem formado.
     *
     * @return array<string, mixed>
     */
    private function confirmed(int $paymentId, string $accessToken): array {
        $payment = $this->api()->getPayment($paymentId, $accessToken);
        $http    = (int)($payment['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300 || empty($payment['id']) || empty($payment['status'])) {
            return [];
        }

        return $payment;
    }

    /**
     * Quem recebe as cobranças novas: o primeiro vendedor ativo.
     *
     * @return array<string, mixed>
     */
    private function firstSeller(): array {
        $sellers = (array)($this->activeSellers)();
        $first   = reset($sellers);

        return is_array($first) ? $first : [];
    }

    /** @return array<string, mixed> */
    private function settings(): array {
        return $this->loaded ??= (array)($this->settings)();
    }

    private function api(): MercadopagoMarketplace {
        return $this->api ??= self::client($this->settings(), $this->secrets);
    }
}
