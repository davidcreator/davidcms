<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language en-us — public contact form (common/contact, F2-17).
 */

$_['heading_title']      = 'Let\'s have a chat?';
$_['text_lead']          = 'Fill the form and I will reply by e-mail.';
$_['text_success']       = 'Message sent! I will get back to you soon.';
$_['text_honeypot_label']= 'Company';

$_['entry_name']      = 'Name';
$_['entry_email']     = 'E-mail';
$_['entry_telephone'] = 'Phone / WhatsApp';
$_['entry_subject']   = 'Subject';
$_['entry_message']   = 'Message';

$_['button_send']      = 'Send message';
$_['button_back_home'] = 'Back to home';

$_['error_name']      = 'Please inform your name (up to 128 chars).';
$_['error_email']     = 'Please inform a valid e-mail address.';
$_['error_telephone'] = 'Phone looks too long (up to 32 chars).';
$_['error_subject']   = 'Please inform the subject (up to 128 chars).';
$_['error_message']   = 'Please write your message (3 to 5000 chars).';

$_['error_csrf']       = 'Security check failed — please resend the form.';
$_['error_rate_limit'] = 'Too many attempts. Please wait a few minutes and try again.';

// F4-05 (ADR 0029): e-mail notification to the site owner
$_['text_mail_subject'] = 'New contact from the site: %s';
$_['text_mail_body']    = "Name: %s\nE-mail: %s\nTelephone: %s\n\nMessage:\n%s";
