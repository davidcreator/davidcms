<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Despacha a Action da requisição (Arquitetura-MVCL §4):
 * tenta a rota pedida; em falha, cai na rota de erro da aplicação (action_error);
 * se até a de erro faltar, responde o mínimo honesto.
 * Um controller pode retornar outra Action para encadear o fluxo.
 */
namespace Reamur\System\Engine;

final class Router {
    public function __construct(protected Registry $registry) {}

    public function dispatch(Action $action, Action $error): void {
        while ($action instanceof Action) {
            try {
                $result = $action->execute($this->registry);
            } catch (\Throwable $e) {
                $log = $this->registry->get('log');

                if ($log && method_exists($log, 'write')) {
                    // F6-06 (ADR 0041): o router sanitiza '.' e '-' do path
                    // (resolve() → 'mainjs'), então a mensagem da exceção
                    // sozinha esconde a URL original. REQUEST_URI junto,
                    // mensagem continua primeiro (compatível com greps antigos).
                    $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');

                    if ($request_uri !== '') {
                        $log->write($e->getMessage() . ' — request: ' . $request_uri);
                    } else {
                        $log->write($e->getMessage());
                    }
                }

                if ($action->getRoute() === $error->getRoute()) {
                    // Nem a rota de erro existe — resposta mínima, sem esconder a falha
                    $response = $this->registry->get('response');

                    if ($response && method_exists($response, 'setOutput')) {
                        $response->setOutput('ReamurCMS error: ' . $e->getMessage());
                    }

                    return;
                }

                $action = $error;

                continue;
            }

            // Encadeamento: só continua se o controller devolveu outra Action
            $action = $result instanceof Action ? $result : null;
        }
    }
}
