<?php
namespace Reamur\System\Library\Mail;

use Reamur\System\Engine\Config;

/**
 * Factory F4-05 (ADR 0029): monta o adapter de envio a partir do Config
 * efetivo da requisição — startup/setting (admin/catalog) já injeta os
 * mail_* gravados pelo painel em rms_setting, então a fábrica NÃO toca o banco.
 *
 *  · Engine: mail_engine ∈ {mail, smtp, capture} (fallback seguro: 'mail')
 *  · Identidade padrão: from = mail_from ?: config_contact_email;
 *    sender = mail_sender ?: config_name — ADR 0029 (sem tela para from/sender)
 *  · capture: grava o payload JSON em DIR_STORAGE.'logs/mail_capture.json'
 *    (adapter já existente — dev/CI/baterias; nunca sai pela rede)
 *
 * Uso:
 *   $mail = Factory::create($this->config, ['to' => ..., 'subject' => ..., 'text' => ...]);
 *   $mail->send();
 *
 * Aditivo puro: nenhum consumidor existia até F4-05.
 */
final class Factory {

    /** Adapters reconhecidos — qualquer outro valor cai em 'mail' (default.php) */
    private const ENGINES = ['mail', 'smtp', 'capture'];

    /** Caminho do artefato do adapter capture (compartilhado admin/catalog: DIR_STORAGE único) */
    private const CAPTURE_FILE = 'logs/mail_capture.json';

    /**
     * @param Config $config config efetivo (default.php + rms_setting injetada)
     * @param array<string, mixed> $data mensagem: to, subject, text (obrigatórios
     *        p/ Mail), html?, reply_to? (vence o mail_reply_to global)
     * @return Mail|Smtp|Capture
     */
    public static function create(Config $config, array $data): Mail|Smtp|Capture {
        $engine = (string)$config->get('mail_engine', 'mail');

        if (!in_array($engine, self::ENGINES, true)) {
            $engine = 'mail';
        }

        // Identidade padrão (ADR 0029): sem tela própria para from/sender — fallback em runtime
        $data['from']   = (string)($data['from']   ?? '') !== '' ? (string)$data['from']   : ((string)$config->get('mail_from', '')   !== '' ? (string)$config->get('mail_from')   : (string)$config->get('config_contact_email', ''));
        $data['sender'] = (string)($data['sender'] ?? '') !== '' ? (string)$data['sender'] : ((string)$config->get('mail_sender', '') !== '' ? (string)$config->get('mail_sender') : (string)$config->get('config_name', ''));

        // reply_to: override por envio vence; senão o global; Mail monta header com fallback próprio
        if ((string)($data['reply_to'] ?? '') === '') {
            $data['reply_to'] = (string)$config->get('mail_reply_to', '');
        }

        $data['parameter'] = (string)$config->get('mail_parameter', '');

        if ($engine === 'smtp') {
            $data['smtp_hostname'] = (string)$config->get('mail_smtp_hostname', '');
            $data['smtp_username'] = (string)$config->get('mail_smtp_username', '');
            $data['smtp_password'] = (string)$config->get('mail_smtp_password', '');
            $data['smtp_port']     = (int)$config->get('mail_smtp_port', 25);
            $data['smtp_timeout']  = (int)$config->get('mail_smtp_timeout', 5);
            $data['verp']          = (bool)$config->get('mail_verp', false);

            return new Smtp($data);
        }

        if ($engine === 'capture') {
            $data['capture_path'] = (defined('DIR_STORAGE') ? DIR_STORAGE : '') . self::CAPTURE_FILE;

            return new Capture($data);
        }

        return new Mail($data);
    }
}
