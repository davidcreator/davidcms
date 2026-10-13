<?php

namespace Squareup;

class Exception extends \Exception {
    const ERR_CODE_ACCESS_TOKEN_REVOKED = 'ACCESS_TOKEN_REVOKED';
    const ERR_CODE_ACCESS_TOKEN_EXPIRED = 'ACCESS_TOKEN_EXPIRED';

    /** @var array */
    private $config;
    
    /** @var \Log */
    private $log;
    
    /** @var \Language */
    private $language;
    
    /** @var array|string */
    private $errors;
    
    /** @var bool */
    private $isCurlError;

    /** @var array */
    private $overrideFields = [
        'billing_address.country',
        'shipping_address.country',
        'email_address',
        'phone_number'
    ];

    /**
     * @param \Registry $registry
     * @param array|string $errors
     * @param bool $is_curl_error
     */
    public function __construct($registry, $errors, $is_curl_error = false) {
        if (!is_array($errors) && !is_string($errors)) {
            throw new \InvalidArgumentException('Errors must be either an array or string');
        }

        $this->errors = $errors;
        $this->isCurlError = (bool)$is_curl_error;
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->language = $registry->get('language');

        $message = $this->concatErrors();

        if ($this->config->get('config_error_log')) {
            $this->log->write('[SquareUp] ' . $message);
        }

        parent::__construct($message);
    }

    public function isCurlError(): bool {
        return $this->isCurlError;
    }

    public function isAccessTokenRevoked(): bool {
        return $this->errorCodeExists(self::ERR_CODE_ACCESS_TOKEN_REVOKED);
    }

    public function isAccessTokenExpired(): bool {
        return $this->errorCodeExists(self::ERR_CODE_ACCESS_TOKEN_EXPIRED);
    }

    protected function errorCodeExists(string $code): bool {
        if (!is_array($this->errors)) {
            return false;
        }

        foreach ($this->errors as $error) {
            if (isset($error['code']) && $error['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    protected function overrideError(string $field): string {
        return (string)$this->language->get('squareup_override_error_' . $field);
    }

    protected function parseError(array $error): string {
        if (!isset($error['detail'])) {
            return 'Unknown error';
        }

        if (!empty($error['field']) && in_array($error['field'], $this->overrideFields, true)) {
            return $this->overrideError($error['field']);
        }

        $message = $error['detail'];

        if (!empty($error['field'])) {
            $fieldMessage = (string)$this->language->get('squareup_error_field');
            $message .= sprintf($fieldMessage, $error['field']);
        }

        return $message;
    }

    protected function concatErrors(): string {
        $messages = [];

        if (is_array($this->errors)) {
            foreach ($this->errors as $error) {
                $messages[] = $this->parseError($error);
            }
        } else {
            $messages[] = (string)$this->errors;
        }

        return implode(' ', $messages);
    }
}