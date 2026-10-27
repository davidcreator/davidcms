<?php
/** Apply published Catalog theme overrides; isolated to the Catalog pre-action. */
namespace Reamur\Catalog\Controller\Startup;
class Theme extends \Reamur\System\Engine\Controller {
    public function index(): void {
        if (!method_exists($this->template, 'setThemeTemplates')) return;
        $this->load->model('common/theme');
        $config=$this->model_common_theme->getPublishedTheme();
        $templates=$config['templates'] ?? [];
        if (is_array($templates)) $this->template->setThemeTemplates($templates);
        if (isset($config['primary']) && preg_match('/^#[0-9a-fA-F]{6}$/',(string)$config['primary'])) $this->config->set('catalog_theme_primary',(string)$config['primary']);
        if (isset($config['surface']) && preg_match('/^#[0-9a-fA-F]{6}$/',(string)$config['surface'])) $this->config->set('catalog_theme_surface',(string)$config['surface']);
        // Scene selection remains declarative and allowlisted by the canonical Three.js system.
        $scenes=['','hero-particles','portfolio-3d','tools-3d','not-found'];
        if (isset($config['scene']) && in_array((string)$config['scene'],$scenes,true)) $this->config->set('catalog_theme_scene',(string)$config['scene']);
    }
}
