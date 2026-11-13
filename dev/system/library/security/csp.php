<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\System\Library\Security;

/**
 * Content-Security-Policy e Strict-Transport-Security (ADR 0093, S8).
 *
 * A política diz ao navegador de onde a página pode carregar e executar
 * coisas. Com ela aplicada, um HTML injetado por uma falha de XSS não roda:
 * script embutido, atributo on*, script de outro site e eval são recusados.
 *
 * Scripts: só arquivos do próprio site, mais os blocos <script> que o
 * servidor escreveu e marcou com o nonce desta resposta
 * (`<script nonce="{{ csp_nonce }}">`). O nonce muda a cada requisição.
 * Não existe 'unsafe-eval' nem 'unsafe-inline' para scripts, com uma exceção
 * declarada: allowInlineScripts(), para a tela do editor antigo de posts.
 *
 * Estilos embutidos continuam permitidos: o tema publica as cores por
 * atributo style e as telas usam style="" em dezenas de pontos.
 *
 * Classe pura: não lê config, constantes nem $_SERVER. Quem monta é o
 * framework.php, com os valores do config/default.php.
 */
final class Csp {
    public const MODE_ENFORCE = 'enforce';
    public const MODE_REPORT  = 'report';
    public const MODE_OFF     = 'off';

    /** 180 dias. */
    public const HSTS_DEFAULT_MAX_AGE = 15552000;

    /** Diretivas que REAMUR_CSP_SOURCES pode ampliar. */
    public const EXTRA_DIRECTIVES = [
        'script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'media-src', 'frame-src', 'form-action',
    ];

    /** Incorporação de vídeo que o renderizador de blocos gera (block_renderer.php). */
    private const VIDEO_FRAMES = ['https://www.youtube-nocookie.com', 'https://player.vimeo.com'];

    private string $app;

    private string $mode;

    /** @var array<string, list<string>> */
    private array $extra;

    private ?string $nonce = null;

    private bool $inlineScripts = false;

    /**
     * @param string $app   'catalog' ou 'admin'; qualquer outro (instalador) não recebe política
     * @param mixed  $mode  enforce | report | off; valor desconhecido vale enforce
     * @param mixed  $extra origens adicionais por diretiva (ver sources()); só o site público as usa
     */
    public function __construct(string $app, mixed $mode = self::MODE_ENFORCE, mixed $extra = []) {
        $this->app   = strtolower($app);
        $this->mode  = self::mode($mode);
        $this->extra = $this->app === 'catalog' ? self::sources($extra) : [];
    }

    /**
     * Normaliza o modo. Só os dois valores que afrouxam são reconhecidos;
     * qualquer outra coisa (erro de digitação, tipo errado) mantém a política aplicada.
     */
    public static function mode(mixed $value): string {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, [self::MODE_REPORT, self::MODE_OFF], true) ? $value : self::MODE_ENFORCE;
    }

    public function getMode(): string {
        return $this->mode;
    }

    /**
     * Origens adicionais vindas do config.php, já conferidas.
     *
     * Aceita só endereço https de um site: `https://exemplo.com`,
     * `https://*.exemplo.com`, com porta e caminho opcionais. Palavras-chave
     * ('unsafe-inline', 'unsafe-eval', 'self'...), esquemas soltos (`https:`,
     * `data:`), `*` e http são ignorados: uma linha de configuração não
     * consegue desligar a proteção contra script injetado.
     *
     * @return array<string, list<string>>
     */
    public static function sources(mixed $raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];

        foreach ($raw as $directive => $list) {
            $directive = is_string($directive) ? strtolower(trim($directive)) : '';

            if (!in_array($directive, self::EXTRA_DIRECTIVES, true)) {
                continue;
            }

            if (is_string($list)) {
                $list = preg_split('/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }

            if (!is_array($list)) {
                continue;
            }

            foreach ($list as $source) {
                if (!is_string($source)) {
                    continue;
                }

                $source = trim($source);

                if (preg_match('~^https://(?:\*\.)?[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._\~!$&()*+=:@%/-]*)?$~Di', $source) !== 1) {
                    continue;
                }

                if (!in_array($source, $clean[$directive] ?? [], true)) {
                    $clean[$directive][] = $source;
                }
            }
        }

        return $clean;
    }

    /** Nonce desta resposta, para `<script nonce="...">`. Gerado uma vez por requisição. */
    public function nonce(): string {
        if ($this->nonce === null) {
            $this->nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }

        return $this->nonce;
    }

    /**
     * Exceção para UMA tela: o CKEditor 4 (posts antigos, não convertidos em
     * blocos) monta a própria interface com atributos on* e não funciona com
     * a regra de nonce. Vale só para a resposta em que foi chamado. Depois de
     * chamar, reenvie o cabeçalho: `$this->response->setHeader($this->csp->header())`.
     */
    public function allowInlineScripts(): void {
        $this->inlineScripts = true;
    }

    public function allowsInlineScripts(): bool {
        return $this->inlineScripts;
    }

    /** @return array<string, list<string>> */
    public function directives(): array {
        $script = $this->inlineScripts
            ? ["'self'", "'unsafe-inline'"]
            : ["'self'", "'nonce-" . $this->nonce() . "'"];

        if ($this->app === 'catalog') {
            $d = [
                'default-src'     => ["'self'"],
                'script-src'      => $script,
                'style-src'       => ["'self'", "'unsafe-inline'"],
                'img-src'         => ["'self'", 'data:', 'blob:', 'https:'],
                'font-src'        => ["'self'", 'data:'],
                // blob: o GLTFLoader do Three.js lê texturas embutidas no modelo por fetch(blob:).
                'connect-src'     => ["'self'", 'blob:'],
                'media-src'       => ["'self'", 'https:'],
                'frame-src'       => self::VIDEO_FRAMES,
                'object-src'      => ["'none'"],
                'base-uri'        => ["'self'"],
                'form-action'     => ["'self'"],
                'frame-ancestors' => ["'self'"],
            ];
        } elseif ($this->app === 'admin') {
            $d = [
                'default-src'     => ["'self'"],
                'script-src'      => $script,
                // reamurcms.css importa a fonte do painel do Google Fonts.
                'style-src'       => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
                'img-src'         => ["'self'", 'data:', 'blob:', 'https:'],
                'font-src'        => ["'self'", 'data:', 'https://fonts.gstatic.com'],
                'connect-src'     => ["'self'", 'blob:'],
                'media-src'       => ["'self'", 'blob:', 'https:'],
                'frame-src'       => array_merge(["'self'"], self::VIDEO_FRAMES),
                'object-src'      => ["'none'"],
                'base-uri'        => ["'self'"],
                // "Conectar Mercado Pago" é um POST que redireciona para a autorização do MP.
                'form-action'     => ["'self'", 'https://auth.mercadopago.com.br'],
                'frame-ancestors' => ["'self'"],
            ];
        } else {
            // Instalador: sem política. Roda uma vez, antes de o site existir, e
            // as telas dele (script embutido, fonte externa) não foram adaptadas.
            return [];
        }

        foreach ($this->extra as $directive => $sources) {
            if (!isset($d[$directive])) {
                continue;
            }

            foreach ($sources as $source) {
                if (!in_array($source, $d[$directive], true)) {
                    $d[$directive][] = $source;
                }
            }
        }

        return $d;
    }

    public function policy(): string {
        $parts = [];

        foreach ($this->directives() as $name => $sources) {
            $parts[] = $name . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }

    /** Linha de cabeçalho pronta para Response::setHeader(); '' quando a política está desligada. */
    public function header(): string {
        if ($this->mode === self::MODE_OFF || $this->directives() === []) {
            return '';
        }

        $name = $this->mode === self::MODE_REPORT ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';

        return $name . ': ' . $this->policy();
    }

    /**
     * Strict-Transport-Security: depois de uma visita por HTTPS, o navegador
     * recusa HTTP neste domínio pelo prazo informado.
     *
     * Sai só quando o endereço do site no config.php (HTTP_SERVER) é https:
     * é a declaração de quem instalou de que o site inteiro vive em HTTPS, e
     * vale mesmo quando a CDN esconde o HTTPS do PHP. Uma visita HTTPS a um
     * site configurado como http não liga o HSTS. Nunca para localhost nem
     * para endereço IP. Sem includeSubDomains e sem preload: só o domínio do
     * site, e dá para voltar atrás (REAMUR_HSTS_MAX_AGE = 0 e esperar o prazo).
     *
     * @param mixed $maxAge segundos; 0 ou negativo desliga; valor não numérico usa o padrão
     *
     * @return string linha de cabeçalho ou ''
     */
    public static function hsts(string $siteUrl, mixed $maxAge = self::HSTS_DEFAULT_MAX_AGE): string {
        $maxAge = is_numeric($maxAge) ? (int)$maxAge : self::HSTS_DEFAULT_MAX_AGE;

        if ($maxAge <= 0) {
            return '';
        }

        $parts  = parse_url(trim($siteUrl));
        $scheme = strtolower((string)(is_array($parts) ? ($parts['scheme'] ?? '') : ''));
        $host   = strtolower(trim((string)(is_array($parts) ? ($parts['host'] ?? '') : ''), '[]'));

        if ($scheme !== 'https') {
            return '';
        }

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return '';
        }

        // Teto de 2 anos: acima disso é quase sempre erro de digitação.
        return 'Strict-Transport-Security: max-age=' . min($maxAge, 63072000);
    }
}
