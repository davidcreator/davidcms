<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Despacha a Action da requisição (Arquitetura-MVCL §4).
 *
 * ADR 0088 (S4) — duas respostas diferentes para duas situações diferentes:
 *   - a URL não corresponde a uma tela (rota, método ou argumentos)
 *       → rota de erro da aplicação (action_error), que responde 404;
 *   - a tela existe e falhou (exceção, aviso do PHP, banco fora do ar)
 *       → 500, com a página de erro estática e o detalhe só no log.
 * Antes as duas caíam na rota de erro: um incidente de banco respondia
 * "404 Página não encontrada" na home.
 *
 * Um controller pode retornar outra Action para encadear o fluxo.
 */
namespace Reamur\System\Engine;

use Reamur\System\Library\ErrorPage;
use Reamur\System\Library\Response;

final class Router {
    public function __construct(protected Registry $registry) {}

    public function dispatch(Action $action, Action $error): void {
        // Cabeçalhos que já estavam prontos ANTES de qualquer controller rodar
        // (os de segurança do config). Uma resposta de erro recomeça daqui.
        $response = $this->response();
        $baseline = $response !== null ? $response->getHeaders() : [];

        while ($action instanceof Action) {
            try {
                $call = $action->prepare($this->registry);
            } catch (NotFoundException $e) {
                // F6-06 (ADR 0041): o router sanitiza '.' e '-' do path
                // (resolve() → 'mainjs'), então a mensagem da exceção
                // sozinha esconde a URL original. REQUEST_URI junto,
                // mensagem continua primeiro (compatível com greps antigos).
                $this->log($e->getMessage());

                if ($action->getRoute() === $error->getRoute()) {
                    // Nem a rota de erro existe: 404 mínimo, sem depender de template.
                    $this->notFound($baseline);

                    return;
                }

                $action = $error;

                continue;
            } catch (\Throwable $e) {
                $this->fail($e, $baseline);

                return;
            }

            // O que o controller escrever DIRETO na saída (echo) fica retido
            // até ele terminar. Se falhar, o pedaço é descartado e o status
            // 500 ainda pode ser enviado; sem isso a resposta saía 200 com
            // meia página. Se terminar bem, o pedaço sai como sempre saiu.
            $level = ob_get_level();
            ob_start();

            try {
                $result = $call();
            } catch (\Throwable $e) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }

                $this->fail($e, $baseline);

                return;
            }

            while (ob_get_level() > $level) {
                ob_end_flush();
            }

            // Encadeamento: só continua se o controller devolveu outra Action
            $action = $result instanceof Action ? $result : null;
        }
    }

    /**
     * A tela existe e falhou: 500.
     *
     * @param array<int, string> $baseline
     */
    private function fail(\Throwable $e, array $baseline): void {
        $reference = ErrorPage::reference();

        $this->log('[' . $reference . '] ' . ErrorPage::describe($e));

        $config   = $this->registry->get('config');
        $response = $this->response();
        $read     = static fn (string $key, mixed $default): mixed
            => $config instanceof Config ? $config->get($key, $default) : $default;

        // Padrão seguro: sem config legível, o detalhe NÃO vai para a tela.
        $detail = $read('error_display', false) ? ErrorPage::describe($e) : null;
        $json   = ErrorPage::wantsJson($_SERVER);
        $lang   = (string)$read('language_directory', 'en-us');

        $body = $json
            ? ErrorPage::json($lang, $reference, $detail)
            : ErrorPage::html($lang, (string)$read('config_name', ''), (string)$read('site_url', '/'), $reference, $detail);

        if ($response === null) {
            return;
        }

        if (PHP_SAPI !== 'cli' && headers_sent()) {
            // O controller escreveu direto na saída antes de falhar: o status
            // já foi. Não tenta mandar cabeçalho (viraria outro erro).
            $response->reset();
            $response->setCompression(0);
            $response->setOutput($body);

            return;
        }

        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        $response->reset(array_merge(ErrorPage::withoutOverridden($baseline), ErrorPage::headers($json)));
        $response->setOutput($body);
    }

    /** @param array<int, string> $baseline */
    private function notFound(array $baseline): void {
        $response = $this->response();

        if ($response === null) {
            return;
        }

        $response->reset(array_merge(ErrorPage::withoutOverridden($baseline), [
            'HTTP/1.1 404 Not Found',
            'Content-Type: text/html; charset=utf-8',
            'Cache-Control: no-store',
        ]));
        $response->setOutput(ErrorPage::notFound());
    }

    private function response(): ?Response {
        $response = $this->registry->get('response');

        return $response instanceof Response ? $response : null;
    }

    private function log(string $message): void {
        $log = $this->registry->get('log');

        if (!$log || !method_exists($log, 'write')) {
            return;
        }

        $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        try {
            $log->write($request_uri !== '' ? $message . ' — request: ' . $request_uri : $message);
        } catch (\Throwable $e) {
            // log indisponível não pode impedir a resposta de erro
        }
    }
}
