<?php
namespace Reamur\System\Library\Mail;

/**
 * Class Mail - Email sending functionality
 *
 * @package Reamur\System\Library\Mail
 */
class Mail {
    /** @var array<string, mixed> */
    protected array $option = [];
    protected string $eol;

    /**
     * Constructor
     *
     * @param array<string, mixed> $option Email options
     * @throws \InvalidArgumentException If required options are missing
     */
    public function __construct(array &$option = []) {
        $this->option = &$option;
        $this->eol = (substr(PHP_OS, 0, 3) == 'WIN') ? "\r\n" : PHP_EOL;

        $this->validateRequiredOptions();
    }

    /**
     * Remove CR, LF and NUL bytes from single-line email headers.
     */
    public static function sanitizeHeaderValue(string $value): string {
        return str_replace(["\r", "\n", "\0"], '', trim($value));
    }

    /**
     * Validate a single email address rejecting CRLF/NUL injection.
     */
    public static function validateEmailAddress(string $email, string $field = 'email'): string {
        if (preg_match('/[\r\n\0]/', $email) === 1) {
            throw new \InvalidArgumentException("Invalid control characters in mail field '$field'");
        }
        $clean = trim($email);
        if (filter_var($clean, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Invalid email address in mail field '$field'");
        }

        return $clean;
    }

    /**
     * Validate required email options
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRequiredOptions(): void {
        $required = ['to', 'from', 'sender', 'subject', 'text'];
        foreach ($required as $key) {
            if (empty($this->option[$key])) {
                throw new \InvalidArgumentException("Required email option '$key' is missing");
            }
        }

        self::validateEmailAddress((string)$this->option['from'], 'from');
        if (!empty($this->option['reply_to'])) {
            self::validateEmailAddress((string)$this->option['reply_to'], 'reply_to');
        }
        if (is_array($this->option['to'])) {
            foreach ($this->option['to'] as $recipient) {
                self::validateEmailAddress((string)$recipient, 'to');
            }
        } else {
            self::validateEmailAddress((string)$this->option['to'], 'to');
        }
    }

    /**
     * Send email
     *
     * @param array<string, mixed>|null $option
     * @return bool
     * @throws \RuntimeException If email sending fails
     */
    public function send(?array $option = null): bool {
        if (is_array($option) && $option) {
            $this->option = $option;
        }

        try {
            $this->validateRequiredOptions();

            $to = $this->prepareRecipients();
            $from = self::validateEmailAddress((string)$this->option['from'], 'from');
            $boundary = $this->generateBoundary();
            $header = $this->buildHeaders($boundary, $from);
            $message = $this->buildMessage($boundary);

            ini_set('sendmail_from', $from);

            $subject = '=?UTF-8?B?' . base64_encode(self::sanitizeHeaderValue((string)$this->option['subject'])) . '?=';
            $parameters = self::sanitizeHeaderValue((string)($this->option['parameter'] ?? ''));

            $result = mail($to, $subject, $message, $header, $parameters);

            if (!$result) {
                throw new \RuntimeException('Failed to send email');
            }

            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Email sending failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Prepare recipient addresses
     *
     * @return string
     */
    protected function prepareRecipients(): string {
        if (is_array($this->option['to'])) {
            $clean = [];
            foreach ($this->option['to'] as $recipient) {
                if ((string)$recipient !== '') {
                    $clean[] = self::validateEmailAddress((string)$recipient, 'to');
                }
            }
            if ($clean === []) {
                throw new \InvalidArgumentException('No valid recipients specified');
            }

            return implode(',', $clean);
        }

        return self::validateEmailAddress((string)$this->option['to'], 'to');
    }

    /**
     * Generate MIME boundary
     *
     * @return string
     */
    protected function generateBoundary(): string {
        return '----=_NextPart_' . md5((string)time());
    }

    /**
     * Build email headers
     *
     * @param string $boundary
     * @return string
     */
    protected function buildHeaders(string $boundary, string $from = ''): string {
        $fromAddress = $from !== '' ? $from : self::validateEmailAddress((string)$this->option['from'], 'from');
        $senderName  = self::sanitizeHeaderValue((string)$this->option['sender']);
        $replyEmail  = !empty($this->option['reply_to'])
            ? self::validateEmailAddress((string)$this->option['reply_to'], 'reply_to')
            : $fromAddress;
        $replyName   = !empty($this->option['reply_to']) ? $replyEmail : $senderName;

        $headers = [
            'MIME-Version: 1.0',
            'Date: ' . date('D, d M Y H:i:s O'),
            'From: =?UTF-8?B?' . base64_encode($senderName) . '?= <' . $fromAddress . '>',
            'Reply-To: =?UTF-8?B?' . base64_encode($replyName) . '?= <' . $replyEmail . '>',
            'Return-Path: ' . $fromAddress,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: multipart/mixed; boundary="' . self::sanitizeHeaderValue($boundary) . '"'
        ];

        return implode($this->eol, $headers) . $this->eol . $this->eol;
    }

    /**
     * Build email message body
     *
     * @param string $boundary
     * @return string
     */
    protected function buildMessage(string $boundary): string {
        $message = '--' . $boundary . $this->eol;

        if (empty($this->option['html'])) {
            $message .= $this->buildTextPart();
        } else {
            $message .= $this->buildMultipartContent($boundary);
        }

        $attachments = $this->option['attachments'] ?? $this->option['attachment'] ?? [];
        if (is_array($attachments) && $attachments !== []) {
            $message .= $this->buildAttachments($boundary, $attachments);
        }

        $message .= '--' . $boundary . '--' . $this->eol;

        return $message;
    }

    /**
     * Build plain text email part
     *
     * @return string
     */
    protected function buildTextPart(): string {
        return 'Content-Type: text/plain; charset="utf-8"' . $this->eol .
               'Content-Transfer-Encoding: base64' . $this->eol . $this->eol .
               chunk_split(base64_encode((string)$this->option['text']), 950) . $this->eol;
    }

    /**
     * Build multipart (HTML + text) email content
     *
     * @param string $boundary
     * @return string
     */
    protected function buildMultipartContent(string $boundary): string {
        $altBoundary = $boundary . '_alt';
        $message = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . $this->eol . $this->eol;

        $message .= '--' . $altBoundary . $this->eol;
        $message .= 'Content-Type: text/plain; charset="utf-8"' . $this->eol;
        $message .= 'Content-Transfer-Encoding: base64' . $this->eol . $this->eol;

        $text = !empty($this->option['text'])
            ? (string)$this->option['text']
            : 'This is a HTML email and your email client software does not support HTML email!';

        $message .= chunk_split(base64_encode($text), 950) . $this->eol;
        $message .= '--' . $altBoundary . $this->eol;
        $message .= 'Content-Type: text/html; charset="utf-8"' . $this->eol;
        $message .= 'Content-Transfer-Encoding: base64' . $this->eol . $this->eol;
        $message .= chunk_split(base64_encode((string)$this->option['html']), 950) . $this->eol;
        $message .= '--' . $altBoundary . '--' . $this->eol;

        return $message;
    }

    /**
     * Build email attachments
     *
     * @param string $boundary
     * @param array<int, mixed>|null $attachments
     * @return string
     * @throws \RuntimeException If attachment cannot be read
     */
    protected function buildAttachments(string $boundary, ?array $attachments = null): string {
        $message = '';
        $items = $attachments ?? (is_array($this->option['attachments'] ?? null) ? $this->option['attachments'] : []);

        foreach ($items as $attachment) {
            if (!is_string($attachment) || !is_file($attachment)) {
                continue;
            }

            $content = file_get_contents($attachment);
            if ($content === false) {
                throw new \RuntimeException("Failed to read attachment: $attachment");
            }

            $filename = str_replace(['"', '\\', "\r", "\n", "\0"], '', basename($attachment));
            if ($filename === '') {
                $filename = 'attachment.bin';
            }

            $message .= '--' . $boundary . $this->eol;
            $message .= 'Content-Type: application/octet-stream; name="' . $filename . '"' . $this->eol;
            $message .= 'Content-Transfer-Encoding: base64' . $this->eol;
            $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . $this->eol;
            $message .= 'Content-ID: <' . urlencode($filename) . '>' . $this->eol;
            $message .= 'X-Attachment-Id: ' . urlencode($filename) . $this->eol . $this->eol;
            $message .= chunk_split(base64_encode($content), 950);
        }

        return $message;
    }
}
