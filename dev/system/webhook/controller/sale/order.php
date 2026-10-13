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
 * Webhook Controller for Order Events
 * @package Reamur\System\Engine
 */
class ControllerWebhookSaleOrder extends Controller {
    public function add($router, $args, $order_id) {
        try {
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('order_add');
            
            $data = [
                'order_id' => (int)$order_id,
                'action' => 'order_add',
                'timestamp' => time()
            ];

            $this->dispatchRequests('order_add', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (add): ' . $e->getMessage());
        }
    }

    public function edit($router, $args) {
        try {
            $order_id = (int)$args[0];
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('order_edit');
            
            $data = [
                'order_id' => $order_id,
                'action' => 'order_edit',
                'timestamp' => time()
            ];

            $this->dispatchRequests('order_edit', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (edit): ' . $e->getMessage());
        }
    }

    public function addOrderHistory($router, $args) {
        try {
            $order_id = (int)$args[0];
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('order_history_edit');
            
            $data = [
                'order_id' => $order_id,
                'action' => 'order_history_edit',
                'timestamp' => time()
            ];

            $this->dispatchRequests('order_history_edit', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (addOrderHistory): ' . $e->getMessage());
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
