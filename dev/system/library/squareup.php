<?php
/**
 * Square API Integration - Versão Melhorada
 * Handles all Square payment gateway operations
 * 
 * @version 2.0
 * @author OpenCart Square Integration
 */
class Squareup {
    private $session;
    private $url;
    private $config;
    private $log;
    private $customer;
    private $currency;
    private $registry;

    const API_URL = 'https://connect.squareup.com';
    const API_VERSION = 'v2';
    const ENDPOINT_ADD_CARD = 'customers/%s/cards';
    const ENDPOINT_AUTH = 'oauth2/authorize';
    const ENDPOINT_CAPTURE_TRANSACTION = 'locations/%s/transactions/%s/capture';
    const ENDPOINT_CUSTOMERS = 'customers';
    const ENDPOINT_DELETE_CARD = 'customers/%s/cards/%s';
    const ENDPOINT_GET_TRANSACTION = 'locations/%s/transactions/%s';
    const ENDPOINT_LOCATIONS = 'locations';
    const ENDPOINT_REFRESH_TOKEN = 'oauth2/clients/%s/access-token/renew';
    const ENDPOINT_REFUND_TRANSACTION = 'locations/%s/transactions/%s/refund';
    const ENDPOINT_TOKEN = 'oauth2/token';
    const ENDPOINT_TRANSACTIONS = 'locations/%s/transactions';
    const ENDPOINT_VOID_TRANSACTION = 'locations/%s/transactions/%s/void';
    const PAYMENT_FORM_URL = 'https://js.squareup.com/v2/paymentform';
    const SCOPE = 'MERCHANT_PROFILE_READ PAYMENTS_READ SETTLEMENTS_READ CUSTOMERS_READ CUSTOMERS_WRITE';
    const VIEW_TRANSACTION_URL = 'https://squareup.com/dashboard/sales/transactions/%s/by-unit/%s';
    const SQUARE_INTEGRATION_ID = 'sqi_65a5ac54459940e3600a8561829fd970';
    const TIMEOUT = 30;
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 1; // seconds

    // HTTP Status Codes
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_INTERNAL_ERROR = 500;

    public function __construct($registry) {
        $this->validateRegistry($registry);
        
        $this->session = $registry->get('session');
        $this->url = $registry->get('url');
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->customer = $registry->get('customer');
        $this->currency = $registry->get('currency');
        $this->registry = $registry;
    }

    /**
     * Validate registry dependencies
     * @param object $registry Registry object
     * @throws InvalidArgumentException if required dependencies are missing
     */
    private function validateRegistry($registry) {
        $required_services = ['session', 'url', 'config', 'log', 'customer', 'currency'];
        
        foreach ($required_services as $service) {
            if (!$registry->has($service)) {
                throw new \InvalidArgumentException("Required service '{$service}' not found in registry");
            }
        }
    }

    /**
     * Make API request to Square with retry logic
     * @param array $request_data Request configuration
     * @param int $attempt Current attempt number (for internal use)
     * @return array API response
     * @throws \Squareup\Exception
     */
    public function api($request_data, $attempt = 1) {
        $this->validateRequestData($request_data);

        $url = $this->buildApiUrl($request_data);
        $curl_options = $this->buildCurlOptions($url, $request_data);

        $this->debug("SQUAREUP API REQUEST - Attempt {$attempt}");
        $this->debug("SQUAREUP ENDPOINT: " . $curl_options[CURLOPT_URL]);
        $this->debug("SQUAREUP METHOD: " . $request_data['method']);

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        
        try {
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            if ($result === false) {
                throw new \Squareup\Exception($this->registry, "CURL Error: " . $curl_error, true);
            }
            
            $this->debug("SQUAREUP HTTP CODE: " . $http_code);
            $this->debug("SQUAREUP RESPONSE: " . $result);

            $response = $this->parseResponse($result, $http_code);
            
            // Check if we should retry for certain error conditions
            if ($this->shouldRetry($response, $http_code, $attempt)) {
                return $this->retryRequest($request_data, $attempt);
            }
            
            return $response;
            
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Validate request data structure
     * @param array $request_data Request data to validate
     * @throws InvalidArgumentException if validation fails
     */
    private function validateRequestData($request_data) {
        if (!isset($request_data['method']) || !isset($request_data['endpoint'])) {
            throw new \InvalidArgumentException('Missing required request parameters: method and endpoint');
        }

        $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (!in_array(strtoupper($request_data['method']), $allowed_methods)) {
            throw new \InvalidArgumentException('Invalid HTTP method: ' . $request_data['method']);
        }
    }

    /**
     * Build the complete API URL
     * @param array $request_data Request configuration
     * @return string Complete API URL
     */
    private function buildApiUrl($request_data) {
        $url = self::API_URL;

        if (empty($request_data['no_version'])) {
            $url .= '/' . self::API_VERSION;
        }

        $url .= '/' . ltrim($request_data['endpoint'], '/');

        return $url;
    }

    /**
     * Build CURL options array
     * @param string $url API URL
     * @param array $request_data Request configuration
     * @return array CURL options
     */
    private function buildCurlOptions($url, $request_data) {
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'OpenCart-Square-Integration/2.0',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ];

        $content_type = $request_data['content_type'] ?? 'application/json';
        $params = $this->prepareParameters($request_data, $content_type);

        $curl_options = $this->setCurlMethod($curl_options, $request_data['method'], $params, $url);
        $curl_options[CURLOPT_HTTPHEADER] = $this->buildHeaders($request_data, $content_type, $params);

        return $curl_options;
    }

    /**
     * Prepare request parameters
     * @param array $request_data Request configuration
     * @param string $content_type Content type
     * @return string|array|null Prepared parameters
     */
    private function prepareParameters($request_data, $content_type) {
        if (empty($request_data['parameters']) || !is_array($request_data['parameters'])) {
            return null;
        }

        return $this->encodeParameters($request_data['parameters'], $content_type);
    }

    /**
     * Set CURL method and parameters
     * @param array $curl_options Current CURL options
     * @param string $method HTTP method
     * @param mixed $params Request parameters
     * @param string $url Base URL
     * @return array Updated CURL options
     */
    private function setCurlMethod($curl_options, $method, $params, $url) {
        switch (strtoupper($method)) {
            case 'GET':
                $curl_options[CURLOPT_POST] = false;
                if (is_string($params)) {
                    $curl_options[CURLOPT_URL] .= ((strpos($url, '?') === false) ? '?' : '&') . $params;
                }
                break;
                
            case 'POST':
                $curl_options[CURLOPT_POST] = true;
                if ($params !== null) {
                    $curl_options[CURLOPT_POSTFIELDS] = $params;
                }
                break;
                
            default:
                $curl_options[CURLOPT_CUSTOMREQUEST] = $method;
                if ($params !== null) {
                    $curl_options[CURLOPT_POSTFIELDS] = $params;
                }
                break;
        }

        return $curl_options;
    }

    /**
     * Build HTTP headers
     * @param array $request_data Request configuration
     * @param string $content_type Content type
     * @param mixed $params Request parameters
     * @return array HTTP headers
     */
    private function buildHeaders($request_data, $content_type, $params) {
        $headers = [];

        // Add authorization header
        if (!empty($request_data['auth_type'])) {
            $token = $this->getAuthToken($request_data);
            $headers[] = 'Authorization: ' . $request_data['auth_type'] . ' ' . $token;
        }

        // Add content type header (only if params is not an array to avoid multipart conflicts)
        if (!is_array($params)) {
            $headers[] = 'Content-Type: ' . $content_type;
        }

        // Add custom headers
        if (isset($request_data['headers']) && is_array($request_data['headers'])) {
            $headers = array_merge($headers, $request_data['headers']);
        }

        // Add integration ID header
        $headers[] = 'Square-Version: 2023-10-18';

        return $headers;
    }

    /**
     * Get authentication token
     * @param array $request_data Request configuration
     * @return string Authentication token
     */
    private function getAuthToken($request_data) {
        if (!empty($request_data['token'])) {
            return $request_data['token'];
        }

        if ($this->config->get('payment_squareup_enable_sandbox')) {
            return $this->config->get('payment_squareup_sandbox_token');
        }

        return $this->config->get('payment_squareup_access_token');
    }

    /**
     * Parse API response
     * @param string $result Raw response
     * @param int $http_code HTTP status code
     * @return array Parsed response
     * @throws \Squareup\Exception
     */
    private function parseResponse($result, $http_code) {
        if (empty($result)) {
            throw new \Squareup\Exception($this->registry, "Empty response from Square API", true);
        }

        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Squareup\Exception($this->registry, "Invalid JSON response: " . json_last_error_msg(), true);
        }

        if (!empty($response['errors'])) {
            throw new \Squareup\Exception($this->registry, $response['errors']);
        }

        if (!$this->isSuccessfulHttpCode($http_code)) {
            throw new \Squareup\Exception($this->registry, "HTTP Error: " . $http_code . " - " . $result, true);
        }

        return $response;
    }

    /**
     * Check if HTTP code indicates success
     * @param int $http_code HTTP status code
     * @return bool True if successful
     */
    private function isSuccessfulHttpCode($http_code) {
        return in_array($http_code, [self::HTTP_OK, self::HTTP_CREATED]);
    }

    /**
     * Determine if request should be retried
     * @param array $response API response
     * @param int $http_code HTTP status code
     * @param int $attempt Current attempt number
     * @return bool True if should retry
     */
    private function shouldRetry($response, $http_code, $attempt) {
        if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
            return false;
        }

        // Retry on server errors or rate limiting
        $retryable_codes = [429, 500, 502, 503, 504];
        
        return in_array($http_code, $retryable_codes);
    }

    /**
     * Retry failed request with exponential backoff
     * @param array $request_data Request configuration
     * @param int $attempt Current attempt number
     * @return array API response
     */
    private function retryRequest($request_data, $attempt) {
        $delay = self::RETRY_DELAY * pow(2, $attempt - 1); // Exponential backoff
        
        $this->debug("SQUAREUP RETRY: Waiting {$delay} seconds before retry");
        sleep($delay);
        
        return $this->api($request_data, $attempt + 1);
    }

    /**
     * Verify access token validity
     * @param string $access_token Access token to verify
     * @return bool True if valid
     */
    public function verifyToken($access_token) {
        try {
            $request_data = [
                'method' => 'GET',
                'endpoint' => self::ENDPOINT_LOCATIONS,
                'auth_type' => 'Bearer',
                'token' => $access_token
            ];

            $this->api($request_data);
            return true;
            
        } catch (\Squareup\Exception $e) {
            if ($e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Generate OAuth authorization link
     * @param string $client_id Square application client ID
     * @return string Authorization URL
     */
    public function authLink($client_id) {
        $state = $this->authState();
        $redirect_uri = str_replace('&amp;', '&', $this->url->link('extension/payment/squareup/oauth_callback', 'user_token=' . $this->session->data['user_token'], true));

        $this->session->data['payment_squareup_oauth_redirect'] = $redirect_uri;

        $params = [
            'client_id' => $client_id,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'locale' => 'en-US',
            'session' => 'false',
            'state' => $state,
            'redirect_uri' => $redirect_uri
        ];

        return self::API_URL . '/' . self::ENDPOINT_AUTH . '?' . http_build_query($params);
    }

    /**
     * Fetch available locations
     * @param string $access_token Access token
     * @param string &$first_location_id Reference to store first location ID
     * @return array Filtered locations
     */
    public function fetchLocations($access_token, &$first_location_id) {
        $request_data = [
            'method' => 'GET',
            'endpoint' => self::ENDPOINT_LOCATIONS,
            'auth_type' => 'Bearer',
            'token' => $access_token
        ];

        $api_result = $this->api($request_data);

        if (empty($api_result['locations'])) {
            $first_location_id = null;
            return [];
        }

        $locations = array_filter($api_result['locations'], [$this, 'filterLocation']);

        if (!empty($locations)) {
            $first_location = current($locations);
            $first_location_id = $first_location['id'];
        } else {
            $first_location_id = null;
        }

        return $locations;
    }

    /**
     * Exchange authorization code for access token
     * @param string $code Authorization code
     * @return array Token response
     */
    public function exchangeCodeForAccessToken($code) {
        $request_data = [
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_TOKEN,
            'no_version' => true,
            'parameters' => [
                'client_id' => $this->config->get('payment_squareup_client_id'),
                'client_secret' => $this->config->get('payment_squareup_client_secret'),
                'redirect_uri' => $this->session->data['payment_squareup_oauth_redirect'],
                'code' => $code,
                'grant_type' => 'authorization_code'
            ]
        ];

        return $this->api($request_data);
    }

    /**
     * Refresh access token
     * @return array Token response
     */
    public function refreshToken() {
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_REFRESH_TOKEN, $this->config->get('payment_squareup_client_id')),
            'no_version' => true,
            'auth_type' => 'Client',
            'token' => $this->config->get('payment_squareup_client_secret'),
            'parameters' => [
                'access_token' => $this->config->get('payment_squareup_access_token')
            ]
        ];

        return $this->api($request_data);
    }

    /**
     * Add card to Square customer
     * @param string $square_customer_id Square customer ID
     * @param array $card_data Card data
     * @return array Card information
     */
    public function addCard($square_customer_id, $card_data) {
        $this->validateSquareCustomerId($square_customer_id);
        
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_ADD_CARD, $square_customer_id),
            'auth_type' => 'Bearer',
            'parameters' => $card_data
        ];

        $result = $this->api($request_data);

        return [
            'id' => $result['card']['id'],
            'card_brand' => $result['card']['card_brand'],
            'last_4' => $result['card']['last_4']
        ];
    }

    /**
     * Delete card from Square customer
     * @param string $square_customer_id Square customer ID
     * @param string $card_id Card ID
     * @return array API response
     */
    public function deleteCard($square_customer_id, $card_id) {
        $this->validateSquareCustomerId($square_customer_id);
        $this->validateCardId($card_id);
        
        $request_data = [
            'method' => 'DELETE',
            'endpoint' => sprintf(self::ENDPOINT_DELETE_CARD, $square_customer_id, $card_id),
            'auth_type' => 'Bearer'
        ];

        return $this->api($request_data);
    }

    /**
     * Add logged in customer to Square
     * @return array Customer information
     */
    public function addLoggedInCustomer() {
        if (!$this->customer->isLogged()) {
            throw new \InvalidArgumentException('Customer must be logged in');
        }

        $request_data = [
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_CUSTOMERS,
            'auth_type' => 'Bearer',
            'parameters' => [
                'given_name' => $this->sanitizeString($this->customer->getFirstName()),
                'family_name' => $this->sanitizeString($this->customer->getLastName()),
                'email_address' => filter_var($this->customer->getEmail(), FILTER_VALIDATE_EMAIL),
                'phone_number' => $this->sanitizePhoneNumber($this->customer->getTelephone()),
                'reference_id' => (string)$this->customer->getId()
            ]
        ];

        // Remove empty fields to avoid API errors
        $request_data['parameters'] = array_filter($request_data['parameters']);

        $result = $this->api($request_data);

        return [
            'customer_id' => $this->customer->getId(),
            'sandbox' => $this->config->get('payment_squareup_enable_sandbox'),
            'square_customer_id' => $result['customer']['id']
        ];
    }

    /**
     * Add transaction to Square
     * @param array $data Transaction data
     * @return array Transaction information
     */
    public function addTransaction($data) {
        $location_id = $this->getLocationId();
        
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_TRANSACTIONS, $location_id),
            'auth_type' => 'Bearer',
            'parameters' => $data
        ];

        $result = $this->api($request_data);

        return $result['transaction'];
    }

    /**
     * Get transaction details
     * @param string $location_id Location ID
     * @param string $transaction_id Transaction ID
     * @return array Transaction information
     */
    public function getTransaction($location_id, $transaction_id) {
        $this->validateLocationId($location_id);
        $this->validateTransactionId($transaction_id);
        
        $request_data = [
            'method' => 'GET',
            'endpoint' => sprintf(self::ENDPOINT_GET_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        ];

        $result = $this->api($request_data);

        return $result['transaction'];
    }

    /**
     * Capture authorized transaction
     * @param string $location_id Location ID
     * @param string $transaction_id Transaction ID
     * @return array Transaction information
     */
    public function captureTransaction($location_id, $transaction_id) {
        $this->validateLocationId($location_id);
        $this->validateTransactionId($transaction_id);
        
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_CAPTURE_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        ];

        $this->api($request_data);

        return $this->getTransaction($location_id, $transaction_id);
    }

    /**
     * Void transaction
     * @param string $location_id Location ID
     * @param string $transaction_id Transaction ID
     * @return array Transaction information
     */
    public function voidTransaction($location_id, $transaction_id) {
        $this->validateLocationId($location_id);
        $this->validateTransactionId($transaction_id);
        
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_VOID_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        ];

        $this->api($request_data);

        return $this->getTransaction($location_id, $transaction_id);
    }

    /**
     * Refund transaction
     * @param string $location_id Location ID
     * @param string $transaction_id Transaction ID
     * @param string $reason Refund reason
     * @param float $amount Refund amount
     * @param string $currency Currency code
     * @param string $tender_id Tender ID
     * @return array Transaction information
     */
    public function refundTransaction($location_id, $transaction_id, $reason, $amount, $currency, $tender_id) {
        $this->validateLocationId($location_id);
        $this->validateTransactionId($transaction_id);
        $this->validateRefundData($reason, $amount, $currency, $tender_id);
        
        $request_data = [
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_REFUND_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer',
            'parameters' => [
                'idempotency_key' => $this->generateIdempotencyKey(),
                'tender_id' => $tender_id,
                'reason' => $reason,
                'amount_money' => [
                    'amount' => $this->lowestDenomination($amount, $currency),
                    'currency' => $currency
                ]
            ]
        ];

        $this->api($request_data);

        return $this->getTransaction($location_id, $transaction_id);
    }

    /**
     * Convert amount to lowest denomination (cents)
     * @param float $value Amount value
     * @param string $currency Currency code
     * @return int Amount in lowest denomination
     */
    public function lowestDenomination($value, $currency) {
        $power = $this->currency->getDecimalPlace($currency);
        $value = (float)$value;

        return (int)round($value * pow(10, $power));
    }

    /**
     * Convert amount from lowest denomination to standard
     * @param int $value Amount in lowest denomination
     * @param string $currency Currency code
     * @return float Standard amount
     */
    public function standardDenomination($value, $currency) {
        $power = $this->currency->getDecimalPlace($currency);
        $value = (int)$value;

        return (float)($value / pow(10, $power));
    }

    /**
     * Debug logging
     * @param string $text Debug message
     */
    public function debug($text) {
        if ($this->config->get('payment_squareup_debug')) {
            $this->log->write('[SQUAREUP] ' . $text);
        }
    }

    /**
     * Filter location by capabilities
     * @param array $location Location data
     * @return bool True if location supports credit card processing
     */
    protected function filterLocation($location) {
        if (empty($location['capabilities'])) {
            return false;
        }

        return in_array('CREDIT_CARD_PROCESSING', $location['capabilities']);
    }

    /**
     * Encode parameters based on content type
     * @param array $params Parameters to encode
     * @param string $content_type Content type
     * @return string|array Encoded parameters
     */
    protected function encodeParameters($params, $content_type) {
        switch ($content_type) {
            case 'application/json':
                return json_encode($params, JSON_UNESCAPED_UNICODE);
                
            case 'application/x-www-form-urlencoded':
                return http_build_query($params);
                
            case 'multipart/form-data':
            default:
                return $params; // curl handles this automatically
        }
    }

    /**
     * Generate authentication state token
     * @return string Random state token
     */
    protected function authState() {
        if (!isset($this->session->data['payment_squareup_oauth_state'])) {
            $this->session->data['payment_squareup_oauth_state'] = bin2hex(random_bytes(32));
        }

        return $this->session->data['payment_squareup_oauth_state'];
    }

    /**
     * Get current location ID based on environment
     * @return string Location ID
     */
    private function getLocationId() {
        if ($this->config->get('payment_squareup_enable_sandbox')) {
            return $this->config->get('payment_squareup_sandbox_location_id');
        }

        return $this->config->get('payment_squareup_location_id');
    }

    /**
     * Generate unique idempotency key
     * @return string Idempotency key
     */
    private function generateIdempotencyKey() {
        return uniqid('sq_', true) . '_' . time();
    }

    /**
     * Sanitize string input
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeString($input) {
        return trim(filter_var($input, FILTER_SANITIZE_STRING));
    }

    /**
     * Sanitize phone number
     * @param string $phone Phone number
     * @return string Sanitized phone number
     */
    private function sanitizePhoneNumber($phone) {
        return preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
    }

    /**
     * Validate Square customer ID
     * @param string $customer_id Customer ID
     * @throws InvalidArgumentException
     */
    private function validateSquareCustomerId($customer_id) {
        if (empty($customer_id) || !is_string($customer_id)) {
            throw new \InvalidArgumentException('Invalid Square customer ID');
        }
    }

    /**
     * Validate card ID
     * @param string $card_id Card ID
     * @throws InvalidArgumentException
     */
    private function validateCardId($card_id) {
        if (empty($card_id) || !is_string($card_id)) {
            throw new \InvalidArgumentException('Invalid card ID');
        }
    }

    /**
     * Validate location ID
     * @param string $location_id Location ID
     * @throws InvalidArgumentException
     */
    private function validateLocationId($location_id) {
        if (empty($location_id) || !is_string($location_id)) {
            throw new \InvalidArgumentException('Invalid location ID');
        }
    }

    /**
     * Validate transaction ID
     * @param string $transaction_id Transaction ID
     * @throws InvalidArgumentException
     */
    private function validateTransactionId($transaction_id) {
        if (empty($transaction_id) || !is_string($transaction_id)) {
            throw new \InvalidArgumentException('Invalid transaction ID');
        }
    }

    /**
     * Validate refund data
     * @param string $reason Refund reason
     * @param float $amount Refund amount
     * @param string $currency Currency code
     * @param string $tender_id Tender ID
     * @throws InvalidArgumentException
     */
    private function validateRefundData($reason, $amount, $currency, $tender_id) {
        if (empty($reason) || !is_string($reason)) {
            throw new \InvalidArgumentException('Invalid refund reason');
        }

        if (!is_numeric($amount) || $amount <= 0) {
            throw new \InvalidArgumentException('Invalid refund amount');
        }

        if (empty($currency) || !is_string($currency)) {
            throw new \InvalidArgumentException('Invalid currency code');
        }

        if (empty($tender_id) || !is_string($tender_id)) {
            throw new \InvalidArgumentException('Invalid tender ID');
        }
    }

    /**
     * Get Square dashboard transaction URL
     * @param string $transaction_id Transaction ID
     * @param string $location_id Location ID
     * @return string Dashboard URL
     */
    public function getTransactionUrl($transaction_id, $location_id) {
        return sprintf(self::VIEW_TRANSACTION_URL, $transaction_id, $location_id);
    }

    /**
     * Check if environment is sandbox
     * @return bool True if sandbox mode
     */
    public function isSandbox() {
        return (bool)$this->config->get('payment_squareup_enable_sandbox');
    }

    /**
     * Get current application ID
     * @return string Application ID
     */
    public function getApplicationId() {
        return $this->config->get('payment_squareup_client_id');
    }

    /**
     * Get current environment configuration
     * @return array Environment config
     */
    public function getEnvironmentConfig() {
        $is_sandbox = $this->isSandbox();
        
        return [
            'environment' => $is_sandbox ? 'sandbox' : 'production',
            'application_id' => $this->getApplicationId(),
            'location_id' => $this->getLocationId(),
            'api_url' => self::API_URL,
            'payment_form_url' => self::PAYMENT_FORM_URL
        ];
    }

    /**
     * Validate webhook signature (for future webhook implementation)
     * @param string $body Request body
     * @param string $signature Webhook signature
     * @param string $webhook_signature_key Webhook signature key
     * @return bool True if signature is valid
     */
    public function validateWebhookSignature($body, $signature, $webhook_signature_key) {
        if (empty($body) || empty($signature) || empty($webhook_signature_key)) {
            return false;
        }

        $expected_signature = base64_encode(hash_hmac('sha256', $body, $webhook_signature_key, true));
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Format amount for display
     * @param int $amount Amount in lowest denomination
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    public function formatAmount($amount, $currency) {
        $standard_amount = $this->standardDenomination($amount, $currency);
        return $this->currency->format($standard_amount, $currency);
    }

    /**
     * Log error with context
     * @param string $message Error message
     * @param array $context Additional context
     */
    public function logError($message, $context = []) {
        $log_message = '[SQUAREUP ERROR] ' . $message;
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        
        $this->log->write($log_message);
    }

    /**
     * Get masked credit card number for display
     * @param string $last_4 Last 4 digits
     * @param string $card_brand Card brand
     * @return string Masked card number
     */
    public function getMaskedCardNumber($last_4, $card_brand = '') {
        $mask = '****-****-****-' . $last_4;
        
        if (!empty($card_brand)) {
            return strtoupper($card_brand) . ' ' . $mask;
        }
        
        return $mask;
    }

    /**
     * Check if payment method is available
     * @return bool True if Square is properly configured
     */
    public function isAvailable() {
        $required_configs = [
            'payment_squareup_client_id',
            'payment_squareup_client_secret'
        ];

        if ($this->isSandbox()) {
            $required_configs[] = 'payment_squareup_sandbox_token';
            $required_configs[] = 'payment_squareup_sandbox_location_id';
        } else {
            $required_configs[] = 'payment_squareup_access_token';
            $required_configs[] = 'payment_squareup_location_id';
        }

        foreach ($required_configs as $config_key) {
            if (empty($this->config->get($config_key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get supported currencies
     * @return array Supported currency codes
     */
    public function getSupportedCurrencies() {
        return [
            'USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY',
            'CHF', 'DKK', 'NOK', 'SEK', 'NZD', 'SGD'
        ];
    }

    /**
     * Check if currency is supported
     * @param string $currency Currency code
     * @return bool True if supported
     */
    public function isCurrencySupported($currency) {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Clean up session data
     */
    public function cleanupSession() {
        $session_keys = [
            'payment_squareup_oauth_state',
            'payment_squareup_oauth_redirect'
        ];

        foreach ($session_keys as $key) {
            if (isset($this->session->data[$key])) {
                unset($this->session->data[$key]);
            }
        }
    }

    /**
     * Get API rate limit information (if available in response headers)
     * @param resource $ch CURL handle
     * @return array Rate limit info
     */
    private function getRateLimitInfo($ch) {
        $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        
        return [
            'remaining' => $this->extractHeaderValue($headers, 'X-RateLimit-Remaining'),
            'reset' => $this->extractHeaderValue($headers, 'X-RateLimit-Reset'),
            'limit' => $this->extractHeaderValue($headers, 'X-RateLimit-Limit')
        ];
    }

    /**
     * Extract header value from response
     * @param string $headers Header string
     * @param string $header_name Header name
     * @return string|null Header value
     */
    private function extractHeaderValue($headers, $header_name) {
        if (preg_match('/^' . preg_quote($header_name) . ':\s*(.+)$/mi', $headers, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    /**
     * Create error context for logging
     * @param array $request_data Request data
     * @param string $error_message Error message
     * @return array Error context
     */
    private function createErrorContext($request_data, $error_message) {
        return [
            'endpoint' => $request_data['endpoint'] ?? 'unknown',
            'method' => $request_data['method'] ?? 'unknown',
            'error' => $error_message,
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->isSandbox() ? 'sandbox' : 'production'
        ];
    }

    /**
     * Destructor - cleanup if needed
     */
    public function __destruct() {
        // Cleanup any resources if needed
        // This is called when the object is destroyed
    }
}