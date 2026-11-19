<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use DateTimeImmutable;

/** Customer-facing payment instruction; contains no provider response blob. */
final class GatewayCustomerAction {
    public const REDIRECT = 'redirect';
    public const PIX_COPY_PASTE = 'pix_copy_paste';
    public const BOLETO_URL = 'boleto_url';

    private function __construct(
        public readonly string $kind,
        public readonly string $url = '',
        private readonly string $copyPaste = '',
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {}

    public static function redirect(string $url, ?DateTimeImmutable $expiresAt = null): self {
        self::assertHttpsUrl($url);
        return new self(self::REDIRECT, $url, '', $expiresAt);
    }

    public static function boleto(string $url, ?DateTimeImmutable $expiresAt = null): self {
        self::assertHttpsUrl($url);
        return new self(self::BOLETO_URL, $url, '', $expiresAt);
    }

    public static function pixCopyPaste(string $code, ?DateTimeImmutable $expiresAt = null): self {
        if ($code === '' || strlen($code) > 4096 || preg_match('/[\r\n\0]/', $code) === 1) {
            throw new \InvalidArgumentException('gateway customer action: PIX copy-and-paste code is invalid');
        }

        return new self(self::PIX_COPY_PASTE, '', $code, $expiresAt);
    }

    /** @param array<string, mixed> $data */
    public static function fromSafeArray(array $data): self {
        $kind = $data['kind'] ?? '';
        $url = $data['url'] ?? '';
        $copyPaste = $data['copy_paste'] ?? '';
        $expiresAt = $data['expires_at'] ?? null;

        if (!is_string($kind) || !is_string($url) || !is_string($copyPaste)
            || ($expiresAt !== null && !is_string($expiresAt))) {
            throw new \InvalidArgumentException('gateway customer action: stored instruction is malformed');
        }

        $expiry = null;

        if ($expiresAt !== null) {
            try {
                $expiry = new DateTimeImmutable($expiresAt);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('gateway customer action: stored expiry is invalid', 0, $e);
            }
        }

        return match ($kind) {
            self::REDIRECT => self::redirect($url, $expiry),
            self::BOLETO_URL => self::boleto($url, $expiry),
            self::PIX_COPY_PASTE => self::pixCopyPaste($copyPaste, $expiry),
            default => throw new \InvalidArgumentException('gateway customer action: unknown stored instruction type'),
        };
    }

    public function copyPasteValue(): string {
        return $this->copyPaste;
    }

    /** @return array<string, string|null> Only whitelisted customer instructions are serialized. */
    public function toSafeArray(): array {
        return [
            'kind' => $this->kind,
            'url' => $this->url,
            'copy_paste' => $this->copyPaste,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
        ];
    }

    /** @return array{kind: string, expires_at: string|null} */
    public function __debugInfo(): array {
        return ['kind' => $this->kind, 'expires_at' => $this->expiresAt?->format(DATE_ATOM)];
    }

    private static function assertHttpsUrl(string $url): void {
        $parts = parse_url($url);

        if ($url === '' || preg_match('/[\r\n\0]/', $url) === 1 || !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('gateway customer action: URL must be valid HTTPS');
        }
    }
}
