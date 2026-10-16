<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Snapshot da requisição HTTP (Arquitetura-MVCL §4).
 * Uso: $this->request->get['route'] ?? '' · $this->request->post['campo'] ?? ''
 */
namespace Reamur\System\Library;

final class Request {
    /** @var array<string, mixed> */
    public array $get    = [];
    /** @var array<string, mixed> */
    public array $post   = [];
    /** @var array<string, mixed> */
    public array $cookie = [];
    /** @var array<string, mixed> */
    public array $files  = [];
    /** @var array<string, mixed> */
    public array $server = [];

    public function __construct() {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->cookie = $_COOKIE;
        $this->files  = $_FILES;
        $this->server = $_SERVER;
    }
}
