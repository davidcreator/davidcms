<?php declare(strict_types=1);
/** account/logout (CATALOG, KZ-38) */
namespace Reamur\Catalog\Controller\Account;

class Logout extends \Reamur\System\Engine\Controller {
    public function index(): void {
        unset($this->session->data['customer_id']);
        $this->session->regenerateId();
        $this->response->redirect($this->url->link('common/home'));
    }
}
