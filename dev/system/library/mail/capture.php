<?php
namespace Reamur\System\Library\Mail;

/**
 * Capture mail adapter for deterministic local and CI regression tests.
 */
class Capture {
    protected array $option = [];

    public function __construct(array &$option = []) {
        $this->option = &$option;
    }

    public function send(?array $option = null): bool {
        if (is_array($option) && $option) {
            $this->option = $option;
        }

        $capture_path = trim((string)($this->option['capture_path'] ?? ''));

        if ($capture_path === '') {
            throw new \RuntimeException('Capture path is required for the capture mail adapter.');
        }

        $directory = dirname($capture_path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $payload = [
            'to' => is_array($this->option['to'] ?? null) ? array_values($this->option['to']) : [(string)($this->option['to'] ?? '')],
            'from' => (string)($this->option['from'] ?? ''),
            'sender' => (string)($this->option['sender'] ?? ''),
            'reply_to' => (string)($this->option['reply_to'] ?? ''),
            'subject' => (string)($this->option['subject'] ?? ''),
            'text' => (string)($this->option['text'] ?? ''),
            'html' => (string)($this->option['html'] ?? ''),
            'attachments' => is_array($this->option['attachments'] ?? null)
                ? array_values($this->option['attachments'])
                : (is_array($this->option['attachment'] ?? null) ? array_values($this->option['attachment']) : []),
            'captured_at' => date('c')
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || @file_put_contents($capture_path, $encoded) === false) {
            throw new \RuntimeException('Unable to write the captured email payload at ' . $capture_path);
        }

        return true;
    }
}
