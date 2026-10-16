<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Classe base de todo controller (Arquitetura-MVCL §6).
 * Serviços e models chegam via propriedade mágica a partir do Registry:
 * $this->request · $this->load · $this->model_grupo_modulo · ...
 */
namespace Reamur\System\Engine;

class Controller {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado (load, request, model_*...) */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }
}
