<?php
/**
 * Language: pt-br — setting/site_info (F4-03, ADR 0026)
 */

// Heading
$_['heading_title'] = 'Informações do site';

// Text
$_['text_site_info'] = 'Dados institucionais exibidos no site público: contato, endereço, rodapé e redes sociais.';
$_['text_success']   = 'Informações do site salvas com sucesso!';

// Fieldsets
$_['fieldset_contact']   = 'Contato (global)';
$_['fieldset_address']   = 'Endereço (por idioma)';
$_['fieldset_copyright'] = 'Rodapé (por idioma)';
$_['fieldset_social']    = 'Redes sociais (globais)';

// Entries — globais
$_['entry_config_contact_email']           = 'E-mail de contato';
$_['entry_config_contact_whatsapp_text']   = 'WhatsApp — texto visível';
$_['entry_config_contact_whatsapp_href']   = 'WhatsApp — número do link (wa.me)';
$_['entry_config_social_facebook_href']    = 'URL do Facebook';
$_['entry_config_social_instagram_href']   = 'URL do Instagram';
$_['entry_config_social_pinterest_href']   = 'URL do Pinterest';
$_['entry_config_social_x_href']           = 'URL do X (Twitter)';
$_['entry_config_social_google_business_href'] = 'URL do Google Business';
$_['entry_config_social_redbubble_href']   = 'URL do Redbubble';

// Entries — por idioma
$_['entry_contact_address'] = 'Texto do endereço';
$_['entry_copyright']       = 'Texto de copyright';

// Helps
$_['help_config_contact_email']         = 'Destino do formulário de contato e endereço exibido no site.';
$_['help_config_contact_whatsapp_text'] = 'Texto exibido ao lado do ícone do WhatsApp (ex.: +55 11 99999-0000).';
$_['help_config_contact_whatsapp_href'] = 'Somente dígitos (país + DDD + número). Usado em https://wa.me/<número>. Vazio mantém o ícone, porém sem destino de link.';
$_['help_contact_address']              = 'Linha de endereço exibida na seção Contato.';
$_['help_copyright']                    = 'O marcador %s é substituído pelo ano corrente — mantenha-o no texto.';
$_['help_fallback']                     = 'Se um campo ficar intencionalmente vazio no banco, o site cai para o texto do arquivo de idioma (degradação silenciosa — ADR 0023).';

// Errors
$_['error_required']       = '%s é obrigatório!';
$_['error_email']          = '%s deve ser um e-mail válido!';
$_['error_whatsapp_href']  = 'O número do link do WhatsApp deve conter só dígitos (8–16)!';
$_['error_copyright_year'] = 'O copyright deve conter o marcador %s (ano corrente)!';
$_['error_url']            = '%s deve começar com http:// ou https://!';

// Buttons
$_['button_save'] = 'Salvar';
$_['button_back'] = 'Voltar';
