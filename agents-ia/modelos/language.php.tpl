<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * TEMPLATE: arquivo de language.
 * Destino: dev/{app}/language/{{en-us|pt-br|es-eu}}/{{route}}.php
 * REGRA: texto SOMENTE aqui — controllers e templates usam as chaves.
 * REGRA (ADR 0001): en-us é o idioma padrão; criar en-us completo e anotar pendência pt-br/es-eu quando não houver tradução.
 */

// Heading
$_['heading_title']    = '{{HEADING_TITLE}}';

// Text
$_['text_success']     = 'Success: You have modified {{ENTITY_NAME}}!';
$_['text_list']        = '{{ENTITY_NAME}} List';
$_['text_form']        = '{{ENTITY_NAME}} Form';
$_['text_add']         = 'Add {{ENTITY_NAME}}';
$_['text_edit']        = 'Edit {{ENTITY_NAME}}';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_yes']         = 'Yes';
$_['text_no']          = 'No';

// Column
$_['column_name']      = 'Name';
$_['column_status']    = 'Status';
$_['column_sort_order'] = 'Sort Order';
$_['column_date_added'] = 'Date Added';
$_['column_action']    = 'Action';

// Entry
$_['entry_name']       = 'Name';
$_['entry_description'] = 'Description';
$_['entry_image']      = 'Image';
$_['entry_status']     = 'Status';
$_['entry_sort_order'] = 'Sort Order';

// Button
$_['button_save']      = 'Save';
$_['button_back']      = 'Back';
$_['button_add']       = 'Add New';
$_['button_delete']    = 'Delete';
$_['button_filter']    = 'Filter';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify {{ENTITY_NAME}}!';
$_['error_name']       = 'Name must be between 2 and 255 characters!';
$_['error_warning']    = 'Warning: Please check the form carefully for errors!';
