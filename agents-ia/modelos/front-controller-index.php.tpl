<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * TEMPLATE: front controller canônico (Contrato-Bootstrap §2).
 * A ÚNICA linha que muda entre aplicações é o valor de APPLICATION.
 * Destinos válidos: dev/catalog/index.php · dev/admin/index.php · dev/install/index.php
 */

// 1. Versão do núcleo (bump somente em release — ver Contrato-Bootstrap §5.3)
define('REAMUR_VERSION', '1.0.0');

// 2. Aplicação em execução: 'Catalog' | 'Admin' | 'Install'
define('APPLICATION', '{{APPLICATION}}');

// 3. Configuração da instalação (gerada pelo wizard install/)
if (is_file('config.php')) {
    require_once('config.php');
}

// 4. Instalação pendente?
// TEMPLATE (catalog/admin): descomentar o bloco abaixo.
// if (!defined('DIR_APPLICATION')) {
//     header('Location: install/index.php');
//     exit();
// }
// TEMPLATE (install): NUNCA incluir o bloco de redirect acima. Em seu lugar,
// autodefinir DIR_* + HTTP_* do Contrato §1.1 (ver dev/install/index.php —
// Adendo F1-4): o wizard roda sem config.php porque ele próprio o cria.

// 5. Bootstrap do núcleo — ordem inviolável, nada entre estes requires
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'framework.php');

// 6. A partir daqui o fluxo segue o ciclo MVCL (docs/Arquitetura-MVCL.md §4)
