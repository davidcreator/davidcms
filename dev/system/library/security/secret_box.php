<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\System\Library\Security;

/**
 * Cifra dos segredos guardados no banco (ADR 0094, S9).
 *
 * Tokens do Mercado Pago, segredos de webhook e a senha SMTP precisam ser
 * lidos de volta pelo sistema, então não podem virar hash como uma senha de
 * usuário. Aqui eles são cifrados com AES-256-GCM (OpenSSL) antes de ir para
 * o banco, com uma chave que fica FORA do banco: a constante
 * REAMUR_SECRET_KEY, igual no config.php do catálogo e no do admin.
 *
 * Quem obtém só uma cópia do banco (backup, exportação, phpMyAdmin, leitura
 * por falha de SQL) passa a ver `rmsenc:v1:…` no lugar do segredo. Quem lê
 * os arquivos do site lê também a chave: contra isso esta classe não protege.
 *
 * Regras de comportamento:
 *  - sem chave utilizável, seal() devolve o texto como veio (o site continua
 *    funcionando como antes; o painel avisa);
 *  - open() aceita o formato antigo (texto legível) e o cifrado;
 *  - valor cifrado que não abre (chave errada ou perdida) vale VAZIO, nunca o
 *    texto cifrado: ele não pode ser enviado a ninguém como se fosse o token;
 *  - cada valor fica preso ao seu lugar ($context, ex.: 'webhook.secret'):
 *    copiado para outra coluna, não abre;
 *  - seal() não cifra de novo o que já está cifrado: as telas que "mantêm o
 *    valor gravado" podem devolver o conteúdo da coluna sem perdê-lo.
 *
 * A chave nunca entra no Config nem em mensagem de erro ou var_dump.
 */
final class SecretBox {
    /** Marca do valor cifrado. Um segredo real não começa assim. */
    public const PREFIX = 'rmsenc:v1:';

    public const KEY_OK          = 'ok';
    public const KEY_MISSING     = 'missing';
    public const KEY_INVALID     = 'invalid';
    public const KEY_UNAVAILABLE = 'unavailable';

    public const STATE_EMPTY  = 'empty';
    public const STATE_CLEAR  = 'clear';
    public const STATE_SEALED = 'sealed';
    public const STATE_OLD    = 'old';
    public const STATE_LOCKED = 'locked';

    public const PEER_NONE       = 'none';
    public const PEER_SAME       = 'same';
    public const PEER_DIFFERENT  = 'different';
    public const PEER_MISSING    = 'missing';
    public const PEER_UNREADABLE = 'unreadable';

    /** Lugar de cada segredo: entra na cifra, então um valor não abre em outra coluna. */
    public const CTX_MP_ACCESS  = 'mp_seller.access_token';
    public const CTX_MP_REFRESH = 'mp_seller.refresh_token';
    public const CTX_WEBHOOK    = 'webhook.secret';

    /** Chaves de rms_setting que são segredo (contexto: 'setting.' + chave). */
    public const SETTING_KEYS = ['mp_client_secret', 'mp_webhook_secret', 'mail_smtp_password'];

    private const CIPHER    = 'aes-256-gcm';
    private const KEY_BYTES = 32;
    private const NONCE     = 12;
    private const TAG       = 16;

    /** Chave que grava (32 bytes) ou null. */
    private ?string $key;

    /** Chave anterior, só para leitura durante uma troca de chave. */
    private ?string $oldKey;

    private string $keyState;

    /** config.php da outra aplicação, que precisa ter a mesma chave. */
    private ?string $peerConfig;

    private ?string $peerState = null;

    /**
     * @param mixed       $key        REAMUR_SECRET_KEY: 32 bytes aleatórios em base64
     * @param mixed       $oldKey     REAMUR_SECRET_KEY_OLD (opcional): chave anterior, só abre
     * @param string|null $peerConfig caminho do config.php do catálogo, quando quem grava é o
     *                                admin: só cifra se o catálogo conseguir abrir depois
     */
    public function __construct(#[\SensitiveParameter] mixed $key = '', #[\SensitiveParameter] mixed $oldKey = '', ?string $peerConfig = null) {
        $this->peerConfig = ($peerConfig !== null && $peerConfig !== '') ? $peerConfig : null;
        $this->oldKey     = self::decodeKey($oldKey);
        $this->key        = self::decodeKey($key);

        if (!self::available()) {
            $this->key      = null;
            $this->oldKey   = null;
            $this->keyState = self::KEY_UNAVAILABLE;
        } elseif ($this->key !== null) {
            $this->keyState = self::KEY_OK;
        } elseif ($key === null || (is_string($key) && trim($key) === '')) {
            $this->keyState = self::KEY_MISSING;
        } else {
            $this->keyState = self::KEY_INVALID;
        }
    }

    /** Chave nova, pronta para colar no config.php. */
    public static function generateKey(): string {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    /** O PHP desta hospedagem tem o que a cifra precisa? */
    public static function available(): bool {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && in_array(self::CIPHER, array_map('strtolower', openssl_get_cipher_methods()), true);
    }

    /**
     * Chave em base64 → 32 bytes. Qualquer outra coisa (frase, tamanho
     * errado, tipo errado) é recusada: chave fraca não é aceita em silêncio.
     */
    public static function decodeKey(#[\SensitiveParameter] mixed $key): ?string {
        if (!is_string($key)) {
            return null;
        }

        $key = trim($key);

        if ($key === '' || preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $key) !== 1) {
            return null;
        }

        $raw = base64_decode($key, true);

        return (is_string($raw) && strlen($raw) === self::KEY_BYTES) ? $raw : null;
    }

    public static function settingContext(string $key): string {
        return 'setting.' . $key;
    }

    /**
     * Contexto do campo-segredo de uma extensão (EX-01, ADR 0095). Como nos
     * demais, um valor cifrado copiado para outro campo ou outra extensão não abre.
     */
    public static function extensionContext(string $code, string $field): string {
        return 'extension.' . $code . '.' . $field;
    }

    public static function isSealed(string $value): bool {
        return str_starts_with($value, self::PREFIX);
    }

    /** ok | missing | invalid | unavailable (sem OpenSSL). */
    public function keyState(): string {
        return $this->keyState;
    }

    public function hasOldKey(): bool {
        return $this->oldKey !== null;
    }

    /**
     * A outra aplicação tem a mesma chave? Só o admin pergunta (é ele quem
     * grava); o arquivo é lido uma vez por requisição, e só quando preciso.
     */
    public function peerState(): string {
        if ($this->peerState !== null) {
            return $this->peerState;
        }

        if ($this->peerConfig === null) {
            return $this->peerState = self::PEER_NONE;
        }

        $literal = self::readKeyFromConfig($this->peerConfig);

        if ($literal === null) {
            return $this->peerState = self::PEER_UNREADABLE;
        }

        if ($literal === '') {
            return $this->peerState = self::PEER_MISSING;
        }

        $peer = self::decodeKey($literal);

        return $this->peerState = ($peer !== null && $this->key !== null && hash_equals($this->key, $peer))
            ? self::PEER_SAME
            : self::PEER_DIFFERENT;
    }

    /**
     * Grava cifrado? Precisa de chave válida e, no admin, da mesma chave no
     * catálogo: cifrar com uma chave que o catálogo não tem pararia a
     * confirmação dos pagamentos.
     */
    public function canSeal(): bool {
        if ($this->key === null) {
            return false;
        }

        return in_array($this->peerState(), [self::PEER_NONE, self::PEER_SAME], true);
    }

    /**
     * Valor a gravar no banco. Vazio fica vazio; o que já está cifrado fica
     * como está; sem condição de cifrar, o texto segue legível (como antes).
     */
    public function seal(#[\SensitiveParameter] string $plain, string $context): string {
        if ($plain === '' || self::isSealed($plain) || !$this->canSeal() || $this->key === null) {
            return $plain;
        }

        $nonce = random_bytes(self::NONCE);
        $tag   = '';
        $data  = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $context, self::TAG);

        if (!is_string($data) || !is_string($tag) || strlen($tag) !== self::TAG) {
            return $plain;
        }

        return self::PREFIX . base64_encode($nonce . $tag . $data);
    }

    /**
     * Valor para USAR. Formato antigo volta como está; cifrado que não abre
     * volta vazio (use state() para distinguir "vazio" de "não abre").
     */
    public function open(string $stored, string $context): string {
        if (!self::isSealed($stored)) {
            return $stored;
        }

        foreach ([$this->key, $this->oldKey] as $key) {
            $plain = $this->decrypt($stored, $context, $key);

            if ($plain !== null) {
                return $plain;
            }
        }

        return '';
    }

    /**
     * empty  nada gravado
     * clear  legível no banco (formato antigo)
     * sealed cifrado com a chave atual
     * old    cifrado com a chave anterior (REAMUR_SECRET_KEY_OLD)
     * locked cifrado e nenhuma chave configurada abre
     */
    public function state(string $stored, string $context): string {
        if ($stored === '') {
            return self::STATE_EMPTY;
        }

        if (!self::isSealed($stored)) {
            return self::STATE_CLEAR;
        }

        if ($this->decrypt($stored, $context, $this->key) !== null) {
            return self::STATE_SEALED;
        }

        if ($this->decrypt($stored, $context, $this->oldKey) !== null) {
            return self::STATE_OLD;
        }

        return self::STATE_LOCKED;
    }

    /** Atalho para os pontos de uso: há valor gravado, mas ele não abre. */
    public function locked(string $stored, string $context): bool {
        return self::isSealed($stored) && $this->state($stored, $context) === self::STATE_LOCKED;
    }

    /**
     * Valor regravado com a chave atual, ou null quando não há o que fazer
     * (vazio, já cifrado com a chave atual, não abre, ou sem condição de cifrar).
     */
    public function reseal(string $stored, string $context): ?string {
        $state = $this->state($stored, $context);

        if (!in_array($state, [self::STATE_CLEAR, self::STATE_OLD], true) || !$this->canSeal()) {
            return null;
        }

        $plain = $state === self::STATE_CLEAR ? $stored : $this->open($stored, $context);

        if ($plain === '') {
            return null;
        }

        $sealed = $this->seal($plain, $context);

        return self::isSealed($sealed) ? $sealed : null;
    }

    /**
     * Lê define('REAMUR_SECRET_KEY', '…') de um config.php sem executá-lo.
     * Linhas comentadas não contam.
     *
     * @return string|null o texto da chave; '' = arquivo lido, sem a constante; null = não deu para ler
     */
    public static function readKeyFromConfig(string $file): ?string {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        set_error_handler(static fn (): bool => true);

        try {
            $source = php_strip_whitespace($file);
        } catch (\Throwable) {
            $source = '';
        } finally {
            restore_error_handler();
        }

        if ($source === '') {
            return null;
        }

        if (preg_match('/\bdefine\s*\(\s*([\'"])REAMUR_SECRET_KEY\1\s*,\s*([\'"])([^\'"\\\\]*)\2\s*\)/', $source, $m) !== 1) {
            return '';
        }

        return $m[3];
    }

    /** @return array<string, string> a chave não aparece em var_dump/print_r */
    public function __debugInfo(): array {
        return ['keyState' => $this->keyState, 'key' => '[oculta]'];
    }

    private function decrypt(string $stored, string $context, #[\SensitiveParameter] ?string $key): ?string {
        if ($key === null) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if (!is_string($raw) || strlen($raw) <= self::NONCE + self::TAG) {
            return null;
        }

        $plain = openssl_decrypt(
            substr($raw, self::NONCE + self::TAG),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE),
            substr($raw, self::NONCE, self::TAG),
            $context
        );

        return is_string($plain) && $plain !== '' ? $plain : null;
    }
}
