<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Security\SecretBox;

/**
 * S9 (ADR 0094): cifra dos segredos guardados no banco. O que estes testes
 * travam: o valor cifrado nao carrega o segredo, so abre com a chave certa e
 * no lugar certo, e NUNCA volta como se fosse o segredo quando nao abre.
 */
final class SecretBoxTest extends TestCase {
    private const CTX = SecretBox::CTX_MP_ACCESS;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function configFile(string $body): string {
        $file = (string)tempnam(sys_get_temp_dir(), 's9cfg');
        file_put_contents($file, "<?php\n" . $body . "\n");
        $this->files[] = $file;

        return $file;
    }

    public function testGeneratedKeyIs32RandomBytesInBase64(): void {
        $a = SecretBox::generateKey();
        $b = SecretBox::generateKey();

        self::assertNotSame($a, $b);
        self::assertSame(32, strlen((string)SecretBox::decodeKey($a)));
        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]{43}=$#', $a);
        self::assertSame(SecretBox::KEY_OK, (new SecretBox($a))->keyState());
    }

    public function testSealedValueHidesTheSecretAndOpensBack(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $secret = 'APP_USR-1234567890-abcdef-token';
        $sealed = $box->seal($secret, self::CTX);

        self::assertStringStartsWith(SecretBox::PREFIX, $sealed);
        self::assertStringNotContainsString($secret, $sealed);
        self::assertStringNotContainsString($secret, (string)base64_decode(substr($sealed, strlen(SecretBox::PREFIX)), true));
        self::assertSame($secret, $box->open($sealed, self::CTX));
        self::assertSame(SecretBox::STATE_SEALED, $box->state($sealed, self::CTX));
        self::assertFalse($box->locked($sealed, self::CTX));
    }

    public function testSameSecretSealsDifferentlyEachTime(): void {
        $box = new SecretBox(SecretBox::generateKey());

        self::assertNotSame($box->seal('mesmo-segredo-0123456789', self::CTX), $box->seal('mesmo-segredo-0123456789', self::CTX));
    }

    public function testValueIsBoundToItsPlace(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('token-do-vendedor-0123456789', SecretBox::CTX_MP_ACCESS);

        self::assertSame('', $box->open($sealed, SecretBox::CTX_WEBHOOK));
        self::assertSame(SecretBox::STATE_LOCKED, $box->state($sealed, SecretBox::CTX_WEBHOOK));
        self::assertSame('', $box->open($sealed, SecretBox::CTX_MP_REFRESH));
    }

    public function testEmptyStaysEmpty(): void {
        $box = new SecretBox(SecretBox::generateKey());

        self::assertSame('', $box->seal('', self::CTX));
        self::assertSame('', $box->open('', self::CTX));
        self::assertSame(SecretBox::STATE_EMPTY, $box->state('', self::CTX));
        self::assertNull($box->reseal('', self::CTX));
    }

    public function testWithoutKeyNothingChangesAndTheStateSaysSo(): void {
        $box = new SecretBox('');

        self::assertSame(SecretBox::KEY_MISSING, $box->keyState());
        self::assertFalse($box->canSeal());
        self::assertSame('segredo-legivel-0123456789', $box->seal('segredo-legivel-0123456789', self::CTX));
        self::assertSame('segredo-legivel-0123456789', $box->open('segredo-legivel-0123456789', self::CTX));
        self::assertSame(SecretBox::STATE_CLEAR, $box->state('segredo-legivel-0123456789', self::CTX));
        self::assertNull($box->reseal('segredo-legivel-0123456789', self::CTX));
        self::assertSame(SecretBox::KEY_MISSING, (new SecretBox())->keyState());
        self::assertSame(SecretBox::KEY_MISSING, (new SecretBox(null))->keyState());
        self::assertSame(SecretBox::KEY_MISSING, (new SecretBox('   '))->keyState());
    }

    /** @return array<string, array{0: mixed}> */
    public static function badKeys(): array {
        return [
            'frase'            => ['minha chave secreta'],
            '31 bytes'         => [base64_encode(str_repeat('a', 31))],
            '33 bytes'         => [base64_encode(str_repeat('a', 33))],
            'hex de 64'        => [str_repeat('ab', 32)],
            'base64 quebrado'  => [substr(base64_encode(str_repeat('a', 32)), 0, 43) . '!'],
            'espaço no meio'   => [substr(base64_encode(str_repeat('a', 32)), 0, 20) . ' ' . substr(base64_encode(str_repeat('a', 32)), 20)],
            'linha quebrada'   => [substr(base64_encode(str_repeat('a', 32)), 0, 20) . "\n" . substr(base64_encode(str_repeat('a', 32)), 20)],
            'numero'           => [12345],
            'lista'            => [['a']],
            'verdadeiro'       => [true],
        ];
    }

    #[DataProvider('badKeys')]
    public function testWeakOrMalformedKeyIsRefusedNotUsed(mixed $key): void {
        $box = new SecretBox($key);

        self::assertSame(SecretBox::KEY_INVALID, $box->keyState());
        self::assertFalse($box->canSeal());
        self::assertSame('segredo-0123456789abcdef', $box->seal('segredo-0123456789abcdef', self::CTX));
    }

    public function testWrongKeyGivesEmptyNeverTheCiphertext(): void {
        $sealed = (new SecretBox(SecretBox::generateKey()))->seal('token-verdadeiro-0123456789', self::CTX);

        foreach ([new SecretBox(SecretBox::generateKey()), new SecretBox(''), new SecretBox('chave invalida')] as $other) {
            self::assertSame('', $other->open($sealed, self::CTX));
            self::assertSame(SecretBox::STATE_LOCKED, $other->state($sealed, self::CTX));
            self::assertTrue($other->locked($sealed, self::CTX));
            self::assertNull($other->reseal($sealed, self::CTX));
        }
    }

    public function testTamperedValueDoesNotOpen(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('token-verdadeiro-0123456789', self::CTX);
        $raw    = (string)base64_decode(substr($sealed, strlen(SecretBox::PREFIX)), true);

        $cases = [
            'byte trocado no fim'    => SecretBox::PREFIX . base64_encode(substr($raw, 0, -1) . chr(ord($raw[-1]) ^ 1)),
            'byte trocado no inicio' => SecretBox::PREFIX . base64_encode(chr(ord($raw[0]) ^ 1) . substr($raw, 1)),
            'cortado'                => substr($sealed, 0, -8),
            'so o prefixo'           => SecretBox::PREFIX,
            'lixo'                   => SecretBox::PREFIX . '%%%nao-e-base64%%%',
            'curto'                  => SecretBox::PREFIX . base64_encode(str_repeat('x', 28)),
        ];

        foreach ($cases as $label => $value) {
            self::assertSame('', $box->open($value, self::CTX), $label);
            self::assertTrue($box->locked($value, self::CTX), $label);
        }
    }

    public function testAlreadySealedValueIsNotSealedAgain(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('segredo-0123456789abcdef', self::CTX);

        self::assertSame($sealed, $box->seal($sealed, self::CTX));

        // Vale tambem para o que esta chave nao abre: a tela que "mantem o
        // gravado" nao pode destruir um valor so porque a chave esta errada.
        $foreign = (new SecretBox(SecretBox::generateKey()))->seal('outro-segredo-0123456789', self::CTX);
        self::assertSame($foreign, $box->seal($foreign, self::CTX));
    }

    public function testOldKeyOpensAndResealMovesToTheNewKey(): void {
        $oldKey = SecretBox::generateKey();
        $newKey = SecretBox::generateKey();
        $sealed = (new SecretBox($oldKey))->seal('token-verdadeiro-0123456789', self::CTX);

        $box = new SecretBox($newKey, $oldKey);
        self::assertTrue($box->hasOldKey());
        self::assertSame('token-verdadeiro-0123456789', $box->open($sealed, self::CTX));
        self::assertSame(SecretBox::STATE_OLD, $box->state($sealed, self::CTX));
        self::assertFalse($box->locked($sealed, self::CTX));

        $moved = $box->reseal($sealed, self::CTX);
        self::assertIsString($moved);
        self::assertNotSame($sealed, $moved);

        $onlyNew = new SecretBox($newKey);
        self::assertFalse($onlyNew->hasOldKey());
        self::assertSame('token-verdadeiro-0123456789', $onlyNew->open((string)$moved, self::CTX));
        self::assertSame(SecretBox::STATE_SEALED, $onlyNew->state((string)$moved, self::CTX));
        self::assertNull($onlyNew->reseal((string)$moved, self::CTX), 'o que ja esta certo nao e regravado');
        self::assertSame('', (new SecretBox($oldKey))->open((string)$moved, self::CTX), 'a chave antiga nao abre o valor novo');
    }

    public function testResealEncryptsLegacyClearValue(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->reseal('segredo-legivel-0123456789', SecretBox::CTX_WEBHOOK);

        self::assertIsString($sealed);
        self::assertStringStartsWith(SecretBox::PREFIX, (string)$sealed);
        self::assertSame('segredo-legivel-0123456789', $box->open((string)$sealed, SecretBox::CTX_WEBHOOK));
    }

    public function testLongestWebhookSecretFitsTheColumn(): void {
        $box = new SecretBox(SecretBox::generateKey());

        // 64 caracteres de 4 bytes: o pior caso que a tela aceita.
        $sealed = $box->seal(str_repeat("\u{1F512}", 64), SecretBox::CTX_WEBHOOK);

        self::assertLessThanOrEqual(\Reamur\Admin\Model\Setting\Webhook::SECRET_COLUMN_SIZE, strlen($sealed));
        self::assertGreaterThan(64, strlen($box->seal(str_repeat('a', 16), SecretBox::CTX_WEBHOOK)), 'nem o menor segredo cabe na coluna antiga');
    }

    public function testPeerConfigMustCarryTheSameKey(): void {
        $key   = SecretBox::generateKey();
        $other = SecretBox::generateKey();

        $cases = [
            SecretBox::PEER_SAME       => "define('REAMUR_SECRET_KEY', '" . $key . "');",
            SecretBox::PEER_DIFFERENT  => "define('REAMUR_SECRET_KEY', '" . $other . "');",
            SecretBox::PEER_MISSING    => "define('DB_PREFIX', 'rms_');",
        ];

        foreach ($cases as $expected => $body) {
            $box = new SecretBox($key, '', $this->configFile($body));

            self::assertSame($expected, $box->peerState());
            self::assertSame($expected === SecretBox::PEER_SAME, $box->canSeal(), $expected);

            $out = $box->seal('segredo-0123456789abcdef', self::CTX);
            self::assertSame($expected === SecretBox::PEER_SAME, SecretBox::isSealed($out), $expected);
        }

        $unreadable = new SecretBox($key, '', sys_get_temp_dir() . '/s9-nao-existe-' . bin2hex(random_bytes(4)) . '.php');
        self::assertSame(SecretBox::PEER_UNREADABLE, $unreadable->peerState());
        self::assertFalse($unreadable->canSeal());
        self::assertSame('segredo-0123456789abcdef', $unreadable->seal('segredo-0123456789abcdef', self::CTX));

        self::assertSame(SecretBox::PEER_NONE, (new SecretBox($key))->peerState());
        self::assertTrue((new SecretBox($key))->canSeal());
    }

    public function testPeerMismatchStopsWritingButNotReading(): void {
        $key    = SecretBox::generateKey();
        $sealed = (new SecretBox($key))->seal('token-verdadeiro-0123456789', self::CTX);
        $box    = new SecretBox($key, '', $this->configFile("define('DB_PREFIX', 'rms_');"));

        self::assertSame('token-verdadeiro-0123456789', $box->open($sealed, self::CTX));
        self::assertNull($box->reseal('legivel-0123456789abcdef', self::CTX));
    }

    public function testInvalidPeerKeyCountsAsDifferent(): void {
        $key = SecretBox::generateKey();
        $box = new SecretBox($key, '', $this->configFile("define('REAMUR_SECRET_KEY', 'frase qualquer');"));

        self::assertSame(SecretBox::PEER_DIFFERENT, $box->peerState());
    }

    public function testReadKeyFromConfigIgnoresCommentsAndTheOldKeyLine(): void {
        $key = SecretBox::generateKey();

        self::assertSame($key, SecretBox::readKeyFromConfig($this->configFile("define(  \"REAMUR_SECRET_KEY\" ,\n   \"" . $key . "\"  );")));
        self::assertSame('', SecretBox::readKeyFromConfig($this->configFile("// define('REAMUR_SECRET_KEY', '" . $key . "');")));
        self::assertSame('', SecretBox::readKeyFromConfig($this->configFile("# define('REAMUR_SECRET_KEY', '" . $key . "');")));
        self::assertSame('', SecretBox::readKeyFromConfig($this->configFile("/* define('REAMUR_SECRET_KEY', '" . $key . "'); */")));
        self::assertSame('', SecretBox::readKeyFromConfig($this->configFile("define('REAMUR_SECRET_KEY_OLD', '" . $key . "');")));
        self::assertSame($key, SecretBox::readKeyFromConfig($this->configFile(
            "define('REAMUR_SECRET_KEY_OLD', 'antiga');\n// define('REAMUR_SECRET_KEY', 'comentada');\ndefine('REAMUR_SECRET_KEY', '" . $key . "');"
        )));
        self::assertNull(SecretBox::readKeyFromConfig(sys_get_temp_dir() . '/s9-nao-existe.php'));
    }

    public function testKeyDoesNotLeakThroughDumps(): void {
        $key = SecretBox::generateKey();
        $raw = (string)SecretBox::decodeKey($key);
        $box = new SecretBox($key, SecretBox::generateKey());

        ob_start();
        var_dump($box);
        $dump = (string)ob_get_clean() . print_r($box, true);

        self::assertStringNotContainsString($key, $dump);
        self::assertStringNotContainsString($raw, $dump);
        self::assertStringContainsString('[oculta]', $dump);
    }

    public function testSettingContextsAreDistinctPerKey(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('senha-smtp-0123456789', SecretBox::settingContext('mail_smtp_password'));

        self::assertSame('senha-smtp-0123456789', $box->open($sealed, SecretBox::settingContext('mail_smtp_password')));
        self::assertSame('', $box->open($sealed, SecretBox::settingContext('mp_client_secret')));
        self::assertSame(['mp_client_secret', 'mp_webhook_secret', 'mail_smtp_password'], SecretBox::SETTING_KEYS);
    }
}
