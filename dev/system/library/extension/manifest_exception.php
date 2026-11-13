<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): o extension.json de uma extensão não pôde ser aceito.
 * Carrega TODOS os problemas encontrados (não só o primeiro), para a tela de
 * extensões mostrar a lista a quem está escrevendo a extensão.
 */
namespace Reamur\System\Library\Extension;

final class ManifestException extends \RuntimeException {
    /** @var list<string> */
    private array $problems;

    /** @param list<string> $problems */
    public function __construct(array $problems) {
        $this->problems = array_values($problems) ?: ['extension.json: invalid'];

        parent::__construct(implode('; ', $this->problems));
    }

    /** @return list<string> */
    public function problems(): array {
        return $this->problems;
    }
}
