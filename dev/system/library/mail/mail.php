<?php
namespace Reamur\System\Library\Mail;

/**
 * Class Mail - Email sending functionality
 * 
 * @package Reamur\System\Library\Mail
 */
class Mail {
    protected array $option = [];
    protected string $eol;

    /**
     * Constructor
     *
     * @param array $option Email options
     * @throws \InvalidArgumentException If required options are missing
     */
    public function __construct(array &$option = []) {
        $this->option = &$option;
        $this->eol = (substr(PHP_OS, 0, 3) == 'WIN') ? "\r\n" : PHP_EOL;
        
        $this->validateRequiredOptions();
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
    }

    /**
     * Send email
     *
     * @return bool
     * @throws \RuntimeException If email sending fails
     */
    public function send(?array $option = null): bool {
        if (is_array($option) && $option) {
            $this->option = $option;
        }

        try {
            $to = $this->prepareRecipients();
            $boundary = $this->generateBoundary();
            $header = $this->buildHeaders($boundary);
            $message = $this->buildMessage($boundary);

            ini_set('sendmail_from', $this->option['from']);
            
            $subject = '=?UTF-8?B?' . base64_encode($this->option['subject']) . '?=';
            $parameters = $this->option['parameter'] ?? '';
            
            $result = mail($to, $subject, $message, $header, $parameters);
            
            if (!$result) {
                throw new \RuntimeException('Failed to send email');
            }
            
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException('Email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Prepare recipient addresses
     *
     * @return string
     */
    protected function prepareRecipients(): string {
        return is_array($this->option['to']) 
            ? implode(',', array_filter($this->option['to'])) 
            : (string)$this->option['to'];
    }

    /**
     * Generate MIME boundary
     *
     * @return string
     */
    protected function generateBoundary(): string {
        return '----=_NextPart_' . md5(time());
    }

    /**
     * Build email headers
     *
     * @param string $boundary
     * @return string
     */
    protected function buildHeaders(string $boundary): string {
        $headers = [
            'MIME-Version: 1.0',
            'Date: ' . date('D, d M Y H:i:s O'),
            'From: =?UTF-8?B?' . base64_encode($this->option['sender']) . '?= <' . $this->option['from'] . '>',
            'Reply-To: =?UTF-8?B?' . base64_encode($this->option['reply_to'] ?? $this->option['sender']) . '?= <' . ($this->option['reply_to'] ?? $this->option['from']) . '>',
            'Return-Path: ' . $this->option['from'],
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
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

        if (!empty($this->option['attachments'])) {
            $message .= $this->buildAttachments($boundary);
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
               chunk_split(base64_encode($this->option['text']), 950) . $this->eol;
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
            ? $this->option['text'] 
            : 'This is a HTML email and your email client software does not support HTML email!';
        
        $message .= chunk_split(base64_encode($text), 950) . $this->eol;
        $message .= '--' . $altBoundary . $this->eol;
        $message .= 'Content-Type: text/html; charset="utf-8"' . $this->eol;
        $message .= 'Content-Transfer-Encoding: base64' . $this->eol . $this->eol;
        $message .= chunk_split(base64_encode($this->option['html']), 950) . $this->eol;
        $message .= '--' . $altBoundary . '--' . $this->eol;
        
        return $message;
    }

    /**
     * Build email attachments
     *
     * @param string $boundary
     * @return string
     * @throws \RuntimeException If attachment cannot be read
     */
    protected function buildAttachments(string $boundary): string {
        $message = '';
        
        foreach ($this->option['attachments'] as $attachment) {
            if (!is_file($attachment)) {
                continue;
            }
            
            $content = file_get_contents($attachment);
            if ($content === false) {
                throw new \RuntimeException("Failed to read attachment: $attachment");
            }
            
            $filename = basename($attachment);
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
