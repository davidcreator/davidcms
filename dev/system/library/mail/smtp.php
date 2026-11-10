<?php declare(strict_types=1);
/**
 * Basic SMTP mail class
 */
namespace Reamur\System\Library\Mail;

/**
 * Class Smtp
 */
class Smtp {
	/**
	 * @var array<string, mixed>
	 */
	protected array $option = [];
	/**
	 * @var array<string, int|bool>
	 */
	protected array $default = [
		'smtp_port'     => 25,
		'smtp_timeout'  => 5,
		'max_attempts'  => 3,
		'verp'          => false
	];

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $option
	 */
	public function __construct(array &$option = []) {
		foreach ($this->default as $key => $value) {
			if (!isset($option[$key])) {
				$option[$key] = $value;
			}
		}

		$this->option = &$option;
	}

	/**
	 * Remove CR, LF and NUL bytes from single-line SMTP headers.
	 */
	public static function sanitizeHeaderValue(string $value): string {
		return str_replace(["\r", "\n", "\0"], '', trim($value));
	}

	/**
	 * Validate a single email address rejecting CRLF/NUL injection.
	 */
	public static function validateEmailAddress(string $email, string $field = 'email'): string {
		if (preg_match('/[\r\n\0]/', $email) === 1) {
			throw new \InvalidArgumentException("Invalid control characters in SMTP field '$field'");
		}
		$clean = trim($email);
		if (filter_var($clean, FILTER_VALIDATE_EMAIL) === false) {
			throw new \InvalidArgumentException("Invalid email address in SMTP field '$field'");
		}

		return $clean;
	}

	/**
	 * Fold lines to RFC 821/5321 limit (998 bytes) and apply dot-stuffing so
	 * a line starting with '.' cannot prematurely terminate SMTP DATA.
	 *
	 * @return array<int, string>
	 */
	public static function prepareDataLines(string $rawMessage): array {
		$normalized = str_replace(["\r\n", "\r"], "\n", $rawMessage);
		$lines = explode("\n", $normalized);
		$out = [];

		foreach ($lines as $line) {
			$results = ($line === '') ? [''] : str_split($line, 998);
			foreach ($results as $result) {
				if (str_starts_with($result, '.')) {
					$result = '.' . $result;
				}
				$out[] = $result;
			}
		}

		return $out;
	}

	/**
	 * Send
	 *
	 * @param array<string, mixed>|null $option
	 * @return bool
	 */
	public function send(?array $option = null): bool {
		if (is_array($option) && $option) {
			$this->option = $option;
		}

		if (empty($this->option['smtp_hostname'])) {
			throw new \Exception('Error: SMTP hostname required!');
		}

		if (empty($this->option['smtp_username'])) {
			throw new \Exception('Error: SMTP username required!');
		}

		if (empty($this->option['smtp_password'])) {
			throw new \Exception('Error: SMTP password required!');
		}

		if (empty($this->option['smtp_port'])) {
			throw new \Exception('Error: SMTP port required!');
		}

		if (empty($this->option['smtp_timeout'])) {
			throw new \Exception('Error: SMTP timeout required!');
		}

		$from    = self::validateEmailAddress((string)($this->option['from'] ?? ''), 'from');
		$sender  = self::sanitizeHeaderValue((string)($this->option['sender'] ?? ''));
		$subject = self::sanitizeHeaderValue((string)($this->option['subject'] ?? ''));

		$recipients = [];
		if (is_array($this->option['to'] ?? null)) {
			foreach ($this->option['to'] as $recipient) {
				$recipients[] = self::validateEmailAddress((string)$recipient, 'to');
			}
			$toHeader = implode(',', $recipients);
		} else {
			$singleTo = self::validateEmailAddress((string)($this->option['to'] ?? ''), 'to');
			$recipients = [$singleTo];
			$toHeader = $singleTo;
		}

		if ($recipients === []) {
			throw new \Exception('Error: At least one recipient required!');
		}

		$replyTo = '';
		if (!empty($this->option['reply_to'])) {
			$replyTo = self::validateEmailAddress((string)$this->option['reply_to'], 'reply_to');
		}

		$boundary = '----=_NextPart_' . md5((string)time());

		$header = 'MIME-Version: 1.0' . PHP_EOL;
		$header .= 'To: <' . $toHeader . '>' . PHP_EOL;
		$header .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . PHP_EOL;
		$header .= 'Date: ' . date('D, d M Y H:i:s O') . PHP_EOL;
		$header .= 'From: =?UTF-8?B?' . base64_encode($sender) . '?= <' . $from . '>' . PHP_EOL;

		if ($replyTo === '') {
			$header .= 'Reply-To: =?UTF-8?B?' . base64_encode($sender) . '?= <' . $from . '>' . PHP_EOL;
		} else {
			$header .= 'Reply-To: =?UTF-8?B?' . base64_encode($replyTo) . '?= <' . $replyTo . '>' . PHP_EOL;
		}

		$header .= 'Return-Path: ' . $from . PHP_EOL;
		$header .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;
		$header .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . PHP_EOL . PHP_EOL;

		$message = '--' . $boundary . PHP_EOL;

		if (empty($this->option['html'])) {
			$message .= 'Content-Type: text/plain; charset="utf-8"' . PHP_EOL;
			$message .= 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL;
			$message .= chunk_split(base64_encode((string)($this->option['text'] ?? '')), 950) . PHP_EOL;
		} else {
			$message .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '_alt"' . PHP_EOL . PHP_EOL;
			$message .= '--' . $boundary . '_alt' . PHP_EOL;
			$message .= 'Content-Type: text/plain; charset="utf-8"' . PHP_EOL;
			$message .= 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL;

			if (!empty($this->option['text'])) {
				$message .= chunk_split(base64_encode((string)$this->option['text']), 950) . PHP_EOL;
			} else {
				$message .= chunk_split(base64_encode('This is a HTML email and your email client software does not support HTML email!'), 950) . PHP_EOL;
			}

			$message .= '--' . $boundary . '_alt' . PHP_EOL;
			$message .= 'Content-Type: text/html; charset="utf-8"' . PHP_EOL;
			$message .= 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL;
			$message .= chunk_split(base64_encode((string)$this->option['html']), 950) . PHP_EOL;
			$message .= '--' . $boundary . '_alt--' . PHP_EOL;
		}

		$attachments = $this->option['attachments'] ?? $this->option['attachment'] ?? [];
		if (is_array($attachments)) {
			foreach ($attachments as $attachment) {
				if (!is_string($attachment) || !is_file($attachment)) {
					continue;
				}
				$size = filesize($attachment);
				$fileHandle = fopen($attachment, 'rb');
				if ($fileHandle === false) {
					continue;
				}
				$content = ($size !== false && $size > 0) ? fread($fileHandle, $size) : '';
				fclose($fileHandle);
				if ($content === false) {
					continue;
				}

				$safeName = str_replace(['"', '\\', "\r", "\n", "\0"], '', basename($attachment));
				if ($safeName === '') {
					$safeName = 'attachment.bin';
				}

				$message .= '--' . $boundary . PHP_EOL;
				$message .= 'Content-Type: application/octet-stream; name="' . $safeName . '"' . PHP_EOL;
				$message .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
				$message .= 'Content-Disposition: attachment; filename="' . $safeName . '"' . PHP_EOL;
				$message .= 'Content-ID: <' . urlencode($safeName) . '>' . PHP_EOL;
				$message .= 'X-Attachment-Id: ' . urlencode($safeName) . PHP_EOL . PHP_EOL;
				$message .= chunk_split(base64_encode($content), 950);
			}
		}

		$message .= '--' . $boundary . '--' . PHP_EOL;

		$rawHost = self::sanitizeHeaderValue((string)$this->option['smtp_hostname']);
		if (str_starts_with($rawHost, 'tls://')) {
			$hostname = substr($rawHost, 6);
			$useStartTls = true;
		} else {
			$hostname = $rawHost;
			$useStartTls = false;
		}

		$errno = 0;
		$errstr = '';
		$handle = fsockopen($hostname, (int)$this->option['smtp_port'], $errno, $errstr, (int)$this->option['smtp_timeout']);

		if ($handle === false) {
			throw new \Exception('Error: ' . $errstr . ' (' . $errno . ')');
		}

		try {
			stream_set_timeout($handle, (int)$this->option['smtp_timeout'], 0);

			while (($line = fgets($handle, 515)) !== false) {
				if (substr($line, 3, 1) === ' ') {
					break;
				}
			}

			$ehloHost = trim((string)(getenv('SERVER_NAME') ?: 'localhost'));
			if ($ehloHost === '' || preg_match('/[\r\n\0\s]/', $ehloHost) === 1) {
				$ehloHost = 'localhost';
			}

			fputs($handle, 'EHLO ' . $ehloHost . "\r\n");

			$reply = '';
			while (($line = fgets($handle, 515)) !== false) {
				$reply .= $line;

				if (substr($reply, 0, 3) === '220' && substr($line, 3, 1) === ' ') {
					$reply = '';
					continue;
				} elseif (substr($line, 3, 1) === ' ') {
					break;
				}
			}

			if (substr($reply, 0, 3) !== '250') {
				throw new \Exception('Error: EHLO not accepted from server!');
			}

			if ($useStartTls) {
				fputs($handle, 'STARTTLS' . "\r\n");
				$this->handleReply($handle, 220, 'Error: STARTTLS not accepted from server!');

				if (stream_socket_enable_crypto($handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
					throw new \Exception('Error: TLS could not be established!');
				}

				fputs($handle, 'EHLO ' . $ehloHost . "\r\n");
				$this->handleReply($handle, 250, 'Error: EHLO not accepted from server!');
			}

			if (!empty($this->option['smtp_username']) && !empty($this->option['smtp_password'])) {
				fputs($handle, 'AUTH LOGIN' . "\r\n");
				$this->handleReply($handle, 334, 'Error: AUTH LOGIN not accepted from server!');

				fputs($handle, base64_encode((string)$this->option['smtp_username']) . "\r\n");
				$this->handleReply($handle, 334, 'Error: Username not accepted from server!');

				fputs($handle, base64_encode((string)$this->option['smtp_password']) . "\r\n");
				$this->handleReply($handle, 235, 'Error: Password not accepted from server!');
			} else {
				fputs($handle, 'HELO ' . $ehloHost . "\r\n");
				$this->handleReply($handle, 250, 'Error: HELO not accepted from server!');
			}

			if (!empty($this->option['verp'])) {
				fputs($handle, 'MAIL FROM: <' . $from . '>XVERP' . "\r\n");
			} else {
				fputs($handle, 'MAIL FROM: <' . $from . '>' . "\r\n");
			}

			$this->handleReply($handle, 250, 'Error: MAIL FROM not accepted from server!');

			foreach ($recipients as $recipient) {
				fputs($handle, 'RCPT TO: <' . $recipient . '>' . "\r\n");
				$rcptReply = $this->handleReply($handle, false, 'RCPT TO');

				if (substr($rcptReply, 0, 3) !== '250' && substr($rcptReply, 0, 3) !== '251') {
					throw new \Exception('Error: RCPT TO not accepted from server!');
				}
			}

			fputs($handle, 'DATA' . "\r\n");
			$this->handleReply($handle, 354, 'Error: DATA not accepted from server!');

			foreach (self::prepareDataLines($header . $message) as $dataLine) {
				fputs($handle, $dataLine . "\r\n");
			}

			fputs($handle, '.' . "\r\n");
			$this->handleReply($handle, 250, 'Error: DATA not accepted from server!');

			fputs($handle, 'QUIT' . "\r\n");
			$this->handleReply($handle, 221, 'Error: QUIT not accepted from server!');

			return true;
		} finally {
			if (is_resource($handle)) {
				fclose($handle);
			}
		}
	}

	/**
	 * handleReply
	 *
	 * @param mixed        $handle
	 * @param int|false    $status_code
	 * @param string|false $error_text
	 * @param int          $counter
	 */
	private function handleReply(mixed $handle, int|false $status_code = false, string|false $error_text = false, int $counter = 0): string {
		if (!is_resource($handle)) {
			return '';
		}

		$reply = '';
		$line  = false;

		while (($line = fgets($handle, 515)) !== false) {
			$reply .= $line;

			if (substr($line, 3, 1) === ' ') {
				break;
			}
		}

		// Handle slowish server responses (generally due to policy servers)
		if ($line === false && $reply === '' && $counter < (int)($this->option['max_attempts'] ?? 3)) {
			sleep(1);

			return $this->handleReply($handle, $status_code, $error_text, $counter + 1);
		}

		if ($status_code !== false && substr($reply, 0, 3) !== (string)$status_code) {
			throw new \Exception($error_text !== false ? $error_text : 'SMTP reply error');
		}

		return $reply;
	}
}
