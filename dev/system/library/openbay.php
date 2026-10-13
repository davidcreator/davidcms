<?php
/**
 * Classe Openbay
 * Sistema de integração com marketplaces para OpenCart
 * 
 * @author OpenCart Team
 * @version 1.1
 */
final class Openbay {
    private $registry;
    private $installed_modules = array();
    public $installed_markets = array();
    private $logging = 1;
    private $logger;

    /**
     * Construtor da classe
     * 
     * @param object $registry Registro do sistema OpenCart
     */
    public function __construct($registry) {
        $this->registry = $registry;

        try {
            // Verifica se a conexão com o banco existe
            if ($this->db !== null) {
                $this->getInstalled();
                $this->initializeMarkets();
            }

            // Inicializa o logger
            $this->initializeLogger();
            
        } catch (Exception $e) {
            error_log('Openbay initialization failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to initialize Openbay: ' . $e->getMessage());
        }
    }

    /**
     * Magic method para acessar propriedades do registry
     * 
     * @param string $name Nome da propriedade
     * @return mixed
     */
    public function __get($name) {
        if (!$this->registry->has($name)) {
            throw new InvalidArgumentException("Property '$name' not found in registry");
        }
        return $this->registry->get($name);
    }

    /**
     * Inicializa os mercados instalados
     * 
     * @return void
     */
    private function initializeMarkets() {
        foreach ($this->installed_markets as $market) {
            $class = '\openbay\\' . ucfirst($market);
            
            if (class_exists($class)) {
                try {
                    $this->{$market} = new $class($this->registry);
                } catch (Exception $e) {
                    $this->log("Failed to initialize market '$market': " . $e->getMessage());
                }
            } else {
                $this->log("Market class '$class' not found for market '$market'");
            }
        }
    }

    /**
     * Inicializa o logger
     * 
     * @return void
     */
    private function initializeLogger() {
        try {
            $this->logger = new \Log('openbay.log');
        } catch (Exception $e) {
            error_log('Failed to initialize Openbay logger: ' . $e->getMessage());
            // Continua sem logger se não conseguir inicializar
            $this->logging = 0;
        }
    }

    /**
     * Registra log com informações adicionais
     * 
     * @param string $data Dados para log
     * @param bool $write Se deve escrever no arquivo
     * @return void
     */
    public function log($data, $write = true) {
        if ($this->logging !== 1 || !$this->logger) {
            return;
        }

        $log_entry = $data;
        
        // Adiciona process ID se disponível
        if (function_exists('getmypid')) {
            $process_id = getmypid();
            $log_entry = "[PID: $process_id] " . $log_entry;
        }

        // Adiciona timestamp
        $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $log_entry;

        if ($write === true) {
            try {
                $this->logger->write($log_entry);
            } catch (Exception $e) {
                error_log('Openbay logging failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Criptografa dados usando AES-128-CBC
     * 
     * @param mixed $value Valor a ser criptografado
     * @param string $key Chave de criptografia (hex)
     * @param string $iv Vetor de inicialização (hex)
     * @param bool $json Se deve converter para JSON primeiro
     * @return string Dados criptografados em base64 URL-safe
     * @throws InvalidArgumentException|RuntimeException
     */
    public function encrypt($value, $key, $iv, $json = true) {
        if (!is_string($key) || !is_string($iv)) {
            throw new InvalidArgumentException('Key and IV must be strings');
        }

        // Valida o tamanho da chave e IV
        $key_bin = hex2bin($key);
        $iv_bin = hex2bin($iv);
        
        if ($key_bin === false || strlen($key_bin) !== 16) {
            throw new InvalidArgumentException('Invalid key format or length (expected 32 hex chars for AES-128)');
        }
        
        if ($iv_bin === false || strlen($iv_bin) !== 16) {
            throw new InvalidArgumentException('Invalid IV format or length (expected 32 hex chars)');
        }

        // Converte para JSON se necessário
        if ($json) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($value === false) {
                throw new RuntimeException('JSON encode failed: ' . json_last_error_msg());
            }
        }

        // Criptografa
        $encrypted = openssl_encrypt(
            $value, 
            'aes-128-cbc', 
            hash('sha256', $key_bin, true), 
            OPENSSL_RAW_DATA, 
            $iv_bin
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Retorna em formato URL-safe
        return strtr(base64_encode($encrypted), '+/=', '-_,');
    }

    /**
     * Descriptografa dados usando AES-128-CBC
     * 
     * @param string $value Dados criptografados em base64 URL-safe
     * @param string $key Chave de descriptografia (hex)
     * @param string $iv Vetor de inicialização (hex)
     * @param bool $json Se deve decodificar JSON
     * @return mixed Dados descriptografados
     * @throws InvalidArgumentException|RuntimeException
     */
    public function decrypt($value, $key, $iv, $json = true) {
        if (!is_string($key) || !is_string($iv) || !is_string($value)) {
            throw new InvalidArgumentException('Key, IV and value must be strings');
        }

        // Valida o tamanho da chave e IV
        $key_bin = hex2bin($key);
        $iv_bin = hex2bin($iv);
        
        if ($key_bin === false || strlen($key_bin) !== 16) {
            throw new InvalidArgumentException('Invalid key format or length');
        }
        
        if ($iv_bin === false || strlen($iv_bin) !== 16) {
            throw new InvalidArgumentException('Invalid IV format or length');
        }

        // Converte de URL-safe base64
        $encrypted_data = base64_decode(strtr($value, '-_,', '+/='));
        if ($encrypted_data === false) {
            throw new RuntimeException('Invalid base64 data');
        }

        // Descriptografa
        $decrypted = openssl_decrypt(
            $encrypted_data,
            'aes-128-cbc',
            hash('sha256', $key_bin, true),
            OPENSSL_RAW_DATA,
            $iv_bin
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        $response = trim($decrypted);

        // Decodifica JSON se necessário
        if ($json) {
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON decode failed: ' . json_last_error_msg());
            }
            return $decoded;
        }

        return $response;
    }

    /**
     * Obtém mercados instalados do banco de dados
     * 
     * @return void
     */
    private function getInstalled() {
        try {
            $query = $this->db->query("SELECT `code` FROM " . DB_PREFIX . "extension WHERE `type` = 'openbay'");
            
            $this->installed_markets = array();
            foreach ($query->rows as $result) {
                if (!empty($result['code'])) {
                    $this->installed_markets[] = $result['code'];
                }
            }
            
        } catch (Exception $e) {
            $this->log('Failed to get installed markets: ' . $e->getMessage());
            $this->installed_markets = array();
        }
    }

    /**
     * Retorna lista de mercados instalados
     * 
     * @return array Lista de mercados
     */
    public function getInstalledMarkets() {
        return $this->installed_markets;
    }

    /**
     * Atualiza estoque em lote nos mercados
     * 
     * @param array $product_id_array Array de IDs de produtos
     * @param bool $end_inactive Se deve inativar produtos sem estoque
     * @return void
     */
    public function putStockUpdateBulk($product_id_array, $end_inactive = false) {
        if (!is_array($product_id_array) || empty($product_id_array)) {
            return;
        }

        foreach ($this->installed_markets as $market) {
            if ($this->isMarketActive($market) && isset($this->{$market})) {
                try {
                    $this->{$market}->putStockUpdateBulk($product_id_array, $end_inactive);
                } catch (Exception $e) {
                    $this->log("Stock update failed for market '$market': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Verifica se um mercado está ativo
     * 
     * @param string $market Nome do mercado
     * @return bool
     */
    private function isMarketActive($market) {
        return ($this->config->get($market . '_status') == 1 || 
                $this->config->get('openbay_' . $market . '_status') == 1);
    }

    /**
     * Testa se uma coluna existe em uma tabela
     * 
     * @param string $table Nome da tabela
     * @param string $column Nome da coluna
     * @return bool
     * @throws InvalidArgumentException
     */
    public function testDbColumn($table, $column) {
        if (!is_string($table) || !is_string($column) || empty($table) || empty($column)) {
            throw new InvalidArgumentException('Table and column names must be non-empty strings');
        }

        try {
            $escaped_table = $this->db->escape($table);
            $escaped_column = $this->db->escape($column);
            
            $res = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $escaped_table . "` LIKE '" . $escaped_column . "'");
            return $res->num_rows > 0;
            
        } catch (Exception $e) {
            $this->log("Database column test failed for $table.$column: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Testa se uma tabela existe
     * 
     * @param string $table Nome da tabela
     * @return bool
     */
    public function testDbTable($table) {
        if (!is_string($table) || empty($table)) {
            return false;
        }

        try {
            $res = $this->db->query("SELECT `table_name` AS `c` FROM `information_schema`.`tables` WHERE `table_schema` = DATABASE()");

            $tables = array();
            foreach($res->rows as $row) {
                $tables[] = $row['c'];
            }

            return in_array(DB_PREFIX . $table, $tables);
            
        } catch (Exception $e) {
            $this->log("Database table test failed for $table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Separa nome completo em nome e sobrenome
     * 
     * @param string $name Nome completo
     * @return array Array com 'firstname' e 'surname'
     */
    public function splitName($name) {
        if (!is_string($name)) {
            return array('firstname' => '', 'surname' => '');
        }

        $name = trim($name);
        if (empty($name)) {
            return array('firstname' => '', 'surname' => '');
        }

        $parts = explode(' ', $name);
        $firstname = array_shift($parts);
        $surname = implode(' ', $parts);

        return array(
            'firstname' => $firstname ?: '',
            'surname'   => $surname ?: ''
        );
    }

    /**
     * Obtém taxas para uma classe de imposto
     * 
     * @param int $tax_class_id ID da classe de imposto
     * @return array Array de taxas
     */
    public function getTaxRates($tax_class_id) {
        $tax_class_id = (int)$tax_class_id;
        if ($tax_class_id <= 0) {
            return array();
        }

        try {
            $tax_query = $this->db->query("SELECT
                        tr2.tax_rate_id,
                        tr2.name,
                        tr2.rate,
                        tr2.type,
                        tr1.priority
                    FROM " . DB_PREFIX . "tax_rule tr1
                    LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id)
                    INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id)
                    LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id)
                    LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id)
                    WHERE tr1.tax_class_id = '" . $tax_class_id . "'
                    AND tr1.based = 'shipping'
                    AND tr2cg.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                    AND z2gz.country_id = '" . (int)$this->config->get('config_country_id') . "'
                    AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$this->config->get('config_zone_id') . "')
                    ORDER BY tr1.priority ASC");

            $tax_rates = array();
            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }

            return $tax_rates;
            
        } catch (Exception $e) {
            $this->log("Failed to get tax rates for class $tax_class_id: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Calcula percentual de imposto para uma classe
     * 
     * @param int $class_id ID da classe de imposto
     * @return float Percentual total
     */
    public function getTaxRate($class_id) {
        $rates = $this->getTaxRates($class_id);
        $percentage = 0.00;
        
        foreach($rates as $rate) {
            if($rate['type'] == 'P') {
                $percentage += (float)$rate['rate'];
            }
        }

        return $percentage;
    }

    /**
     * Obtém ID da zona pelo nome e país
     * 
     * @param string $name Nome da zona
     * @param int $country_id ID do país
     * @return int ID da zona ou 0 se não encontrada
     */
    public function getZoneId($name, $country_id) {
        if (!is_string($name) || empty($name)) {
            return 0;
        }

        $country_id = (int)$country_id;
        if ($country_id <= 0) {
            return 0;
        }

        try {
            $query = $this->db->query("SELECT `zone_id` FROM `" . DB_PREFIX . "zone` WHERE `country_id` = '" . $country_id . "' AND status = '1' AND `name` = '" . $this->db->escape($name) . "' LIMIT 1");

            return ($query->num_rows > 0) ? (int)$query->row['zone_id'] : 0;
            
        } catch (Exception $e) {
            $this->log("Failed to get zone ID for '$name' in country $country_id: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Notifica administradores sobre novo pedido
     * 
     * @param int $order_id ID do pedido
     * @param int $order_status_id ID do status do pedido
     * @return void
     */
    public function newOrderAdminNotify($order_id, $order_status_id) {
        $order_id = (int)$order_id;
        $order_status_id = (int)$order_status_id;
        
        if ($order_id <= 0 || $order_status_id <= 0) {
            return;
        }

        try {
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info) {
                throw new RuntimeException("Order #$order_id not found");
            }

            // Só envia notificação para pedidos novos
            if ($order_info['order_status_id'] || !$order_status_id || 
                !in_array('order', (array)$this->config->get('config_mail_alert'))) {
                return;
            }

            $this->sendOrderNotificationEmail($order_info, $order_status_id);
            
        } catch (Exception $e) {
            $this->log('Order notification error for order ' . $order_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Envia email de notificação de pedido
     * 
     * @param array $order_info Informações do pedido
     * @param int $order_status_id ID do status
     * @return void
     */
    private function sendOrderNotificationEmail($order_info, $order_status_id) {
        $this->load->language('mail/order_alert');
        $this->load->model('tool/image');
        $this->load->model('tool/upload');

        // Prepara dados do template
        $data = $this->prepareOrderNotificationData($order_info, $order_status_id);
        
        // Configura o email
        $mail = $this->createMailInstance();
        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));
        $mail->setSubject(html_entity_decode(
            sprintf($this->language->get('text_subject'), 
                   $this->config->get('config_name'), 
                   $order_info['order_id']), 
            ENT_QUOTES, 'UTF-8'
        ));
        $mail->setText($this->load->view('mail/order_alert', $data));
        $mail->send();

        // Envia para emails adicionais
        $this->sendToAdditionalEmails($mail);
    }

    /**
     * Prepara dados para o template de notificação
     * 
     * @param array $order_info Informações do pedido
     * @param int $order_status_id ID do status
     * @return array Dados preparados
     */
    private function prepareOrderNotificationData($order_info, $order_status_id) {
        $data = array();
        
        // Textos do idioma
        $language_keys = array('text_received', 'text_order_id', 'text_date_added', 
                              'text_order_status', 'text_product', 'text_total', 'text_comment');
        
        foreach ($language_keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        // Dados básicos do pedido
        $data['order_id'] = $order_info['order_id'];
        $data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));
        $data['store_url'] = HTTP_SERVER;
        $data['store'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
        $data['comment'] = strip_tags($order_info['comment']);

        // Status do pedido
        $data['order_status'] = $this->getOrderStatusName($order_status_id);
        
        // Logo
        $data['logo'] = $this->getStoreLogo();
        
        // Produtos, vouchers e totais
        $data['products'] = $this->getOrderProductsForEmail($order_info['order_id']);
        $data['vouchers'] = $this->getOrderVouchersForEmail($order_info);
        $data['totals'] = $this->getOrderTotalsForEmail($order_info);

        return $data;
    }

    /**
     * Obtém nome do status do pedido
     * 
     * @param int $order_status_id ID do status
     * @return string Nome do status
     */
    private function getOrderStatusName($order_status_id) {
        try {
            $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");
            
            return $order_status_query->num_rows ? $order_status_query->row['name'] : '';
        } catch (Exception $e) {
            $this->log('Failed to get order status name: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Obtém URL do logo da loja
     * 
     * @return string URL do logo
     */
    private function getStoreLogo() {
        try {
            $logo_path = $this->config->get('config_logo');
            
            if ($logo_path && is_file(DIR_IMAGE . $logo_path)) {
                return $this->model_tool_image->resize(
                    $logo_path, 
                    $this->config->get('theme_default_image_location_width'), 
                    $this->config->get('theme_default_image_cart_height')
                );
            }
        } catch (Exception $e) {
            $this->log('Failed to get store logo: ' . $e->getMessage());
        }
        
        return '';
    }

    /**
     * Obtém produtos do pedido formatados para email
     * 
     * @param int $order_id ID do pedido
     * @return array Produtos formatados
     */
    private function getOrderProductsForEmail($order_id) {
        try {
            $products = array();
            $order_products = $this->model_checkout_order->getOrderProducts($order_id);

            foreach ($order_products as $order_product) {
                $products[] = array(
                    'name'     => $order_product['name'],
                    'model'    => $order_product['model'],
                    'quantity' => $order_product['quantity'],
                    'option'   => $this->getOrderProductOptions($order_id, $order_product['order_product_id']),
                    'total'    => $this->formatCurrency($order_product, $this->getOrderInfo($order_id))
                );
            }
            
            return $products;
        } catch (Exception $e) {
            $this->log('Failed to get order products for email: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém opções do produto do pedido
     * 
     * @param int $order_id ID do pedido
     * @param int $order_product_id ID do produto do pedido
     * @return array Opções formatadas
     */
    private function getOrderProductOptions($order_id, $order_product_id) {
        try {
            $option_data = array();
            $order_options = $this->model_checkout_order->getOrderOptions($order_id, $order_product_id);

            foreach ($order_options as $order_option) {
                $value = $order_option['value'];
                
                if ($order_option['type'] == 'file') {
                    $upload_info = $this->model_tool_upload->getUploadByCode($order_option['value']);
                    $value = $upload_info ? $upload_info['name'] : '';
                }

                $option_data[] = array(
                    'name'  => $order_option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                );
            }
            
            return $option_data;
        } catch (Exception $e) {
            $this->log('Failed to get order product options: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém informações básicas do pedido
     * 
     * @param int $order_id ID do pedido
     * @return array|null Informações do pedido
     */
    private function getOrderInfo($order_id) {
        try {
            return $this->model_checkout_order->getOrder($order_id);
        } catch (Exception $e) {
            $this->log('Failed to get order info: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Formata valor monetário
     * 
     * @param array $product Dados do produto
     * @param array|null $order_info Informações do pedido
     * @return string Valor formatado
     */
    private function formatCurrency($product, $order_info) {
        if (!$order_info) {
            return '0.00';
        }

        try {
            $total = $product['total'];
            
            if ($this->config->get('config_tax')) {
                $total += ($product['tax'] * $product['quantity']);
            }
            
            return html_entity_decode(
                $this->currency->format($total, $order_info['currency_code'], $order_info['currency_value']), 
                ENT_NOQUOTES, 'UTF-8'
            );
        } catch (Exception $e) {
            $this->log('Failed to format currency: ' . $e->getMessage());
            return '0.00';
        }
    }

    /**
     * Obtém vouchers do pedido para email
     * 
     * @param array $order_info Informações do pedido
     * @return array Vouchers formatados
     */
    private function getOrderVouchersForEmail($order_info) {
        try {
            $vouchers = array();
            $order_vouchers = $this->model_checkout_order->getOrderVouchers($order_info['order_id']);

            foreach ($order_vouchers as $order_voucher) {
                $vouchers[] = array(
                    'description' => $order_voucher['description'],
                    'amount'      => html_entity_decode($this->currency->format($order_voucher['amount'], $order_info['currency_code'], $order_info['currency_value']), ENT_NOQUOTES, 'UTF-8')
                );
            }
            
            return $vouchers;
        } catch (Exception $e) {
            $this->log('Failed to get order vouchers: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém totais do pedido para email
     * 
     * @param array $order_info Informações do pedido
     * @return array Totais formatados
     */
    private function getOrderTotalsForEmail($order_info) {
        try {
            $totals = array();
            $order_totals = $this->model_checkout_order->getOrderTotals($order_info['order_id']);

            foreach ($order_totals as $order_total) {
                $totals[] = array(
                    'title' => $order_total['title'],
                    'value' => html_entity_decode($this->currency->format($order_total['value'], $order_info['currency_code'], $order_info['currency_value']), ENT_NOQUOTES, 'UTF-8')
                );
            }
            
            return $totals;
        } catch (Exception $e) {
            $this->log('Failed to get order totals: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Cria instância configurada do Mail
     * 
     * @return Mail Instância configurada
     */
    private function createMailInstance() {
        $mail = new Mail($this->config->get('config_mail_engine'));
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
        
        return $mail;
    }

    /**
     * Envia email para destinatários adicionais
     * 
     * @param Mail $mail Instância do email
     * @return void
     */
    private function sendToAdditionalEmails($mail) {
        try {
            $emails = explode(',', $this->config->get('config_mail_alert_email'));

            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->setTo($email);
                    $mail->send();
                }
            }
        } catch (Exception $e) {
            $this->log('Failed to send additional email notifications: ' . $e->getMessage());
        }
    }

    /**
     * Chamado quando um pedido é deletado no admin
     * Usado para adicionar estoque de volta aos marketplaces
     * 
     * @param int $order_id ID do pedido
     * @return void
     */
    public function orderDelete($order_id) {
        $order_id = (int)$order_id;
        if ($order_id <= 0) {
            return;
        }

        foreach ($this->installed_markets as $market) {
            if ($this->isMarketActive($market) && isset($this->{$market})) {
                try {
                    $this->{$market}->orderDelete($order_id);
                } catch (Exception $e) {
                    $this->log("Order delete failed for market '$market', order $order_id: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Obtém número do modelo do produto
     * 
     * @param int $product_id ID do produto
     * @param string|null $sku SKU específico (opcional)
     * @return string|false Número do modelo ou false se não encontrado
     */
    public function getProductModelNumber($product_id, $sku = null) {
        $product_id = (int)$product_id;
        if ($product_id <= 0) {
            return false;
        }

        try {
            if ($sku !== null) {
                if (!is_string($sku) || empty($sku)) {
                    return false;
                }
                
                $query = $this->db->query("SELECT `sku` FROM `" . DB_PREFIX . "product_option_variant` WHERE `product_id` = '" . $product_id . "' AND `sku` = '" . $this->db->escape($sku) . "' LIMIT 1");
                
                return ($query->num_rows > 0) ? $query->row['sku'] : false;
            } else {
                $query = $this->db->query("SELECT `model` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $product_id . "' LIMIT 1");
                
                return ($query->num_rows > 0) ? $query->row['model'] : false;
            }
        } catch (Exception $e) {
            $this->log("Failed to get product model number for product $product_id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém ID da classe de imposto do produto
     * 
     * @param int $product_id ID do produto
     * @return int|false ID da classe de imposto ou false se não encontrado
     */
    public function getProductTaxClassId($product_id) {
        $product_id = (int)$product_id;
        if ($product_id <= 0) {
            return false;
        }

        try {
            $query = $this->db->query("SELECT `tax_class_id` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $product_id . "' LIMIT 1");

            return ($query->num_rows > 0) ? (int)$query->row['tax_class_id'] : false;
        } catch (Exception $e) {
            $this->log("Failed to get tax class ID for product $product_id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se um addon está instalado
     * 
     * @param string $addon Nome do addon
     * @return bool True se instalado
     */
    public function addonLoad($addon) {
        if (!is_string($addon) || empty($addon)) {
            return false;
        }

        $addon = strtolower(trim($addon));

        // Carrega módulos instalados se ainda não carregou
        if (empty($this->installed_modules)) {
            $this->loadInstalledModules();
        }

        return in_array($addon, $this->installed_modules);
    }

    /**
     * Carrega lista de módulos instalados
     * 
     * @return void
     */
    private function loadInstalledModules() {
        try {
            $this->installed_modules = array();
            $rows = $this->db->query("SELECT `code` FROM " . DB_PREFIX . "extension")->rows;

            foreach ($rows as $row) {
                if (!empty($row['code'])) {
                    $this->installed_modules[] = strtolower($row['code']);
                }
            }
        } catch (Exception $e) {
            $this->log('Failed to load installed modules: ' . $e->getMessage());
            $this->installed_modules = array();
        }
    }

    /**
     * Obtém ID do cliente pelo email
     * 
     * @param string $email Email do cliente
     * @return int|false ID do cliente ou false se não encontrado
     */
    public function getUserByEmail($email) {
        if (!is_string($email) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $query = $this->db->query("SELECT `customer_id` FROM `" . DB_PREFIX . "customer` WHERE `email` = '" . $this->db->escape($email) . "' LIMIT 1");

            return ($query->num_rows > 0) ? (int)$query->row['customer_id'] : false;
        } catch (Exception $e) {
            $this->log("Failed to get user by email '$email': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém opções de um produto
     * 
     * @param int $product_id ID do produto
     * @return array Array de opções do produto
     */
    public function getProductOptions($product_id) {
        $product_id = (int)$product_id;
        if ($product_id <= 0) {
            return array();
        }

        try {
            $product_option_data = array();
            $language_id = (int)$this->config->get('config_language_id');

            $product_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_id = '" . $product_id . "' AND od.language_id = '" . $language_id . "' ORDER BY o.sort_order");

            foreach ($product_option_query->rows as $product_option) {
                if (in_array($product_option['type'], array('select', 'radio', 'checkbox', 'image'))) {
                    $product_option_data[] = $this->getProductOptionWithValues($product_option);
                } else {
                    $product_option_data[] = $this->getProductOptionSimple($product_option);
                }
            }

            return $product_option_data;
        } catch (Exception $e) {
            $this->log("Failed to get product options for product $product_id: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém opção de produto com valores
     * 
     * @param array $product_option Dados da opção
     * @return array Opção formatada com valores
     */
    private function getProductOptionWithValues($product_option) {
        try {
            $product_option_value_data = array();
            $language_id = (int)$this->config->get('config_language_id');

            $product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' AND ovd.language_id = '" . $language_id . "' ORDER BY ov.sort_order");

            foreach ($product_option_value_query->rows as $product_option_value) {
                $product_option_value_data[] = array(
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id'         => $product_option_value['option_value_id'],
                    'name'                    => $product_option_value['name'],
                    'image'                   => $product_option_value['image'],
                    'quantity'                => $product_option_value['quantity'],
                    'subtract'                => $product_option_value['subtract'],
                    'price'                   => $product_option_value['price'],
                    'price_prefix'            => $product_option_value['price_prefix'],
                    'points'                  => $product_option_value['points'],
                    'points_prefix'           => $product_option_value['points_prefix'],
                    'weight'                  => $product_option_value['weight'],
                    'weight_prefix'           => $product_option_value['weight_prefix']
                );
            }

            return array(
                'product_option_id'    => $product_option['product_option_id'],
                'option_id'            => $product_option['option_id'],
                'name'                 => $product_option['name'],
                'type'                 => $product_option['type'],
                'product_option_value' => $product_option_value_data,
                'required'             => $product_option['required']
            );
        } catch (Exception $e) {
            $this->log('Failed to get product option with values: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém opção de produto simples (sem valores)
     * 
     * @param array $product_option Dados da opção
     * @return array Opção formatada
     */
    private function getProductOptionSimple($product_option) {
        return array(
            'product_option_id' => $product_option['product_option_id'],
            'option_id'         => $product_option['option_id'],
            'name'              => $product_option['name'],
            'type'              => $product_option['type'],
            'option_value'      => $product_option['value'],
            'required'          => $product_option['required']
        );
    }

    /**
     * Obtém produtos de um pedido
     * 
     * @param int $order_id ID do pedido
     * @return array Array de produtos ou array vazio
     */
    public function getOrderProducts($order_id) {
        $order_id = (int)$order_id;
        if ($order_id <= 0) {
            return array();
        }

        try {
            $order_products = $this->db->query("SELECT `product_id`, `order_product_id` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . $order_id . "'");

            return ($order_products->num_rows > 0) ? $order_products->rows : array();
        } catch (Exception $e) {
            $this->log("Failed to get order products for order $order_id: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtém variante do produto do pedido
     * 
     * @param int $order_id ID do pedido
     * @param int $product_id ID do produto
     * @param int $order_product_id ID do produto do pedido
     * @return mixed Dados da variante ou null
     */
    public function getOrderProductVariant($order_id, $product_id, $order_product_id) {
        $order_id = (int)$order_id;
        $product_id = (int)$product_id;
        $order_product_id = (int)$order_product_id;

        if ($order_id <= 0 || $product_id <= 0 || $order_product_id <= 0) {
            return null;
        }

        try {
            // Verifica se o modelo openstock existe
            if (!$this->addonLoad('openstock')) {
                return null;
            }

            $this->load->model('extension/module/openstock');

            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . $order_id . "' AND order_product_id = '" . $order_product_id . "'");

            if ($order_option_query->num_rows) {
                $options = array();

                foreach ($order_option_query->rows as $option) {
                    if (!empty($option['product_option_value_id'])) {
                        $options[] = $option['product_option_value_id'];
                    }
                }

                if (!empty($options)) {
                    return $this->model_extension_module_openstock->getVariantByOptionValues($options, $product_id);
                }
            }

            return null;
        } catch (Exception $e) {
            $this->log("Failed to get order product variant: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpa recursos e conexões
     * 
     * @return void
     */
    public function __destruct() {
        // Limpa referências para evitar vazamentos de memória
        $this->installed_modules = array();
        $this->installed_markets = array();
        
        if ($this->logger) {
            $this->logger = null;
        }
    }
}