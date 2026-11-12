<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Action;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\NotFoundException;
use Reamur\System\Engine\Registry;
use Reamur\System\Engine\Router;
use Reamur\System\Library\Response;
use Reamur\Test\Controller\Common\Home;
use Reamur\Test\Controller\Error\Not_found;

require_once dirname(__DIR__, 2) . '/fixtures/dispatch_controllers.php';

/**
 * S4 (ADR 0088): "a URL nao corresponde a uma tela" responde 404;
 * "a tela existe e falhou" responde 500. Antes as duas respondiam 404.
 */
final class ErrorDispatchTest extends TestCase {
    private const STATUS_500 = 'HTTP/1.1 500 Internal Server Error';
    private const SECURITY   = 'X-Frame-Options: SAMEORIGIN';

    private Registry $registry;
    private Response $response;
    private Config $config;
    /** @var object{lines: array<int, string>} */
    private object $log;
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        $_SERVER['REQUEST_URI'] = '/pagina-pedida?x=1';

        Home::$calls       = [];
        Not_found::$broken = false;

        $this->response = new Response();
        $this->response->addHeader(self::SECURITY);
        $this->response->addHeader('Content-Type: text/html; charset=utf-8');

        $this->config = new Config();
        $this->config->set('error_display', false);
        $this->config->set('language_directory', 'pt-br');
        $this->config->set('config_name', 'Meu Site');
        $this->config->set('site_url', 'https://example.test/');

        $this->log = new class {
            /** @var array<int, string> */
            public array $lines = [];

            public function write(string $message): void {
                $this->lines[] = $message;
            }
        };

        $this->registry = new Registry();
        $this->registry->set('response', $this->response);
        $this->registry->set('config', $this->config);
        $this->registry->set('log', $this->log);
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
    }

    private function dispatch(string $route): void {
        (new Router($this->registry))->dispatch(new Action($route), new Action('error/not_found'));
    }

    // ── Action::prepare: o que conta como "nao existe" ────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function routesThatDoNotExist(): array {
        return [
            'arquivo inexistente'            => ['nao/existe', 'Route not found'],
            'rota vazia'                     => ['', 'Route not found'],
            'metodo com sublinhado'          => ['common/home._segredo', 'Route not found'],
            'arquivo sem a classe esperada'  => ['common/footer', 'Controller class not found'],
            'metodo inexistente'             => ['common/home.naoexiste', 'Method not callable'],
            'metodo protegido'               => ['common/home.hidden', 'Method not callable'],
            'falta argumento obrigatorio'    => ['common/home.req', 'Arguments do not match'],
            'argumento da URL onde cabe array' => ['common/home.needs|abc', 'Arguments do not match'],
            'argumento da URL onde cabe int' => ['common/home.num|abc', 'Arguments do not match'],
            'int mesmo com digitos'          => ['common/home.num|12', 'Arguments do not match'],
        ];
    }

    #[DataProvider('routesThatDoNotExist')]
    public function testPrepareSignalsNotFoundWithoutRunningAnything(string $route, string $message): void {
        try {
            (new Action($route))->prepare($this->registry);
            self::fail('esperava NotFoundException para ' . $route);
        } catch (NotFoundException $e) {
            self::assertStringStartsWith($message, $e->getMessage());
        }

        self::assertSame([], Home::$calls, 'nenhum metodo do controller pode ter rodado');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function routesThatExist(): array {
        return [
            'sem argumento'          => ['common/home', 'index'],
            'argumento texto'        => ['common/home.req|abc', 'req:abc'],
            'opcional omitido'       => ['common/home.opt', 'opt:padrao'],
            'opcional informado'     => ['common/home.opt|x', 'opt:x'],
            'uniao que aceita texto' => ['common/home.either|7', 'either:7'],
            'variadico vazio'        => ['common/home.rest', 'rest:'],
            'variadico cheio'        => ['common/home.rest|a|b', 'rest:a,b'],
        ];
    }

    #[DataProvider('routesThatExist')]
    public function testPrepareAcceptsRoutesThatMatchAScreen(string $route, string $expected): void {
        $call = (new Action($route))->prepare($this->registry);

        self::assertSame([], Home::$calls, 'prepare nao executa o controller');

        $call();

        self::assertSame([$expected], Home::$calls);
    }

    public function testExplicitArgumentsFromCodeAreNotFilteredAsUrlArguments(): void {
        // load->controller('rota', [...]) passa tipos de verdade: array e int valem.
        (new Action('common/home.needs'))->execute($this->registry, [['a' => 1]]);
        (new Action('common/home.num'))->execute($this->registry, [5]);

        self::assertSame(['needs', 'num'], Home::$calls);
    }

    public function testNotFoundExceptionIsStillAPlainException(): void {
        // Quem ja fazia catch (\Exception) em volta de execute() continua funcionando.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Route not found: nao/existe.index');

        (new Action('nao/existe'))->execute($this->registry);
    }

    public function testBrokenConstructorIsAFailureNotANotFound(): void {
        $this->expectException(\LogicException::class);

        (new Action('common/header'))->prepare($this->registry);
    }

    // ── Router: 404 quando a URL nao corresponde a nada ───────────────────

    public function testHealthyRouteIsUntouched(): void {
        $this->dispatch('common/home');

        self::assertSame('home ok', $this->response->getOutput());
        self::assertSame([self::SECURITY, 'Content-Type: text/html; charset=utf-8'], $this->response->getHeaders());
        self::assertSame([], $this->log->lines);
    }

    #[DataProvider('routesThatDoNotExist')]
    public function testUnknownRouteStillGoesToTheErrorRoute(string $route, string $message): void {
        $this->dispatch($route);

        self::assertSame('pagina 404 da aplicacao', $this->response->getOutput());
        self::assertContains('HTTP/1.1 404 Not Found', $this->response->getHeaders());
        self::assertNotContains(self::STATUS_500, $this->response->getHeaders());
        self::assertCount(1, $this->log->lines);
        self::assertStringStartsWith($message, $this->log->lines[0]);
        self::assertStringEndsWith(' — request: /pagina-pedida?x=1', $this->log->lines[0]);
    }

    public function testControllerCanStillChainToAnotherAction(): void {
        $this->dispatch('common/home.chain');

        self::assertSame(['chain', 'index'], Home::$calls);
        self::assertSame('home ok', $this->response->getOutput());
    }

    public function testControllerCanStillAskForTheNotFoundPage(): void {
        // O 404 deliberado dos controllers (projeto inexistente etc.) segue igual.
        $this->dispatch('common/home.missing');

        self::assertSame('pagina 404 da aplicacao', $this->response->getOutput());
        self::assertContains('HTTP/1.1 404 Not Found', $this->response->getHeaders());
    }

    public function testMissingErrorRouteAnswersAMinimal404(): void {
        (new Router($this->registry))->dispatch(new Action('nao/existe'), new Action('tambem/nao'));

        $headers = $this->response->getHeaders();

        self::assertContains('HTTP/1.1 404 Not Found', $headers);
        self::assertContains(self::SECURITY, $headers);
        self::assertStringContainsString('404', $this->response->getOutput());
        self::assertStringNotContainsString('tambem/nao', $this->response->getOutput());
    }

    // ── Router: 500 quando a tela existe e falhou ─────────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function routesThatFail(): array {
        return [
            'excecao no controller' => ['common/home.boom', 'RuntimeException: segredo-interno'],
            'aviso do PHP'          => ['common/home.warning', 'ErrorException: Undefined variable'],
            'erro de tipo'          => ['common/home.typeerror', 'TypeError: '],
            'construtor quebrado'   => ['common/header', 'LogicException: construtor quebrado'],
            'echo antes da falha'   => ['common/home.echoed', 'RuntimeException: falhou depois do echo'],
        ];
    }

    #[DataProvider('routesThatFail')]
    public function testFailingControllerAnswers500InsteadOf404(string $route, string $logged): void {
        ob_start();
        $this->dispatch($route);
        $leaked = (string)ob_get_clean();

        $headers = $this->response->getHeaders();
        $output  = $this->response->getOutput();

        self::assertSame('', $leaked, 'nada do controller que falhou pode sair direto');
        self::assertContains(self::STATUS_500, $headers);
        self::assertNotContains('HTTP/1.1 404 Not Found', $headers);
        self::assertStringNotContainsString('pagina 404 da aplicacao', $output);
        self::assertStringContainsString('<title>Erro temporário · Meu Site</title>', $output);
        self::assertStringContainsString('href="https://example.test/"', $output);

        self::assertCount(1, $this->log->lines);
        self::assertMatchesRegularExpression('/^\[([0-9A-F]{8})\] /', $this->log->lines[0]);
        self::assertStringContainsString($logged, $this->log->lines[0]);
        self::assertMatchesRegularExpression('/ em .+\.php:\d+ — request: \/pagina-pedida\?x=1$/', $this->log->lines[0]);

        // O codigo mostrado ao visitante e o mesmo da linha do log.
        preg_match('/^\[([0-9A-F]{8})\]/', $this->log->lines[0], $m);
        self::assertStringContainsString($m[1], $output);
    }

    public function testProductionPageShowsNoInternalDetail(): void {
        $this->dispatch('common/home.boom');

        $output = $this->response->getOutput();

        foreach (['segredo-interno', 'SELECT', 'rms_user', 'RuntimeException', 'dispatch_controllers', '.php', '<meia-pagina/>'] as $needle) {
            self::assertStringNotContainsString($needle, $output, $needle);
        }
    }

    public function testFailedControllerHeadersAreDiscardedAndSecurityHeadersKept(): void {
        $this->dispatch('common/home.boom');

        $headers = $this->response->getHeaders();

        self::assertContains(self::SECURITY, $headers);
        self::assertContains('Content-Type: text/html; charset=utf-8', $headers);
        self::assertContains('Cache-Control: no-store', $headers);
        self::assertContains('X-Robots-Tag: noindex', $headers);
        self::assertNotContains('Content-Type: application/xml; charset=utf-8', $headers);
        self::assertNotContains('Cache-Control: public, max-age=3600', $headers);
        self::assertCount(1, preg_grep('/^Content-Type:/i', $headers));
        self::assertCount(1, preg_grep('/^Cache-Control:/i', $headers));
    }

    public function testDevelopmentModeShowsTheDetailEscaped(): void {
        $this->config->set('error_display', true);

        $this->dispatch('common/home.boom');

        $output = $this->response->getOutput();

        self::assertContains(self::STATUS_500, $this->response->getHeaders());
        self::assertStringContainsString('RuntimeException: segredo-interno &lt;b&gt;x&lt;/b&gt;', $output);
        self::assertStringNotContainsString('<b>x</b>', $output);
    }

    public function testMissingConfigNeverShowsTheDetail(): void {
        // Padrao seguro: sem config legivel, nada de detalhe na tela.
        $this->registry->set('config', null);

        $this->dispatch('common/home.boom');

        self::assertContains(self::STATUS_500, $this->response->getHeaders());
        self::assertStringNotContainsString('segredo-interno', $this->response->getOutput());
        self::assertStringContainsString('<title>Temporary error</title>', $this->response->getOutput());
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function jsonCallers(): array {
        return [
            'Accept JSON'      => [['HTTP_ACCEPT' => 'application/json']],
            'XMLHttpRequest'   => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']],
            'fetch do painel'  => [['HTTP_ACCEPT' => 'application/json, text/plain, */*']],
        ];
    }

    /** @param array<string, string> $server */
    #[DataProvider('jsonCallers')]
    public function testScriptCallersGetJsonInsteadOfAPage(array $server): void {
        $_SERVER = array_merge($_SERVER, $server);

        $this->dispatch('common/home.boom');

        $payload = json_decode($this->response->getOutput(), true, 8, JSON_THROW_ON_ERROR);

        self::assertContains(self::STATUS_500, $this->response->getHeaders());
        self::assertContains('Content-Type: application/json; charset=utf-8', $this->response->getHeaders());
        self::assertSame(['success', 'error', 'ref'], array_keys($payload));
        self::assertFalse($payload['success']);
        self::assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $payload['ref']);
        self::assertStringNotContainsString('segredo-interno', $this->response->getOutput());
    }

    public function testBrowserNavigationKeepsGettingHtml(): void {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $this->dispatch('common/home.boom');

        self::assertStringStartsWith('<!DOCTYPE html>', $this->response->getOutput());
    }

    public function testFailureInsideTheErrorRouteDoesNotLoop(): void {
        Not_found::$broken = true;

        $this->dispatch('nao/existe');

        self::assertContains(self::STATUS_500, $this->response->getHeaders());
        self::assertCount(2, $this->log->lines, 'uma linha do 404 e uma da falha');
        self::assertStringContainsString('a propria pagina 404 falhou', $this->log->lines[1]);
    }

    public function testDirectOutputOfAHealthyControllerStillGoesOut(): void {
        ob_start();
        $this->dispatch('common/home.printed');
        $printed = (string)ob_get_clean();

        self::assertSame('saida direta', $printed);
        self::assertSame([], $this->log->lines);
    }

    public function testBrokenLogDoesNotPreventTheErrorResponse(): void {
        $this->registry->set('log', new class {
            public function write(string $message): void {
                throw new \RuntimeException('disco cheio');
            }
        });

        $this->dispatch('common/home.boom');

        self::assertContains(self::STATUS_500, $this->response->getHeaders());
    }

    // ── Response::reset ───────────────────────────────────────────────────

    public function testResponseResetClearsHeadersAndOutput(): void {
        $this->response->setOutput('antigo');
        $this->response->reset(['X-Um: 1']);

        self::assertSame(['X-Um: 1'], $this->response->getHeaders());
        self::assertSame('', $this->response->getOutput());

        $this->response->reset();

        self::assertSame([], $this->response->getHeaders());
    }
}
