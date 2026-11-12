<?php
/**
 * common/dashboard (ADMIN) — painel inicial (F8-01, ADR 0045) — pt-br
 */

$_['heading_title']         = 'Painel';
$_['text_welcome_overview'] = 'Sua operação em um relance — os contadores respeitam suas permissões.';
$_['text_stats_overview']   = 'Visão geral';
$_['text_quick_actions']    = 'Ações rápidas';
$_['text_latest_posts']     = 'Últimos posts';
$_['text_latest_messages']  = 'Últimas mensagens';
$_['text_view_all']         = 'Ver tudo';
$_['text_no_posts']         = 'Nada por aqui ainda — assim que houver conteúdo, ele aparece aqui.';
$_['text_no_messages']      = 'Nenhuma mensagem.';
$_['text_no_subject']       = '(sem assunto)';
$_['text_panel_user']       = 'Usuário';
$_['text_panel_tech']       = 'Conta e sistema';

// Painel F1-F7 legacy module list (mantido — contrato de baterias F2-F7)
$_['text_modules']              = 'Módulos';
$_['text_manage_portfolio']     = 'Portfólio';
$_['text_manage_categories']    = 'Categorias';
$_['text_manage_pages']         = 'Páginas';
$_['text_manage_blog']          = 'Blog';
$_['text_manage_testimonials']  = 'Depoimentos';
$_['text_manage_client_logos']  = 'Logos de Clientes';
$_['text_manage_tools']         = 'Ferramentas';
$_['text_manage_orders']        = 'Pedidos';
$_['text_manage_messages']      = 'Mensagens';
$_['text_manage_webhooks']      = 'Webhooks';
$_['text_manage_marketplace']   = 'Mercado Pago Marketplace';
$_['text_manage_users']         = 'Usuários';
$_['text_manage_user_groups']   = 'Grupos de Usuários';
$_['text_manage_settings']      = 'Configurações';
$_['text_manage_site_info']     = 'Informações do Site';
$_['text_manage_languages']     = 'Idiomas';

// Perfil / painel técnico
$_['text_profile']          = 'Perfil';
$_['text_username']         = 'Usuário';
$_['text_email']            = 'E-mail';
$_['text_user_group']       = 'Grupo de usuários';
$_['text_manage_profile']      = 'Editar perfil';
$_['text_acl']              = 'ACL';
$_['text_access_routes']    = 'Rotas de acesso';
$_['text_modify_routes']    = 'Rotas de modificação';
$_['text_acl_note']         = 'Gerenciada em Usuários → Grupos. Cards e menus só aparecem para rotas permitidas.';
$_['text_cache_indicator']  = 'Cache: %s · TTL %ss';
$_['text_cache_fallback']   = 'o painel configurado "%s" — fallback active';
$_['text_version']          = 'ReamurCMS v%s';
$_['button_logout']         = 'Sair';

// Cards
$_['card_posts']         = 'Posts publicados';
$_['card_pages']         = 'Páginas CMS';
$_['card_projects']      = 'Projetos';
$_['card_orders']        = 'Pedidos e orçamentos';
$_['card_messages']      = 'Mensagens';
$_['card_testimonials']  = 'Depoimentos';
$_['card_tools']         = 'Ferramentas';
$_['card_webhook_fails'] = 'Falhas de webhook';
$_['card_today']         = 'hoje: %s';
$_['card_7d']            = 'últimos 7 dias: %s';
$_['card_24h']           = 'últimas 24h: %s';
$_['card_30d']           = 'últimos 30 dias: %s';

// Ações rápidas
$_['action_new_post']    = 'Novo post';
$_['action_new_page']    = 'Páginas (CMS)';
$_['action_new_project'] = 'Novo projeto';
$_['action_new_tool']    = 'Ferramentas';
$_['action_import_wp']   = 'Importar do WordPress';

// ADR 0088 (S3)
$_['warning_error_display'] = 'A exibição de erros está ligada nesta instalação: se algo falhar, detalhes técnicos (arquivo, linha, SQL) aparecem na tela para qualquer visitante. Em produção, defina REAMUR_ERROR_DISPLAY como false nos arquivos config.php de catalog/ e admin/.';

// ADR 0089 (S5): conexão, proxy e CDN
$_['text_conn_heading']           = 'Conexão';
$_['text_conn_ip']                = 'Seu IP para o sistema';
$_['text_conn_peer']              = 'Conexão recebida de';
$_['text_conn_https']             = 'HTTPS';
$_['text_conn_https_no']          = 'não';
$_['text_conn_https_direct']      = 'sim, visto pelo servidor';
$_['text_conn_https_proxy']       = 'sim, informado pelo proxy';
$_['text_conn_cookie']            = 'Cookie de sessão';
$_['text_conn_cookie_secure']     = 'com Secure';
$_['text_conn_cookie_plain']      = 'sem Secure';
$_['text_conn_trust']             = 'Proxies confiáveis';
$_['text_conn_trust_none']        = 'não configurado';
$_['text_conn_trust_list']        = 'lista com %d entrada(s)';
$_['text_conn_trust_hops']        = '%d proxy(s) na frente';
$_['text_conn_note']              = 'Se os dois endereços são iguais e não são o seu IP, o site está atrás de um proxy ou CDN que ainda não foi declarado no config.php.';
$_['warning_proxy_unconfigured']  = 'A conexão chegou de %s trazendo cabeçalhos de proxy. Se o site fica atrás de CDN ou proxy reverso, todos os visitantes contam hoje como um só IP no limite de tentativas de login, contato e orçamento. Defina REAMUR_TRUSTED_PROXIES ou REAMUR_TRUSTED_PROXY_HOPS em catalog/config.php e admin/config.php.';
$_['warning_proxy_untrusted_peer']= 'A conexão chegou de %s, que não está em REAMUR_TRUSTED_PROXIES. Os cabeçalhos de proxy foram ignorados. Se esse endereço é da sua CDN, acrescente-o à lista nos dois arquivos config.php.';
$_['warning_proxy_invalid']       = 'REAMUR_TRUSTED_PROXIES tem entradas que não são endereço nem faixa e foram ignoradas: %s';
$_['warning_proxy_hops_direct']   = 'REAMUR_TRUSTED_PROXY_HOPS vale %d, mas esta conexão chegou sem X-Forwarded-For. Se o servidor pode ser acessado por fora do proxy, o IP do visitante pode ser forjado: prefira REAMUR_TRUSTED_PROXIES com os endereços do proxy.';
