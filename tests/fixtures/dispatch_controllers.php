<?php declare(strict_types=1);
/**
 * Controllers de mentira para os testes do despacho (ADR 0088).
 *
 * No bootstrap de teste APPLICATION vale 'Test' e DIR_APPLICATION aponta para
 * dev/catalog/: a rota 'common/home' encontra o ARQUIVO real do catalog e a
 * CLASSE Reamur\Test\Controller\Common\Home, declarada aqui. Assim os testes
 * exercitam Action e Router de verdade sem banco, template ou tema.
 */
namespace Reamur\Test\Controller\Common;

use Reamur\System\Engine\Action;
use Reamur\System\Engine\Registry;

final class Home {
    /** @var array<int, string> */
    public static array $calls = [];

    public function __construct(private Registry $registry) {}

    public function index(): void {
        self::$calls[] = 'index';
        $this->registry->get('response')->setOutput('home ok');
    }

    public function boom(): void {
        self::$calls[] = 'boom';
        $response = $this->registry->get('response');
        $response->addHeader('Content-Type: application/xml; charset=utf-8');
        $response->addHeader('Cache-Control: public, max-age=3600');
        $response->setOutput('<meia-pagina/>');

        throw new \RuntimeException('segredo-interno <b>x</b> SELECT * FROM rms_user');
    }

    public function echoed(): void {
        echo 'meia pagina escrita direto';

        throw new \RuntimeException('falhou depois do echo');
    }

    public function printed(): void {
        echo 'saida direta';
    }

    public function warning(): void {
        throw new \ErrorException('Undefined variable $x', 0, E_WARNING, __FILE__, __LINE__);
    }

    public function typeerror(): void {
        strlen(...[[]]);
    }

    public function chain(): Action {
        self::$calls[] = 'chain';

        return new Action('common/home.index');
    }

    public function missing(): Action {
        return new Action('error/not_found');
    }

    public function needs(array $data): void {
        self::$calls[] = 'needs';
    }

    public function num(int $id): void {
        self::$calls[] = 'num';
    }

    public function req(string $slug): void {
        self::$calls[] = 'req:' . $slug;
    }

    public function opt(string $slug = 'padrao'): void {
        self::$calls[] = 'opt:' . $slug;
    }

    public function either(int|string $id): void {
        self::$calls[] = 'either:' . $id;
    }

    public function rest(string ...$parts): void {
        self::$calls[] = 'rest:' . implode(',', $parts);
    }

    protected function hidden(): void {
        self::$calls[] = 'hidden';
    }
}

namespace Reamur\Test\Controller\Error;

use Reamur\System\Engine\Registry;

final class Not_found {
    public static bool $broken = false;

    public function __construct(private Registry $registry) {}

    public function index(): void {
        if (self::$broken) {
            throw new \RuntimeException('a propria pagina 404 falhou');
        }

        $response = $this->registry->get('response');
        $response->addHeader('HTTP/1.1 404 Not Found');
        $response->setOutput('pagina 404 da aplicacao');
    }
}

namespace Reamur\Test\Controller\Common;

use Reamur\System\Engine\Registry;

/** O construtor falha: a rota existe e está quebrada (500, não 404). */
final class Header {
    public function __construct(Registry $registry) {
        throw new \LogicException('construtor quebrado');
    }

    public function index(): void {}
}
