<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/language (Adendo F2-3 do ADR 0001): idioma da requisição.
 * Ordem de decisão: ?language=codigo validado contra os diretórios existentes
 * → persiste na sessão; senão sessão; senão o padrão da app (en-us).
 * Substitui o serviço language montado pelo framework (contrato estável).
 * F13-P: a decisao migrou para LanguageNegotiator em startup/theme;
 * esta pre-acao aplica o idioma efetivo (K) e fecha a limitacao da L.
 * Nao toca no banco: a tabela rms_language entra na governança da UI de
 * idiomas (tarefa futura), aqui o filesystem é a fonte da verdade.
 */
namespace Reamur\Catalog\Controller\Startup;

class Language extends \Reamur\System\Engine\Controller {

    /**
     * Aplica o idioma ativo (silent fallback — nunca explode erro).
     * F4-02 (ADR 0025) continua governando os codigos publicaveis; a selecao
     * é feita uma unica vez em LanguageNegotiator::resolve() pela pre-acao
     * startup/theme, usando getEnabledCodes() (status=1 + diretorio real).
     */
    public function index(): void {
        // F13-P: startup/theme (que roda ANTES) ja resolveu o idioma efetivo
        // com LanguageNegotiator e gravou a sessao. Aqui apenas APLICAMOS o
        // resultado: sem segunda decisao, sem segunda leitura e sem escrita.
        // A precedencia ?language= > sessao > site > default (K) fica intacta.
        $code = \Reamur\System\Library\LanguageNegotiator::clean(
            (string)$this->config->get('catalog_theme_language_effective', '')
        );
        // Fail-closed para instalacao parcial (theme sem a P): cai no default
        // allowlistado do painel/configfile, nunca em texto livre do request.
        if (!\Reamur\System\Library\LanguageNegotiator::isCode($code)) {
            $code = \Reamur\System\Library\LanguageNegotiator::clean(
                (string)$this->config->get('config_language_catalog',
                    (string)$this->config->get('language_directory', 'en-us'))
            );
        }
        if (!\Reamur\System\Library\LanguageNegotiator::isCode($code)) $code = 'en-us';

        $source = (string)$this->config->get('catalog_theme_language_source', 'default');
        if (!in_array($source, \Reamur\System\Library\LanguageNegotiator::SOURCES, true)) {
            $source = 'default';
        }
        // Origem allowlistada (rastreabilidade, sem dado do visitante).
        $this->config->set('catalog_theme_language_source', $source);

        // Substitui o idioma montado no framework pelo escolhido para a requisicao
        $this->registry->set('language', new \Reamur\System\Library\Language($code));

        $this->config->set('language_directory', $code);
    }
}
