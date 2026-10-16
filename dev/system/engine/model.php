<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Classe base de todo model (Arquitetura-MVCL §7).
 * Mesmo acesso mágico ao Registry dos controllers: $this->db, $this->config, ...
 */
namespace Reamur\System\Engine;

class Model {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }
}
