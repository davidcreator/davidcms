<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Library;

/**
 * Secure encryption/decryption using OpenSSL with authenticated encryption
 * 
 * This class provides secure encryption/decryption functionality using AES-256-GCM
 * with proper key derivation and authenticated encryption to prevent tampering.
 */
class Encryption {
    private const CIPHER = 'aes-256-gcm';
    private const DIGEST = 'sha256';
    private const KEY_LENGTH = 32; // 256 bits for AES-256
    private const TAG_LENGTH = 16; // GCM tag length
    private const MIN_KEY_LENGTH = 8; // Minimum input key length
    private const PBKDF2_ITERATIONS = 10000; // For key derivation
    
    /**
     * Encrypt data with authenticated encryption
     * 
     * @param string $key Encryption key (minimum 8 characters)
     * @param string $value Data to encrypt
     * @return string Base64 encoded encrypted data with salt, IV and tag
     * @throws \InvalidArgumentException If parameters are invalid
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $key, string $value): string {
        // Input validation
        $this->validateKey($key);
        $this->validateValue($value);
        
        // Check if OpenSSL extension is available
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL extension is required');
        }
        
        try {
            // Generate random salt for key derivation
            $salt = random_bytes(16);
            
            // Derive secure key using PBKDF2
            $derivedKey = hash_pbkdf2(self::DIGEST, $key, $salt, self::PBKDF2_ITERATIONS, self::KEY_LENGTH, true);
            
            // Generate random IV
            $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
            $tag = '';
            
            // Perform encryption
            $encrypted = openssl_encrypt(
                $value,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::TAG_LENGTH
            );
            
            if ($encrypted === false) {
                throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
            }
            
            // Combine salt, IV, tag and encrypted data
            $combined = $salt . $iv . $tag . $encrypted;
            
            return base64_encode($combined);
            
        } catch (\Exception $e) {
            // Clear sensitive data from memory
            if (isset($derivedKey)) {
                sodium_memzero($derivedKey);
            }
            
            if ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Encryption process failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Decrypt data with authentication verification
     * 
     * @param string $key Encryption key used for encryption
     * @param string $value Base64 encoded encrypted data
     * @return string Decrypted data
     * @throws \InvalidArgumentException If parameters are invalid
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $key, string $value): string {
        // Input validation
        $this->validateKey($key);
        $this->validateEncryptedValue($value);
        
        // Check if OpenSSL extension is available
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL extension is required');
        }
        
        try {
            // Decode base64 data
            $raw = base64_decode($value, true);
            if ($raw === false) {
                throw new \RuntimeException('Invalid base64 data');
            }
            
            // Validate minimum data length
            $saltLength = 16;
            $ivLength = openssl_cipher_iv_length(self::CIPHER);
            $minLength = $saltLength + $ivLength + self::TAG_LENGTH;
            
            if (strlen($raw) <= $minLength) {
                throw new \RuntimeException('Invalid encrypted data format: insufficient data length');
            }
            
            // Extract components
            $salt = substr($raw, 0, $saltLength);
            $iv = substr($raw, $saltLength, $ivLength);
            $tag = substr($raw, $saltLength + $ivLength, self::TAG_LENGTH);
            $encrypted = substr($raw, $saltLength + $ivLength + self::TAG_LENGTH);
            
            // Derive the same key used for encryption
            $derivedKey = hash_pbkdf2(self::DIGEST, $key, $salt, self::PBKDF2_ITERATIONS, self::KEY_LENGTH, true);
            
            // Perform decryption with authentication
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            // Clear sensitive key from memory
            sodium_memzero($derivedKey);
            
            if ($decrypted === false) {
                throw new \RuntimeException('Decryption failed: authentication verification failed or corrupted data');
            }
            
            return $decrypted;
            
        } catch (\Exception $e) {
            // Clear sensitive data from memory if it exists
            if (isset($derivedKey)) {
                sodium_memzero($derivedKey);
            }
            
            if ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Decryption process failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Generate a cryptographically secure random key
     * 
     * @param int $length Key length in bytes (default: 32 for AES-256)
     * @return string Random key suitable for encryption
     * @throws \RuntimeException If random key generation fails
     */
    public function generateKey(int $length = self::KEY_LENGTH): string {
        if ($length < self::MIN_KEY_LENGTH) {
            throw new \InvalidArgumentException('Key length must be at least ' . self::MIN_KEY_LENGTH . ' bytes');
        }
        
        try {
            return random_bytes($length);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate secure random key: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Validate encryption key
     * 
     * @param string $key Key to validate
     * @throws \InvalidArgumentException If key is invalid
     */
    private function validateKey(string $key): void {
        if (empty($key)) {
            throw new \InvalidArgumentException('Encryption key cannot be empty');
        }
        
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new \InvalidArgumentException('Key must be at least ' . self::MIN_KEY_LENGTH . ' characters');
        }
    }
    
    /**
     * Validate value to encrypt
     * 
     * @param string $value Value to validate
     * @throws \InvalidArgumentException If value is invalid
     */
    private function validateValue(string $value): void {
        if ($value === '') {
            throw new \InvalidArgumentException('Value to encrypt cannot be empty');
        }
    }
    
    /**
     * Validate encrypted value for decryption
     * 
     * @param string $value Encrypted value to validate
     * @throws \InvalidArgumentException If value is invalid
     */
    private function validateEncryptedValue(string $value): void {
        if (empty($value)) {
            throw new \InvalidArgumentException('Encrypted value cannot be empty');
        }
        
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $value)) {
            throw new \InvalidArgumentException('Invalid base64 format for encrypted data');
        }
    }
    
    /**
     * Get cipher information
     * 
     * @return array Cipher configuration details
     */
    public function getCipherInfo(): array {
        return [
            'cipher' => self::CIPHER,
            'digest' => self::DIGEST,
            'key_length' => self::KEY_LENGTH,
            'tag_length' => self::TAG_LENGTH,
            'pbkdf2_iterations' => self::PBKDF2_ITERATIONS,
            'openssl_available' => extension_loaded('openssl'),
            'sodium_available' => extension_loaded('sodium')
        ];
    }
}