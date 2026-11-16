<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Document;
use Reamur\System\Library\Language;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\TokenRegistry;
use Reamur\System\Library\Url;

/**
 * KZ-17 (ADR 0110): pendencias do editor de tema.
 *
 *  - Salvar continua no editor (antes levava para a lista de temas, sem aviso):
 *    mesmo tema, mesmo modulo, mesma tela (normal ou pagina cheia), com o aviso
 *    "Rascunho salvo".
 *  - As listas da parte "Quando" das condicoes (tipo de grupo, campo, operador)
 *    mostram o nome no idioma do admin; o valor gravado continua o tecnico.
 *  - O editor de blocos do blog chama o editor de tema pelo nome atual.
 *
 * O controller roda de verdade, com um modelo de mentira no lugar do banco.
 */
final class KazSaveAndWhenNamesTest extends TestCase {
    private const LANGUAGES = ['pt-br', 'en-us', 'es-eu'];
    private const TWIG = 'dev/admin/view/template/design/kaz_theme_editor.twig';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const EDITOR = 'dev/admin/view/js/kaz-theme-editor.js';
    private const BUILDER = 'dev/admin/view/js/theme-condition-builder.js';
    private const BUILDER_CSS = 'dev/admin/view/css/theme-condition-builder.css';
    private const BASE = 'Location: https://example.test/admin/index.php?route=design/theme';
    private const FIELDS = ['route', 'path', 'language', 'device', 'mobile', 'logged_in', 'user_group', 'hour', 'weekday', 'date', 'month', 'variant', 'https', 'ajax', 'query', 'site'];
    private const OPERATORS = ['eq', 'neq', 'in', 'not_in', 'contains', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte', 'between', 'exists', 'empty', 'truthy', 'falsy'];
    /** Escritos aqui, e nao so contados: uma traducao trocada, vazia ou deixada em outro idioma aparece. */
    private const WHEN_TEXTS = [
        'pt-br' => [
            'fields' => ['Rota', 'Caminho da URL', 'Idioma', 'Dispositivo', 'Celular ou tablet', 'Usuário logado', 'Grupo do usuário', 'Hora (0 a 23)', 'Dia da semana (1 = segunda)',
                'Data (AAAA-MM-DD)', 'Mês (1 a 12)', 'Variante', 'Conexão HTTPS', 'Requisição AJAX', 'Parâmetros da URL', 'Site'],
            'query_key' => 'Parâmetro da URL: %s',
            'operators' => ['é igual a', 'é diferente de', 'está na lista', 'não está na lista', 'contém', 'começa com', 'termina com', 'é maior que', 'é maior ou igual a',
                'é menor que', 'é menor ou igual a', 'está entre', 'existe', 'está vazio', 'é verdadeiro', 'é falso'],
            'groups' => ['E (todas)', 'OU (qualquer)', 'NÃO (nenhuma)'],
            'saved' => 'Rascunho salvo. Publique quando quiser levar as mudanças ao site.',
        ],
        'en-us' => [
            'fields' => ['Route', 'URL path', 'Language', 'Device', 'Phone or tablet', 'Logged-in user', 'User group', 'Hour (0 to 23)', 'Weekday (1 = Monday)',
                'Date (YYYY-MM-DD)', 'Month (1 to 12)', 'Variant', 'HTTPS connection', 'AJAX request', 'URL parameters', 'Site'],
            'query_key' => 'URL parameter: %s',
            'operators' => ['is equal to', 'is not equal to', 'is in the list', 'is not in the list', 'contains', 'starts with', 'ends with', 'is greater than',
                'is greater than or equal to', 'is less than', 'is less than or equal to', 'is between', 'exists', 'is empty', 'is true', 'is false'],
            'groups' => ['AND (all)', 'OR (any)', 'NOT (none)'],
            'saved' => 'Draft saved. Publish when you want the changes on the site.',
        ],
        'es-eu' => [
            'fields' => ['Ruta', 'Dirección de la URL', 'Idioma', 'Dispositivo', 'Móvil o tableta', 'Usuario con sesión', 'Grupo de usuario', 'Hora (0 a 23)', 'Día de la semana (1 = lunes)',
                'Fecha (AAAA-MM-DD)', 'Mes (1 a 12)', 'Variante', 'Conexión HTTPS', 'Petición AJAX', 'Parámetros de la URL', 'Sitio'],
            'query_key' => 'Parámetro de la URL: %s',
            'operators' => ['es igual a', 'es distinto de', 'está en la lista', 'no está en la lista', 'contiene', 'empieza por', 'termina en', 'es mayor que', 'es mayor o igual que',
                'es menor que', 'es menor o igual que', 'está entre', 'existe', 'está vacío', 'es verdadero', 'es falso'],
            'groups' => ['Y (todas)', 'O (cualquiera)', 'NO (ninguna)'],
            'saved' => 'Borrador guardado. Publique cuando quiera llevar los cambios al sitio.',
        ],
    ];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @return array<string,string> */
    private function language(string $code): array {
        $_ = [];
        require DIR_REAMUR . 'dev/admin/language/' . $code . '/design/theme.php';

        return array_filter($_, 'is_string');
    }

    /**
     * O controller de verdade, com o banco trocado por um modelo de mentira.
     *
     * @param array<string,string> $texts textos do idioma (vazio = idioma sem os textos da etapa)
     * @return array{theme:AdminThemeController,request:Request,response:Response,load:object,model:object,session:Session}
     */
    private function editor(array $texts = [], int $newId = 7, bool $slugTaken = false): array {
        $registry = new Registry();
        $language = new Language('en-us');
        foreach ($texts as $key => $text) $language->set($key, $text);
        $engine = new ConditionEngine();
        $tokens = new TokenRegistry();
        $package = new ThemePackage($engine, $tokens);
        $registry->set('language', $language);
        $registry->set('cms_page_layout', new \Reamur\System\Library\Cms\PageLayout());
        $registry->set('theme_condition_engine', $engine);
        $registry->set('theme_token_registry', $tokens);
        $registry->set('theme_section_registry', new \Reamur\System\Library\Theme\SectionRegistry($registry->get('cms_page_layout')));
        $registry->set('theme_component_registry', new \Reamur\System\Library\Theme\ComponentRegistry());
        $registry->set('theme_schedule', new \Reamur\System\Library\Theme\ThemeSchedule());
        $registry->set('theme_media', new \Reamur\System\Library\Theme\ThemeMedia());
        $registry->set('theme_sites', new \Reamur\System\Library\Theme\ThemeSites());
        $registry->set('theme_package', $package);
        $registry->set('three_scene_config', new \Reamur\System\Library\Three\SceneConfig());
        $registry->set('three_asset_manifest', new \Reamur\System\Library\Three\AssetManifest());
        $registry->set('three_scene_preset', new \Reamur\System\Library\Three\ScenePresetRegistry());
        $registry->set('theme_snapshot', new \Reamur\System\Library\Theme\ThemeSnapshot($engine, $tokens, $package));

        /** @var Session $session */
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];
        $model = new class($newId, $slugTaken) {
            /** @var list<int> */
            public array $saves = [];
            public function __construct(private int $newId, private bool $slugTaken) {}
            /** @return array<string, mixed> */
            public function getTheme(int $id): array { return ['theme_id' => $id, 'name' => 'Tema', 'slug' => 'tema', 'draft_config' => '{}']; }
            public function slugExists(string $slug, int $id): bool { return $this->slugTaken; }
            /** Como o modelo de verdade: devolve o ID do tema gravado (o novo, quando o tema nao existia). */
            public function save(int $id, mixed ...$rest): int { $this->saves[] = $id; return $id > 0 ? $id : $this->newId; }
            /** @param array<int, mixed> $args */
            public function __call(string $name, array $args): mixed { return []; }
        };
        $load = new class {
            /** @var array<string, mixed> */
            public array $data = [];
            public function language(string $route): void {}
            public function model(string $route): void {}
            public function controller(string $route, mixed ...$args): string { return ''; }
            /** @param array<string, mixed> $data */
            public function view(string $route, array $data = []): string { $this->data = $data; return ''; }
        };
        $request = new Request();
        $response = new Response();
        $config = new Config();
        $config->set('site_url', 'https://example.test/admin/');
        $registry->set('config', $config);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('url', new Url('https://example.test/admin/'));
        $registry->set('document', new Document());
        $registry->set('session', $session);
        $registry->set('load', $load);
        $registry->set('model_design_theme', $model);
        $registry->set('user', new class {
            public function hasPermission(string $type, string $route): bool { return true; }
            public function getUserId(): int { return 1; }
        });

        return ['theme' => new AdminThemeController($registry), 'request' => $request, 'response' => $response, 'load' => $load, 'model' => $model, 'session' => $session];
    }

    /**
     * Um salvar aceito, com o endereco do pedido levando `$query`.
     *
     * @param array<string,mixed> $query
     * @return list<string> os cabecalhos da resposta
     */
    private function save(string $themeId, array $query, int $newId = 7): array {
        $e = $this->editor([], $newId);
        $e['request']->server['REQUEST_METHOD'] = 'POST';
        $e['request']->get = $query;
        $e['request']->post = ['csrf_token' => Csrf::token($e['session']), 'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
        $e['theme']->edit($themeId);
        self::assertSame([(int)$themeId], $e['model']->saves, 'o tema foi gravado uma vez');
        self::assertSame([], $e['load']->data, 'salvar aceito redireciona, nao desenha o formulario');
        $location = array_values(array_filter($e['response']->getHeaders(), static fn (string $h): bool => str_starts_with($h, 'Location:')));
        self::assertCount(1, $location);

        return $location;
    }

    /**
     * Abrir a tela (GET) com `$query` no endereco.
     *
     * @param array<string,mixed> $query
     * @param array<string,string> $texts
     * @return array<string,mixed> o que o controller entrega ao Twig
     */
    private function open(array $query, array $texts = [], string $method = 'GET'): array {
        $e = $this->editor($texts);
        $e['request']->server['REQUEST_METHOD'] = $method;
        $e['request']->get = $query;
        $e['theme']->edit('1');
        self::assertSame([], $e['model']->saves);

        return $e['load']->data;
    }

    public function testSavingKeepsThePersonInTheEditor(): void {
        // Antes: `Location: …?route=design/theme` (a lista), sem aviso nenhum.
        self::assertSame([self::BASE . '.edit|1&saved=1'], $this->save('1', []));
        self::assertSame([self::BASE . '.edit|1&saved=1&kaz_module=tokens'], $this->save('1', ['kaz_module' => 'tokens']));
        self::assertSame([self::BASE . '.edit|1&saved=1&kaz_fullscreen=1'], $this->save('1', ['kaz_fullscreen' => '1']));
        self::assertSame([self::BASE . '.edit|1&saved=1&kaz_module=condicoes&kaz_fullscreen=1'], $this->save('1', ['kaz_fullscreen' => '1', 'kaz_module' => 'condicoes']));
        // So "1" liga a pagina cheia.
        self::assertSame([self::BASE . '.edit|1&saved=1'], $this->save('1', ['kaz_fullscreen' => 'yes']));
        self::assertSame([self::BASE . '.edit|42&saved=1&kaz_module=geral'], $this->save('42', ['kaz_module' => 'geral']));
    }

    public function testSavingANewThemeOpensTheEditorOfTheThemeJustCreated(): void {
        self::assertSame([self::BASE . '.edit|7&saved=1&kaz_module=geral'], $this->save('0', ['kaz_module' => 'geral'], 7));
        self::assertSame([self::BASE . '.edit|913&saved=1'], $this->save('0', [], 913));
    }

    /** @return array<string, array{mixed}> */
    public static function badModules(): array {
        return [
            'maiuscula' => ['Tokens'], 'numero' => ['tokens2'], 'espaco' => ['to kens'], 'quebra de linha' => ["tok\nens"], 'aspas' => ['tokens"onfocus="x'],
            'e comercial' => ['tokens&kaz_fullscreen=1'], 'barra' => ['../theme'], 'hifen' => ['pagina-x'], '25 letras' => [str_repeat('a', 25)],
            'vazio' => [''], 'lista' => [['tokens']], 'acento' => ['mídia'], 'cerquilha' => ['tokens#x'],
        ];
    }

    #[DataProvider('badModules')]
    public function testOnlyAPlainModuleNameTravelsInTheAddress(mixed $module): void {
        self::assertSame([self::BASE . '.edit|1&saved=1'], $this->save('1', ['kaz_module' => $module]));
        self::assertSame([self::BASE . '.edit|1&saved=1&kaz_fullscreen=1'], $this->save('1', ['kaz_module' => $module, 'kaz_fullscreen' => '1']));
        self::assertSame('', $this->open(['kaz_module' => $module])['kaz_start_module']);
    }

    public function testTheEditorOpensInTheModuleNamedInTheAddress(): void {
        self::assertSame('', $this->open([])['kaz_start_module']);
        foreach (['tokens', 'condicoes', 'templates', 'a', str_repeat('a', 24)] as $module) {
            self::assertSame($module, $this->open(['kaz_module' => $module])['kaz_start_module']);
        }
        // O valor passa por trim() antes de ser conferido.
        self::assertSame('tokens', $this->open(['kaz_module' => " tokens\n"])['kaz_start_module']);
    }

    public function testTheSavedNoticeShowsOnlyAfterASave(): void {
        $texts = ['text_saved_ok' => 'SALVO', 'text_import_ok' => 'IMPORTADO', 'text_import_error' => 'ERRO'];
        self::assertSame('', $this->open([], $texts)['notice']);
        self::assertSame('SALVO', $this->open(['saved' => '1'], $texts)['notice']);
        self::assertSame('SALVO', $this->open(['saved' => '1', 'kaz_module' => 'tokens', 'kaz_fullscreen' => '1'], $texts)['notice']);
        self::assertSame('', $this->open(['saved' => '0'], $texts)['notice']);
        self::assertSame('', $this->open(['saved' => 'true'], $texts)['notice']);
        // O aviso de importacao e o erro de importacao vem antes.
        self::assertSame('IMPORTADO', $this->open(['saved' => '1', 'import_ok' => '1'], $texts)['notice']);
        $failed = $this->open(['saved' => '1', 'import_error' => 'json_invalid'], $texts);
        self::assertSame('', $failed['notice']);
        self::assertSame('ERRO (json_invalid)', $failed['error']);
    }

    public function testARefusedSaveStaysOnTheScreenAndNeverSaysSaved(): void {
        $e = $this->editor(['text_saved_ok' => 'SALVO', 'error_slug_exists' => 'SLUG REPETIDO'], 7, true);
        $e['request']->server['REQUEST_METHOD'] = 'POST';
        $e['request']->get = ['saved' => '1', 'kaz_module' => 'geral', 'kaz_fullscreen' => '1'];
        $e['request']->post = ['csrf_token' => Csrf::token($e['session']), 'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
        $e['theme']->edit('1');
        self::assertSame([], $e['model']->saves);
        self::assertSame([], array_values(array_filter($e['response']->getHeaders(), static fn (string $h): bool => str_starts_with($h, 'Location:'))));
        self::assertSame('SLUG REPETIDO', $e['load']->data['error']);
        self::assertSame('', $e['load']->data['notice']);
        // A tela volta no mesmo modulo e na mesma tela, com o erro.
        self::assertSame('geral', $e['load']->data['kaz_start_module']);
        self::assertTrue($e['load']->data['kaz_fullscreen_page']);
    }

    public function testASaveWithoutAValidTokenIsStillRefused(): void {
        $e = $this->editor(['error_csrf' => 'TOKEN']);
        $e['request']->server['REQUEST_METHOD'] = 'POST';
        $e['request']->get = ['kaz_module' => 'tokens'];
        $e['request']->post = ['csrf_token' => 'errado', 'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
        $e['theme']->edit('1');
        self::assertSame([], $e['model']->saves);
        self::assertSame('TOKEN', $e['load']->data['error']);
        self::assertSame([], array_values(array_filter($e['response']->getHeaders(), static fn (string $h): bool => str_starts_with($h, 'Location:'))));
    }

    public function testEveryFieldAndOperatorOfTheEngineHasANameInEachLanguage(): void {
        $spec = (new ConditionEngine())->spec();
        // A lista abaixo e a do motor: um campo ou operador novo no motor pede o nome nos tres idiomas.
        self::assertSame(self::FIELDS, $spec['fields']);
        self::assertSame(self::OPERATORS, $spec['operators']);
        self::assertSame(['all', 'any', 'not'], $spec['groups']);
        foreach (self::LANGUAGES as $code) {
            $texts = $this->language($code);
            $want = self::WHEN_TEXTS[$code];
            foreach (self::FIELDS as $i => $field) self::assertSame($want['fields'][$i], $texts['text_builder_field_' . $field] ?? null, $code . ' ' . $field);
            foreach (self::OPERATORS as $i => $op) self::assertSame($want['operators'][$i], $texts['text_builder_op_' . $op] ?? null, $code . ' ' . $op);
            foreach (['all', 'any', 'not'] as $i => $group) self::assertSame($want['groups'][$i], $texts['text_builder_group_' . $group] ?? null, $code . ' ' . $group);
            self::assertSame($want['query_key'], $texts['text_builder_field_query_key'] ?? null, $code);
            self::assertSame(1, substr_count($want['query_key'], '%s'), $code . ': um lugar para o nome do parametro');
            self::assertSame($want['saved'], $texts['text_saved_ok'] ?? null, $code);
            // Duas opcoes da mesma lista com o mesmo texto nao se distinguem.
            self::assertCount(count(self::FIELDS), array_unique($want['fields']), $code);
            self::assertCount(count(self::OPERATORS), array_unique($want['operators']), $code);
            // Nenhum texto sobrando para um campo ou operador que o motor nao tem.
            $extra = array_filter(array_keys($texts), static fn (string $k): bool => preg_match('/^text_builder_(field|op)_/', $k) === 1);
            self::assertCount(count(self::FIELDS) + 1 + count(self::OPERATORS), $extra, $code);
        }
        // Um idioma nao e copia do outro: so coincidem os nomes que sao iguais nas duas linguas.
        self::assertCount(0, array_intersect(self::WHEN_TEXTS['pt-br']['operators'], self::WHEN_TEXTS['en-us']['operators']));
        self::assertSame(['está entre', 'existe'], array_values(array_intersect(self::WHEN_TEXTS['es-eu']['operators'], self::WHEN_TEXTS['pt-br']['operators'])));
    }

    public function testTheScreenReceivesTheNamesOfTheWhenLists(): void {
        foreach (self::LANGUAGES as $code) {
            $labels = json_decode((string)$this->open([], $this->language($code))['builder_labels_json'], true);
            self::assertIsArray($labels);
            $want = self::WHEN_TEXTS[$code];
            foreach (self::FIELDS as $i => $field) self::assertSame($want['fields'][$i], $labels['field_' . $field] ?? null, $code . ' ' . $field);
            foreach (self::OPERATORS as $i => $op) self::assertSame($want['operators'][$i], $labels['op_' . $op] ?? null, $code . ' ' . $op);
            foreach (['all', 'any', 'not'] as $i => $group) self::assertSame($want['groups'][$i], $labels['group_' . $group] ?? null, $code . ' ' . $group);
            self::assertSame($want['query_key'], $labels['field_query_key'] ?? null);
            self::assertCount(33, array_filter(array_keys($labels), static fn (string $k): bool => preg_match('/^(field|op)_/', $k) === 1), $code);
        }
    }

    public function testALanguageWithoutTheNamesShowsTheTechnicalValue(): void {
        // Quem mantem um quarto idioma e ainda nao copiou as chaves: a chave nao vai para a tela;
        // o script, sem texto para o item, mostra o valor tecnico (conferido em check_condition_builder_labels.js).
        $labels = json_decode((string)$this->open([], ['text_builder_field_route' => 'Rota', 'text_builder_op_eq' => '', 'text_builder_cond_field' => 'Campo'])['builder_labels_json'], true);
        self::assertIsArray($labels);
        self::assertSame(['field_route'], array_values(array_filter(array_keys($labels), static fn (string $k): bool => preg_match('/^(field|op)_/', $k) === 1)));
        self::assertSame('Rota', $labels['field_route']);
        self::assertSame('Campo', $labels['cond_field']);
        foreach ($labels as $key => $text) {
            if (preg_match('/^(field|op)_/', (string)$key) === 1) self::assertStringStartsNotWith('text_builder_', (string)$text);
        }
    }

    public function testTheWhenListsTakeTheirTextFromTheScreen(): void {
        $script = $this->repo(self::BUILDER);
        self::assertStringContainsString("label: L('field_' + f, f)", $script);
        self::assertStringContainsString("L('field_query_key', 'query.%s').replace('%s', k)", $script);
        self::assertStringContainsString("label: L('group_' + g, g)", $script);
        self::assertStringContainsString("label: L('op_' + o, o)", $script);
        self::assertSame(1, substr_count($script, 'sel(groupOptions(), kind,'));
        self::assertSame(1, substr_count($script, "sel(opOptions(), node.op || 'eq',"));
        self::assertSame(1, substr_count($script, "sel(fieldOptions(), node.field || 'route',"));
        // O selo que repetia o tipo do grupo saiu do script e da folha.
        self::assertStringNotContainsString('tcb-group__badge', $script);
        $css = $this->repo(self::BUILDER_CSS);
        self::assertStringNotContainsString('tcb-group__badge', $css);
        // A linha de uma condicao deixa cada lista com a largura do proprio texto e quebra quando nao cabe.
        self::assertMatchesRegularExpression('/\.tcb-cond \{ display: flex; flex-wrap: wrap;/', $css);
        self::assertMatchesRegularExpression('/\.tcb-cond__field, \.tcb-cond__op \{ width: auto; max-width: 100%; flex: 0 1 auto; \}/', $css);
        self::assertMatchesRegularExpression('/\.tcb-cond__value \{ min-width: 0; flex: 1 1 140px; \}/', $css);
        // tela estreita: cada lista ocupa a linha inteira (a regra antiga mexia em colunas de grade que nao existem mais)
        self::assertMatchesRegularExpression('/@media \(max-width: 767\.98px\) \{\s+\.tcb-cond__field, \.tcb-cond__op \{ flex: 1 1 100%; \}/', $css);
        self::assertStringNotContainsString('.tcb-cond { grid-template-columns', $css);
        self::assertStringNotContainsString('grid-template-columns: minmax(120px, 1.1fr)', $css);
    }

    public function testTheFormAndTheScriptCarryModuleAndScreenMode(): void {
        $twig = $this->repo(self::TWIG);
        self::assertSame(1, substr_count($twig, '<form id="kaz-theme-form" method="post" action="{{ action }}{% if kaz_fullscreen_page %}&amp;kaz_fullscreen=1{% endif %}">'));
        self::assertSame(1, substr_count($twig, 'data-kaz-start-module="{{ kaz_start_module|e(\'html_attr\') }}"'));
        // Nenhum campo novo no formulario: o modulo vai no endereco.
        self::assertStringNotContainsString('name="kaz_module"', $twig);
        $script = $this->repo(self::EDITOR);
        self::assertStringContainsString("var startModule = root.getAttribute('data-kaz-start-module') || '';", $script);
        self::assertStringContainsString("if (!(startModule && setModule(startModule)) && root.querySelector('[data-kaz-module-panel=\"pagina\"]')) setModule('pagina');", $script);
        self::assertStringContainsString("    if (saveAction) form.setAttribute('action', saveAction + (saveAction.indexOf('?') < 0 ? '?' : '&') + 'kaz_module=' + encodeURIComponent(stateModule));", $script);
        // O destino depois de salvar e montado no servidor; do pedido so entram dois sinais conferidos.
        $controller = $this->repo(self::CONTROLLER);
        self::assertStringNotContainsString("if (\$saved) { \$this->response->redirect(\$this->url->link('design/theme')); return; }", $controller);
        self::assertSame(2, substr_count($controller, "preg_match('/^[a-z]{1,24}\$/D',"));
    }

    public function testBlockNamesAndTheBlogEditorUseTheCurrentSpelling(): void {
        $script = $this->repo(self::EDITOR);
        self::assertStringContainsString("card: { label: 'Cartão', layers: [", $script);
        self::assertStringContainsString("titulo: { label: 'Título', layers: [", $script);
        self::assertSame(0, preg_match_all("/label: '(Cartao|Titulo)'/", $script));
        $blog = $this->repo('dev/admin/view/js/blog-block-editor-kaz.js');
        self::assertSame(2, substr_count($blog, "'Padrão KAZEditor-Theme ativo'"));
        self::assertSame(0, preg_match_all("/'[^'\\n]*KAZTheme-Editor[^'\\n]*'/", $blog), 'o nome antigo nao aparece em texto de tela');
    }

    public function testChangedFilesGotANewAddress(): void {
        $controller = $this->repo(self::CONTROLLER);
        self::assertMatchesRegularExpression("/'-theme-builder-[e-z]'/", $controller);
        self::assertMatchesRegularExpression("/'-kaz-theme-editor-kz(1[7-9]|[2-9]\\d)'/", $controller);
        foreach (['dev/admin/controller/cms/page.php', 'dev/admin/controller/blog/blog.php'] as $path) {
            self::assertMatchesRegularExpression("/\\\$kazBdreAsset = .*'-kaz-bdre-kz(1[7-9]|[2-9]\\d)';/", $this->repo($path), $path);
        }
    }

    public function testWhenListCheckerPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node tests/check_condition_builder_labels.js` à mão.');
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . 'tests/check_condition_builder_labels.js') . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", array_slice($output, 0, 12)));
        self::assertMatchesRegularExpression('/^Condition builder labels checker: \d{4,} PASS$/', (string)end($output));
        $checker = $this->repo('tests/check_condition_builder_labels.js');
        self::assertStringContainsString("'lista do campo: o texto vem da tela; sem texto, mostra o valor (", $checker);
        self::assertStringContainsString("'nao ha selo ao lado da lista do grupo'", $checker);
    }
}
