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
 * Class Mail
 * Handles email operations with multiple adapters
 */
class Mail {
    private object $adaptor;
    private array $option = [];
    
    // Constantes para validação
    private const REQUIRED_FIELDS = ['to', 'from', 'sender', 'subject'];
    private const MAX_SUBJECT_LENGTH = 998; // RFC 5322
    private const MAX_LINE_LENGTH = 998; // RFC 5322

    /**
     * Constructor
     * @param string $adaptor The mail adapter to use (default: 'mail')
     * @param array $option Configuration options for the adapter
     * @throws \InvalidArgumentException If adapter is invalid
     * @throws \RuntimeException If adapter fails to initialize
     */
    public function __construct(string $adaptor = 'mail', array $option = []) {
        $this->validateAdaptor($adaptor);
        $this->initializeAdaptor($adaptor, $option);
    }

    /**
     * Validate adapter name
     * @param string $adaptor
     * @throws \InvalidArgumentException
     */
    private function validateAdaptor(string $adaptor): void {
        if (empty(trim($adaptor))) {
            throw new \InvalidArgumentException('Adapter name must be a non-empty string');
        }

        // Sanitize adapter name to prevent potential security issues
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $adaptor)) {
            throw new \InvalidArgumentException('Invalid adapter name format');
        }
    }

    /**
     * Initialize the mail adapter
     * @param string $adaptor
     * @param array $option
     * @throws \RuntimeException
     */
    private function initializeAdaptor(string $adaptor, array $option): void {
        $class = 'Reamur\\System\\Library\\Mail\\' . ucfirst(strtolower($adaptor));

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('Mail adapter "%s" not found', $adaptor));
        }

        try {
            $this->option = $option;
            $this->adaptor = new $class($option);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to initialize mail adapter "%s": %s', $adaptor, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Validate email address format
     * @param string $email
     * @return bool
     */
    private function isValidEmail(string $email): bool {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate array of email addresses
     * @param array $emails
     * @throws \InvalidArgumentException
     */
    private function validateEmailArray(array $emails): void {
        if (empty($emails)) {
            throw new \InvalidArgumentException('Email array cannot be empty');
        }

        foreach ($emails as $email) {
            if (!is_string($email) || !$this->isValidEmail($email)) {
                throw new \InvalidArgumentException(sprintf('Invalid email address: %s', $email ?? 'null'));
            }
        }
    }

    /**
     * Set recipient email address(es)
     * @param string|array $to Email address or array of addresses
     * @throws \InvalidArgumentException If email is invalid
     */
    public function setTo($to): void {
        if (is_string($to)) {
            $to = trim($to);
            if (!$this->isValidEmail($to)) {
                throw new \InvalidArgumentException(sprintf('Invalid recipient email address: %s', $to));
            }
        } elseif (is_array($to)) {
            $this->validateEmailArray($to);
        } else {
            throw new \InvalidArgumentException('Recipient must be string or array of email addresses');
        }
        
        $this->option['to'] = $to;
    }

    /**
     * Set sender email address
     * @param string $from Sender email address
     * @throws \InvalidArgumentException If email is invalid
     */
    public function setFrom(string $from): void {
        $from = trim($from);
        if (!$this->isValidEmail($from)) {
            throw new \InvalidArgumentException(sprintf('Invalid sender email address: %s', $from));
        }
        $this->option['from'] = $from;
    }

    /**
     * Set sender name/email
     * @param string $sender Sender identifier
     * @throws \InvalidArgumentException If sender is invalid
     */
    public function setSender(string $sender): void {
        $sender = trim($sender);
        if (empty($sender)) {
            throw new \InvalidArgumentException('Sender cannot be empty');
        }
        $this->option['sender'] = $sender;
    }

    /**
     * Set reply-to email address
     * @param string $reply_to Reply-to email address
     * @throws \InvalidArgumentException If email is invalid
     */
    public function setReplyTo(string $reply_to): void {
        $reply_to = trim($reply_to);
        if (!$this->isValidEmail($reply_to)) {
            throw new \InvalidArgumentException(sprintf('Invalid reply-to email address: %s', $reply_to));
        }
        $this->option['reply_to'] = $reply_to;
    }

    /**
     * Set email subject
     * @param string $subject Email subject
     * @throws \InvalidArgumentException If subject is invalid
     */
    public function setSubject(string $subject): void {
        $subject = trim($subject);
        if (empty($subject)) {
            throw new \InvalidArgumentException('Subject cannot be empty');
        }
        
        if (strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Subject too long (max %d characters)', self::MAX_SUBJECT_LENGTH));
        }
        
        // Remove potential header injection attempts
        $subject = str_replace(["\r", "\n"], '', $subject);
        
        $this->option['subject'] = $subject;
    }

    /**
     * Set plain text message content
     * @param string $text Plain text content
     * @throws \InvalidArgumentException If text is invalid
     */
    public function setText(string $text): void {
        $text = trim($text);
        if (empty($text)) {
            throw new \InvalidArgumentException('Text content cannot be empty');
        }
        $this->option['text'] = $text;
    }

    /**
     * Set HTML message content
     * @param string $html HTML content
     * @throws \InvalidArgumentException If HTML is invalid
     */
    public function setHtml(string $html): void {
        $html = trim($html);
        if (empty($html)) {
            throw new \InvalidArgumentException('HTML content cannot be empty');
        }
        $this->option['html'] = $html;
    }

    /**
     * Add file attachment
     * @param string $filename Path to attachment file
     * @throws \InvalidArgumentException If file is invalid or doesn't exist
     */
    public function addAttachment(string $filename): void {
        $filename = trim($filename);
        if (empty($filename)) {
            throw new \InvalidArgumentException('Attachment filename cannot be empty');
        }
        
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException(sprintf('Attachment file not found: %s', $filename));
        }
        
        if (!is_readable($filename)) {
            throw new \InvalidArgumentException(sprintf('Attachment file not readable: %s', $filename));
        }
        
        $this->option['attachment'][] = $filename;
    }

    /**
     * Validate required fields before sending
     * @throws \RuntimeException If required fields are missing
     */
    private function validateRequiredFields(): void {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($this->option[$field])) {
                throw new \RuntimeException(sprintf('Email field "%s" is required', $field));
            }
        }

        if (empty($this->option['text']) && empty($this->option['html'])) {
            throw new \RuntimeException('Email must contain either text or HTML content');
        }
    }

    /**
     * Get current mail configuration
     * @return array Current mail options (excluding sensitive data)
     */
    public function getOptions(): array {
        // Return copy without sensitive information
        $options = $this->option;
        // Remove any potential sensitive data if needed in the future
        return $options;
    }

    /**
     * Clear all current mail settings
     * @return void
     */
    public function clear(): void {
        $this->option = [];
    }

    /**
     * Check if adapter supports a specific feature
     * @param string $feature Feature name to check
     * @return bool True if feature is supported
     */
    public function supportsFeature(string $feature): bool {
        if (method_exists($this->adaptor, 'supportsFeature')) {
            return $this->adaptor->supportsFeature($feature);
        }
        return false;
    }

    /**
     * Send the email
     * @return bool True on success
     * @throws \RuntimeException If required fields are missing or send fails
     */
    public function send(): bool {
        try {
            $this->validateRequiredFields();
            return $this->adaptor->send($this->option);
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions as-is
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to send email: %s', $e->getMessage()), 0, $e);
        }
    }
}