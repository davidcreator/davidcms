<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * ADR 0088 (S4): a URL pedida não corresponde a nada chamável — rota, método
 * ou argumentos. É o ÚNICO sinal que o Router traduz em 404. Qualquer outra
 * exceção é falha do sistema e vira 500.
 *
 * Estende \Exception: quem já capturava \Exception em volta de
 * Action::execute() continua funcionando, e as mensagens não mudaram.
 */
namespace Reamur\System\Engine;

final class NotFoundException extends \Exception {
}
