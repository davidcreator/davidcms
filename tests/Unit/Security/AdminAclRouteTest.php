<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Startup\Login as AdminStartupLogin;
use Reamur\Admin\Controller\Startup\Permission as AdminStartupPermission;
use Reamur\System\Engine\Action;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Url;
use Reamur\System\Library\User;

/**
 * S1 (ADR 0087): a ACL do admin e o dispatcher precisam ler a rota do mesmo
 * jeito. Antes, 'user/us..er' era checada como 'user/us' (arquivo inexistente,
 * liberada) e executada como 'user/user'.
 *
 * No bootstrap de teste DIR_APPLICATION aponta para dev/catalog/, entao a
 * "tela restrita" usada aqui e 'common/home', que existe la.
 */
final class AdminAclRouteTest extends TestCase {
    private const RESTRICTED = 'common/home';
    private const DENIED     = 'Location: https://example.test/admin/index.php?route=error/permission';

    /** @return array<string, array{0: string}> */
    public static function obfuscatedRoutes(): array {
        return [
            'ponto duplo no meio'        => ['common/ho..me'],
            'ponto duplo no inicio'      => ['..common/home'],
            'ponto duplo apos a barra'   => ['common/..home'],
            'ponto duplo no grupo'       => ['com..mon/home'],
            'ponto duplo antes da barra' => ['common../home'],
            'traversal unix'             => ['common/ho../me'],
            'traversal windows'          => ['common/ho..\\me'],
            'com metodo'                 => ['common/ho..me.index'],
            'com argumento'              => ['common/ho..me|1'],
            'metodo e argumento'         => ['common/ho..me.index|1'],
            'ponto duplo no argumento'   => ['common/home|..'],
            'tres pontos'                => ['common/ho...me'],
            'com espacos em volta'       => ['  common/ho..me  '],
        ];
    }

    #[DataProvider('obfuscatedRoutes')]
    public function testObfuscatedRouteNeverReachesARestrictedScreen(string $route): void {
        $headers = $this->runPermission($route, []);
        $action  = new Action($route);

        // A falha era exatamente esta combinacao: ACL libera E o dispatcher executa.
        self::assertFalse(
            $headers === [] && $action->isCallable(),
            sprintf('ACL liberou "%s" e o dispatcher executaria "%s.%s"', $route, $action->getRoute(), $action->getMethod())
        );
    }

    #[DataProvider('obfuscatedRoutes')]
    public function testRouteWithDoubleDotIsNotRepaired(string $route): void {
        $action = new Action($route);

        self::assertSame('', $action->getRoute());
        self::assertFalse($action->isCallable());
        self::assertSame(['route' => '', 'method' => 'index', 'args' => []], Action::parse($route));
    }

    public function testRestrictedScreenIsDeniedWithoutGrantAndOpenWithIt(): void {
        self::assertTrue((new Action(self::RESTRICTED))->isCallable(), 'pre-condicao: a rota existe');

        self::assertSame([self::DENIED], $this->runPermission(self::RESTRICTED, []));
        self::assertSame([], $this->runPermission(self::RESTRICTED, [self::RESTRICTED]));
    }

    public function testMethodAndArgumentsInheritTheGrantOfTheController(): void {
        foreach ([self::RESTRICTED . '.index', self::RESTRICTED . '.index|3', self::RESTRICTED . '|3'] as $route) {
            self::assertSame([self::DENIED], $this->runPermission($route, []), $route);
            self::assertSame([], $this->runPermission($route, [self::RESTRICTED]), $route);
        }
    }

    public function testGrantOfAnotherScreenDoesNotOpenTheRestrictedOne(): void {
        self::assertSame([self::DENIED], $this->runPermission(self::RESTRICTED, ['common/footer']));
    }

    public function testStrippedCharactersAreCheckedAsTheRouteThatRuns(): void {
        // Action ainda descarta caracteres fora da gramatica; a ACL tem de ver o resultado.
        foreach (['common/ho-me', 'common/ho me', "common/ho\0me", 'common/ho%me'] as $route) {
            $action = new Action($route);

            self::assertSame(self::RESTRICTED, $action->getRoute(), $route);
            self::assertSame([self::DENIED], $this->runPermission($route, []), $route);
        }
    }

    public function testUnknownRouteIsLeftForTheDispatcherNotFound(): void {
        foreach (['common/nao_existe', 'nada', 'common/home._privado'] as $route) {
            self::assertFalse((new Action($route))->isCallable(), $route);
            self::assertSame([], $this->runPermission($route, []), $route);
        }
    }

    public function testBootstrapAllowlistStillOpensWithEmptyAcl(): void {
        foreach (['common/dashboard', 'common/login', 'common/logout', 'user/profile', 'setting/appearance', 'error/permission'] as $route) {
            self::assertSame([], $this->runPermission($route, []), $route);
        }
    }

    public function testRouteComesFromConfigBeforeTheQueryString(): void {
        // Producao: o framework grava a rota resolvida em `_route`; a query nao manda.
        $headers = $this->runPermission(self::RESTRICTED, [], ['route' => 'common/dashboard']);

        self::assertSame([self::DENIED], $headers);
    }

    public function testQueryStringIsUsedWhenConfigHasNoRoute(): void {
        self::assertSame([self::DENIED], $this->runPermission('', [], ['route' => self::RESTRICTED]));
        self::assertSame([], $this->runPermission('', [], ['route' => 'common/ho..me']));
        self::assertFalse((new Action('common/ho..me'))->isCallable());
    }

    /**
     * startup/login compara a rota CRUA com as duas rotas publicas. Fica assim
     * de proposito (ADR 0087): normalizar ali abriria 'common/login.metodo' e
     * 'common/log-in' sem sessao.
     * @return array<string, array{0: string}>
     */
    public static function routesThatNeedASession(): array {
        return [
            'tela comum'                  => ['common/home'],
            'ponto duplo'                 => ['common/lo..gin'],
            'caractere descartado'        => ['common/log-in'],
            'metodo do controller publico' => ['common/login.index'],
            'argumento'                   => ['common/login|1'],
            'espaco'                      => [' common/login'],
            'maiusculas'                  => ['Common/Login'],
            'barra final'                 => ['common/login/'],
            'logout com metodo'           => ['common/logout.index'],
        ];
    }

    #[DataProvider('routesThatNeedASession')]
    public function testLoginWallSendsEveryNonExactRouteToTheLoginForm(string $route): void {
        self::assertSame(
            ['Location: https://example.test/admin/index.php?route=common/login'],
            $this->runLogin($route, false)
        );
    }

    public function testLoginWallOpensOnlyTheTwoExactPublicRoutes(): void {
        self::assertSame([], $this->runLogin('common/login', false));
        self::assertSame([], $this->runLogin('common/logout', false));
        // Com sessao: o formulario de login leva ao painel, o resto passa.
        self::assertSame(['Location: https://example.test/admin/index.php?route=common/dashboard'], $this->runLogin('common/login', true));
        self::assertSame([], $this->runLogin('common/logout', true));
        self::assertSame([], $this->runLogin('common/home', true));
    }

    public function testParseKeepsTheRouteGrammar(): void {
        self::assertSame(['route' => 'user/user', 'method' => 'index', 'args' => []], Action::parse('user/user'));
        self::assertSame(['route' => 'user/user', 'method' => 'save', 'args' => []], Action::parse('user/user.save'));
        self::assertSame(['route' => 'install/step_3', 'method' => 'save', 'args' => ['draft', '2']], Action::parse(' install/step_3.save|draft|2 '));
        self::assertSame(['route' => 'design/theme', 'method' => 'canvas', 'args' => ['1']], Action::parse('design/theme.canvas|1'));
        self::assertSame(['route' => '', 'method' => 'index', 'args' => []], Action::parse(''));

        $action = new Action('design/theme.canvas|1');
        self::assertSame(['design/theme', 'canvas', ['1']], [$action->getRoute(), $action->getMethod(), $action->getArgs()]);
    }

    /**
     * Roda a pre-acao real com um usuario logado cujo grupo tem so $access.
     *
     * @param list<string>          $access
     * @param array<string, string> $query
     * @return array<int, string> cabecalhos da resposta ([] = liberado)
     */
    private function runPermission(string $configRoute, array $access, array $query = []): array {
        [$registry, $response] = $this->adminRegistry($configRoute, $query, true, $access);

        (new AdminStartupPermission($registry))->index();

        return $response->getHeaders();
    }

    /** @return array<int, string> cabecalhos da resposta ([] = passou pela muralha) */
    private function runLogin(string $configRoute, bool $logged): array {
        [$registry, $response] = $this->adminRegistry($configRoute, [], $logged, []);

        (new AdminStartupLogin($registry))->index();

        return $response->getHeaders();
    }

    /**
     * @param array<string, string> $query
     * @param list<string>          $access
     * @return array{0: Registry, 1: Response}
     */
    private function adminRegistry(string $configRoute, array $query, bool $logged, array $access): array {
        $registry = new Registry();
        $config   = new Config();
        $request  = new Request();
        $response = new Response();

        $config->set('site_url', 'https://example.test/admin/');
        $config->set('action_default', 'common/dashboard');

        if ($configRoute !== '') {
            $config->set('_route', $configRoute);
        }

        $request->get = $query;

        $registry->set('config', $config);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('url', new Url('https://example.test/admin/'));
        $registry->set('load', new Loader($registry));

        $user = new User($registry);
        (new \ReflectionProperty(User::class, 'logged'))->setValue($user, $logged);
        (new \ReflectionProperty(User::class, 'user_id'))->setValue($user, $logged ? 7 : 0);
        (new \ReflectionProperty(User::class, 'permissions'))->setValue($user, ['access' => $access, 'modify' => []]);
        $registry->set('user', $user);

        return [$registry, $response];
    }
}
