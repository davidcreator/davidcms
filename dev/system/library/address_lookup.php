<?php
namespace Reamur\System\Library;

/**
 * AddressLookup - lightweight postal-code based address resolver with multi-provider fallback.
 *
 * Priority: open/free services first.
 * - Brazil: BrasilAPI (https://brasilapi.com.br) then ViaCEP (https://viacep.com.br).
 * - Other countries (US, CA, GB, JP, etc.): Zippopotam.us (covers many ISO country codes).
 */
class AddressLookup {
    /** @var \Reamur\System\Engine\Registry */
    protected $registry;

    /** @var \Reamur\System\Library\Cache */
    protected $cache;

    /** @var \Reamur\System\Library\Config */
    protected $config;

    /** @var \Reamur\System\Library\Request */
    protected $request;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->cache = $registry->get('cache');
        $this->config = $registry->get('config');
        $this->request = $registry->get('request');
    }

    /**
     * Resolve an address by country code and postal code.
     *
     * @param string $countryCode ISO 2
     * @param string $postalCode  raw postal code (may contain punctuation)
     * @return array [success=>bool, address=>array, message=>string]
     */
    public function resolve(string $countryCode, string $postalCode): array {
        $country = strtoupper(trim($countryCode));
        $postal = trim($postalCode);

        $validation = $this->isValidPostal($country, $postal);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message'] ?? 'Invalid postal code'];
        }

        $cacheKey = 'address.lookup.' . $country . '.' . preg_replace('/\s+/', '', $postal);
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return ['success' => true, 'address' => $cached];
        }

        $address = null;
        $error = null;

        if ($country === 'BR') {
            [$address, $error] = $this->lookupBrasilApi($postal);
            if (!$address) {
                [$address, $error] = $this->lookupViaCep($postal);
            }
        } else {
            [$address, $error] = $this->lookupZippopotam($country, $postal);
        }

        if ($address) {
            $this->cache->set($cacheKey, $address);
            return ['success' => true, 'address' => $address];
        }

        return ['success' => false, 'message' => $error ?: 'Address not found'];
    }

    private function isValidPostal(string $country, string $postal): array {
        $patterns = [
            'BR' => '/^\\d{5}-?\\d{3}$/',
            'US' => '/^\\d{5}(-\\d{4})?$/',
            'GB' => '/^(GIR 0AA|[A-Z]{1,2}\\d[A-Z\\d]? ?\\d[ABD-HJLNP-UW-Z]{2})$/i',
            'CA' => '/^[A-Z]\\d[A-Z][ -]?\\d[A-Z]\\d$/i',
            'JP' => '/^\\d{3}-?\\d{4}$/',
        ];

        if (isset($patterns[$country]) && !preg_match($patterns[$country], $postal)) {
            return ['valid' => false, 'message' => 'Invalid postal code for ' . $country];
        }

        if ($postal === '') {
            return ['valid' => false, 'message' => 'Postal code required'];
        }

        return ['valid' => true];
    }

    private function lookupBrasilApi(string $postal): array {
        $cep = preg_replace('/\\D/', '', $postal);
        $url = "https://brasilapi.com.br/api/cep/v1/{$cep}";
        return $this->httpJson($url, function ($data) use ($cep) {
            if (!isset($data['cep'])) return null;
            return [
                'country_code' => 'BR',
                'postal_code' => $this->formatCep($cep),
                'street' => $data['street'] ?? '',
                'number' => '',
                'complement' => $data['complement'] ?? '',
                'district' => $data['neighborhood'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
            ];
        });
    }

    private function lookupViaCep(string $postal): array {
        $cep = preg_replace('/\\D/', '', $postal);
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        return $this->httpJson($url, function ($data) use ($cep) {
            if (!empty($data['erro'])) return null;
            return [
                'country_code' => 'BR',
                'postal_code' => $this->formatCep($cep),
                'street' => $data['logradouro'] ?? '',
                'number' => '',
                'complement' => $data['complemento'] ?? '',
                'district' => $data['bairro'] ?? '',
                'city' => $data['localidade'] ?? '',
                'state' => $data['uf'] ?? '',
            ];
        });
    }

    private function lookupZippopotam(string $country, string $postal): array {
        $url = "https://api.zippopotam.us/{$country}/" . urlencode($postal);
        return $this->httpJson($url, function ($data) use ($country, $postal) {
            if (empty($data['places'][0])) return null;
            $place = $data['places'][0];
            return [
                'country_code' => $country,
                'postal_code' => $data['post code'] ?? $postal,
                'street' => '',
                'number' => '',
                'complement' => '',
                'district' => '',
                'city' => $place['place name'] ?? '',
                'state' => $place['state abbreviation'] ?? ($place['state'] ?? ''),
            ];
        });
    }

    private function formatCep(string $cep): string {
        return substr($cep, 0, 5) . '-' . substr($cep, 5);
    }

    /**
     * @param string   $url
     * @param callable $mapper returns array|null
     * @return array [address|null, error|null]
     */
    private function httpJson(string $url, callable $mapper): array {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header'  => "Accept: application/json\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ]
            ]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                return [null, 'Lookup failed'];
            }
            $data = json_decode($raw, true);
            $address = $mapper($data);
            if ($address) {
                return [$address, null];
            }
            return [null, 'No results'];
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }
    }
}
