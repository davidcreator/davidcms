<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Front controller — aplicação CATALOG (site público).
 * Especificação: docs/Contrato-Bootstrap.md §2. A ÚNICA linha específica
 * da aplicação é o valor de APPLICATION (demais apps mudam apenas ela).
 */

// 1. Versão do núcleo (bump somente em release — ver Contrato-Bootstrap §5.3)
define('REAMUR_VERSION', '1.0.0');

// 2. Aplicação em execução
define('APPLICATION', 'Catalog');

// 3. Configuração da instalação (gerada pelo wizard install/)
if (is_file('config.php')) {
    require_once('config.php');
}

// 4. Instalação pendente? → wizard
if (!defined('DIR_APPLICATION')) {
    header('Location: install/index.php');
    exit();
}

// 5. Bootstrap do núcleo — ordem inviolável, nada entre estes requires
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'framework.php');

// 6. A partir daqui o fluxo segue o ciclo MVCL (docs/Arquitetura-MVCL.md §4)
