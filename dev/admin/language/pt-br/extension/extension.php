<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * extension/extension — Tela de extensões: lista da pasta, ligar, desligar, configurar (EX-01, ADR 0095; pt-BR).
 */

$_['heading_title']            = 'Extensões';
$_['text_list']                = 'Extensões encontradas na pasta de extensões deste site. Ligue, desligue e configure por aqui.';
$_['text_install_hint']        = 'Para instalar uma extensão, copie a pasta dela para o servidor (FTP ou deploy), dentro de:';
$_['text_trust_hint']          = 'Uma extensão ligada é código que roda com o mesmo alcance do próprio CMS (banco, arquivos, configurações). Só ligue extensões de origem conhecida. Enquanto uma extensão está desligada, nenhum código dela roda.';
$_['text_no_results']          = 'Nenhuma extensão na pasta ainda.';
$_['text_close']               = 'Fechar';
$_['text_enabled']             = 'Ligada';
$_['text_disabled']            = 'Desligada';
$_['text_invalid']             = 'Descrição inválida';
$_['text_incompatible']        = 'Incompatível';
$_['text_folder_missing']      = 'Pasta não encontrada';
$_['text_folder_missing_hint'] = 'Esta extensão tem dados gravados, mas a pasta dela não está mais no servidor. Nada dela roda. Devolva a pasta ou remova os dados.';
$_['text_runs_in']             = 'Roda em';
$_['text_app_catalog']         = 'site público';
$_['text_app_admin']           = 'admin';
$_['text_events']              = 'Escuta';
$_['text_no_events']           = 'nenhum evento';
$_['text_sources']             = 'Endereços de fora que pede';
$_['text_no_sources']          = 'nenhum';
$_['text_problems']            = 'O arquivo extension.json foi recusado:';
$_['text_blockers']            = 'Ainda não pode ser ligada:';
$_['text_details']             = 'O que esta extensão faz no site';
$_['text_version']             = 'v';
$_['text_author']              = 'por';
$_['text_confirm_enable']      = 'Ligar esta extensão? O código dela começa a rodar no site na hora. Só ligue extensões de origem conhecida.';
$_['text_confirm_purge']       = 'Remover tudo o que esta extensão gravou (configuração e segredos)? Não dá para desfazer.';
$_['text_off_by_config']       = 'Todas as extensões estão desligadas pela linha REAMUR_EXTENSIONS do config.php. O que aparece como ligado abaixo volta a rodar quando essa linha for removida.';
$_['text_no_folder']           = 'A pasta de extensões não existe neste servidor:';
$_['text_form']                = 'Configuração desta extensão.';
$_['text_secret_set']          = 'Valor gravado';
$_['text_secret_unset']        = 'Nenhum valor gravado';
$_['text_secret_hint']         = 'Este valor nunca é mostrado de novo. Deixe vazio para manter o que está gravado, ou digite outro para substituir.';
$_['text_secret_locked']       = 'Há um segredo gravado que a chave do config.php não abre. A extensão o recebe vazio. Digite-o de novo ou restaure a chave que o gravou.';
$_['text_secret_clear']        = 'Apagar o valor gravado';
$_['text_secret_clear_help']   = 'Só vale se o campo acima estiver vazio.';
$_['text_secret_no_key']       = 'A chave dos segredos não está no config.php (Configurações > Proteção dos segredos). Um segredo gravado agora fica legível no banco até isso ser feito.';
$_['text_select_none']         = '(nenhum)';
$_['text_required']            = 'Obrigatório';
$_['text_success_save']        = 'Configuração de "%s" gravada.';
$_['text_success_enable']      = 'Extensão "%s" ligada.';
$_['text_success_disable']     = 'Extensão "%s" desligada.';
$_['text_success_purge']       = 'Dados gravados de "%s" removidos.';
$_['column_extension']         = 'Extensão';
$_['column_status']            = 'Situação';
$_['column_action']            = 'Ações';
$_['button_enable']            = 'Ligar';
$_['button_disable']           = 'Desligar';
$_['button_configure']         = 'Configurar';
$_['button_purge']             = 'Remover dados';
$_['button_save']              = 'Salvar';
$_['button_cancel']            = 'Cancelar';
$_['error_csrf']               = 'O formulário expirou ou não pôde ser verificado. Recarregue a página e tente novamente.';
$_['error_form']               = 'Alguns campos precisam de atenção. Nada foi gravado.';
$_['error_required']           = 'Este campo é obrigatório.';
$_['error_too_long']           = 'No máximo %d caracteres.';
$_['error_pattern']            = 'Este valor não está no formato que a extensão aceita.';
$_['error_option']             = 'Escolha uma das opções da lista.';
$_['error_not_found']          = 'Extensão não encontrada na pasta, ou o extension.json dela é inválido.';
$_['error_no_settings']        = 'Esta extensão não tem nada para configurar.';
$_['error_blocked']            = '"%s" não pode ser ligada: %s';
$_['error_purge_enabled']      = 'Desligue a extensão antes de remover os dados dela.';

// EX-02 (ADR 0096): consentimento do visitante
$_['text_consent']             = 'Só roda para o visitante que aceitou cookies de:';
$_['text_consent_analytics']   = 'medição de visitas';
$_['text_consent_marketing']   = 'publicidade';
$_['text_cookies']             = 'Cookies no navegador';
