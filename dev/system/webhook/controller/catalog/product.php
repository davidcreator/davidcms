<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link https://reamurcms.com
 */
namespace Reamur\System\Engine;

/**
 * Webhook Controller for Product Events
 * @package Reamur\System\Engine
 */
class ControllerWebhookCatalogProduct extends Controller {
    public function add($router, $args, $product_id) {
        try {
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('product_add');
            
            $data = [
                'product_id' => (int)$product_id,
                'action' => 'product_add',
                'timestamp' => time()
            ];

            $this->dispatchRequests('product_add', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (product_add): ' . $e->getMessage());
        }
    }

    public function copy($router, $args, $output) {
        try {
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('product_add');
            
            $data = [
                'product_id' => (int)$output,
                'action' => 'product_add',
                'timestamp' => time()
            ];

            $this->dispatchRequests('product_add', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (product_copy): ' . $e->getMessage());
        }
    }

    public function edit($router, $args) {
        try {
            $product_id = (int)$args[0];
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('product_edit');
            
            $data = [
                'product_id' => $product_id,
                'action' => 'product_edit',
                'timestamp' => time()
            ];

            $this->dispatchRequests('product_edit', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (product_edit): ' . $e->getMessage());
        }
    }

    public function delete($router, $args) {
        try {
            $product_id = (int)$args[0];
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('product_delete');
            
            $data = [
                'product_id' => $product_id,
                'action' => 'product_delete',
                'timestamp' => time()
            ];

            $this->dispatchRequests('product_delete', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (product_delete): ' . $e->getMessage());
        }
    }

    public function order($router, $args) {
        try {
            $order_id = (int)$args[0];
            $order_status_id = (int)$args[1];

            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info) {
                return false;
            }

            $order_products = $this->model_checkout_order->getOrderProducts($order_id);

            if (!$order_products) {
                return false;
            }

            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('product_edit');

            if (!in_array($order_info['order_status_id'], array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))) && 
                in_array($order_status_id, array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status')))) {
                
                foreach ($order_products as $order_product) {
                    $data = [
                        'product_id' => (int)$order_product['product_id'],
                        'action' => 'product_stock_edit',
                        'timestamp' => time(),
                        'order_id' => $order_id
                    ];

                    if ($order_product['subtract']) {
                        $this->dispatchRequests('product_stock_edit', $hooks, $data);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (product_order): ' . $e->getMessage());
        }
    }

    private function dispatchRequests($action, $hooks, $data) {
        if (empty($hooks)) {
            return;
        }

        try {
            ob_start();
            $multiCurl = [];
            $mh = curl_multi_init();

            foreach ($hooks as $hook) {
                $key = $hook['webhook_client_id'];
                $url = filter_var($hook['url'], FILTER_VALIDATE_URL);
                
                if (!$url) {
                    continue;
                }

                $multiCurl[$key] = curl_init($url);
                curl_setopt_array($multiCurl[$key], [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FAILONERROR => false,
                    CURLINFO_HEADER_OUT => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_TIMEOUT => $hook['timeout'] ?? 30,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => array_merge(
                        $hook['headers'] ?? [],
                        ['Content-Type: application/json']
                    )
                ]);

                if (!empty($hook['auth'])) {
                    curl_setopt($multiCurl[$key], CURLOPT_USERPWD, $hook['auth']);
                }

                curl_multi_add_handle($mh, $multiCurl[$key]);
            }

            $active = null;
            do {
                $status = curl_multi_exec($mh, $active);
                if ($status > 0) {
                    throw new \Exception(curl_multi_strerror($status));
                }
            } while ($active > 0);

            foreach ($multiCurl as $webhook_client_id => $ch) {
                $content = curl_multi_getcontent($ch);
                $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
                $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                
                $response = $error ?: $headers . $content;
                
                $this->model_webhook_advanced_webhook->saveRequest(
                    $webhook_client_id, 
                    $action, 
                    $data, 
                    $response, 
                    $status_code
                );
                
                curl_multi_remove_handle($mh, $ch);
            }

            curl_multi_close($mh);
            ob_end_clean();
        } catch (\Exception $e) {
            $this->log->write('Webhook Dispatch Error: ' . $e->getMessage());
            ob_end_clean();
        }
    }
}
