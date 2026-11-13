<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): acha as extensões na pasta e põe para rodar as que o
 * admin ligou. Regras que este arquivo garante:
 *
 *  - extensão DESLIGADA: nenhum PHP dela é carregado; só o extension.json é lido;
 *  - só entra em funcionamento o que o manifesto declara: os ouvintes de
 *    evento listados nele e as origens de fora listadas nele (site público);
 *  - uma extensão com defeito não derruba o site: manifesto inválido, versão
 *    incompatível, arquivo ausente, erro ao carregar ou erro dentro de um
 *    ouvinte viram uma linha no log e a página segue sem ela;
 *  - `define('REAMUR_EXTENSIONS', false);` no config.php desliga todas de uma
 *    vez, sem tocar no banco (saída de emergência).
 *
 * O que NÃO é garantido: isolamento. Uma extensão ligada é código PHP com o
 * mesmo alcance do CMS (banco, arquivos, config.php). Por isso a pasta só
 * chega ao servidor por FTP/deploy, nunca por envio pelo admin.
 */
namespace Reamur\System\Library\Extension;

use Reamur\System\Engine\Config;
use Reamur\System\Engine\Event;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Consent;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csp;
use Reamur\System\Library\Security\SecretBox;

final class Manager {
    /** Evento do site público: args = [PageOutput]. */
    public const EVENT_PAGE_OUTPUT = 'page/output';

    /** Teto de pastas lidas na tela de extensões. */
    public const MAX_FOLDERS = 200;

    private string $directory;

    private bool $booted = false;

    /** @var array<string, Extension> */
    private array $running = [];

    /** @var array<string, string> código => motivo de não ter entrado em funcionamento nesta requisição */
    private array $failed = [];

    /**
     * EX-02 (ADR 0096): extensões ligadas que dependem da escolha do
     * visitante, estejam rodando ou esperando por ela.
     *
     * @var array<string, array{name: array<string, string>, categories: list<string>, cookies: list<string>, running: bool}>
     */
    private array $consented = [];

    private ?Store $store = null;

    public function __construct(
        private Registry $registry,
        string $directory,
        private string $cmsVersion,
        private string $phpVersion = PHP_VERSION
    ) {
        $this->directory = $directory === '' ? '' : rtrim($directory, '/\\') . '/';
    }

    /** Pasta das extensões, com barra final ('' se não configurada). */
    public function directory(): string {
        return $this->directory;
    }

    public function store(): Store {
        return $this->store ??= new Store($this->registry);
    }

    public function cmsVersion(): string {
        return $this->cmsVersion;
    }

    public function phpVersion(): string {
        return $this->phpVersion;
    }

    /**
     * Valor de REAMUR_EXTENSIONS que desliga tudo: false, 0, '0', 'off',
     * 'false', 'no'. Qualquer outro valor (ou a ausência da linha) mantém.
     */
    public static function switchedOff(mixed $value): bool {
        if ($value === false || $value === 0) {
            return true;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['0', 'off', 'false', 'no'], true);
    }

    /** As extensões estão desligadas pelo config.php? */
    public function isOff(): bool {
        $config = $this->registry->get('config');

        return $config instanceof Config && (bool)$config->get('extension_off', false);
    }

    // ── tela de extensões (admin) ───────────────────────────────────────────

    /**
     * O que há na pasta, lendo SÓ os extension.json. Pasta sem esse arquivo
     * não é extensão e é ignorada.
     *
     * @return array<string, array{manifest: ?Manifest, problems: list<string>}> por nome de pasta, em ordem
     */
    public function discover(): array {
        $out = [];

        if ($this->directory === '' || !is_dir($this->directory)) {
            return $out;
        }

        $names = scandir($this->directory);

        if ($names === false) {
            return $out;
        }

        foreach ($names as $name) {
            if ($name === '' || $name[0] === '.' || !is_dir($this->directory . $name)
                || !is_file($this->directory . $name . '/' . Manifest::FILE)) {
                continue;
            }

            if (count($out) >= self::MAX_FOLDERS) {
                break;
            }

            try {
                $out[$name] = ['manifest' => Manifest::read($this->directory, $name), 'problems' => []];
            } catch (ManifestException $e) {
                $out[$name] = ['manifest' => null, 'problems' => $e->problems()];
            }
        }

        ksort($out, SORT_STRING);

        return $out;
    }

    /**
     * Manifesto de uma extensão da pasta.
     *
     * @throws ManifestException
     */
    public function manifest(string $code): Manifest {
        if ($this->directory === '') {
            throw new ManifestException(['extension folder is not configured (DIR_EXTENSION)']);
        }

        return Manifest::read($this->directory, $code);
    }

    /**
     * O que impede a extensão de ser ligada agora, sem carregar o PHP dela.
     *
     * @param array<string, mixed> $config     configuração gravada
     * @param list<string>         $secretsSet segredos que já têm valor
     * @return list<string> motivos; vazio = pode ligar
     */
    public function blockers(Manifest $manifest, array $config = [], array $secretsSet = []): array {
        $out = $manifest->incompatibilities($this->cmsVersion, $this->phpVersion);

        if (!is_file($this->directory . $manifest->code() . '/' . Manifest::ENTRY)) {
            $out[] = Manifest::ENTRY . ': file not found';
        }

        foreach ($manifest->missing($config, $secretsSet) as $key) {
            $out[] = 'setting "' . $key . '" is required and empty';
        }

        return $out;
    }

    // ── funcionamento ───────────────────────────────────────────────────────

    /**
     * Põe para rodar as extensões ligadas que pedem esta aplicação.
     * Chamado uma vez por requisição, pela pré-ação startup/extension.
     *
     * @param string $app 'catalog' ou 'admin'
     */
    public function boot(string $app): void {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if ($this->directory === '' || $this->isOff() || !in_array($app, Manifest::APPS, true)) {
            return;
        }

        try {
            $state = $this->store()->all();
        } catch (\Throwable $e) {
            self::log($this->registry, 'extensions: state could not be read, none started (' . $e->getMessage() . ')');

            return;
        }

        foreach ($state as $code => $row) {
            if (!$row['enabled']) {
                continue;
            }

            try {
                $this->start((string)$code, $row, $app);
            } catch (\Throwable $e) {
                $this->failed[(string)$code] = self::oneLine($e->getMessage());

                self::log($this->registry, 'extension ' . $code . ': not started (' . $e->getMessage() . ')');
            }
        }
    }

    /** @return list<string> códigos das extensões em funcionamento nesta requisição */
    public function running(): array {
        return array_keys($this->running);
    }

    /** @return array<string, string> código => motivo, das ligadas que não entraram em funcionamento */
    public function failed(): array {
        return $this->failed;
    }

    /**
     * EX-02 (ADR 0096): extensões ligadas, prontas para rodar, que dependem
     * da escolha do visitante. `running` diz se, nesta requisição, o
     * visitante aceitou alguma das categorias dela.
     *
     * @return array<string, array{name: array<string, string>, categories: list<string>, cookies: list<string>, running: bool}>
     */
    public function consented(): array {
        return $this->consented;
    }

    /**
     * EX-02 (ADR 0096): linhas `Set-Cookie` que apagam os cookies deixados
     * por extensões que o visitante RECUSOU (todas as categorias dela já
     * respondidas, nenhuma aceita). Fica de fora o cookie que uma extensão em
     * funcionamento para ele também declara, e a extensão que ainda espera
     * resposta.
     *
     * Vale para a gravação da escolha e para os pedidos seguintes: a etiqueta
     * que estava na página pode regravar o cookie dela enquanto a página é
     * fechada, depois de a resposta que o apagava já ter chegado.
     *
     * @param list<string|int> $names nomes dos cookies recebidos no pedido
     * @param string           $host  domínio do site (de `site_url`)
     * @param list<string>     $keep  nomes que nunca são apagados
     * @return list<string>
     */
    public function staleCookieLines(Consent $choice, array $names, string $host, array $keep = []): array {
        $refused = [];
        $in_use  = [];

        foreach ($this->consented as $entry) {
            if ($choice->allowsAny($entry['categories'])) {
                $in_use = array_merge($in_use, $entry['cookies']);
            } elseif ($choice->pending($entry['categories']) === []) {
                $refused = array_merge($refused, $entry['cookies']);
            }
        }

        if ($refused === []) {
            return [];
        }

        $stale = [];

        foreach ($names as $name) {
            $name = (string)$name;

            foreach ($in_use as $pattern) {
                if (Consent::nameMatches($name, $pattern)) {
                    continue 2;
                }
            }

            $stale[] = $name;
        }

        return Consent::expireLines($stale, array_values(array_unique($refused)), $host, $keep);
    }

    /**
     * Categorias de consentimento em uso no site agora, na ordem de
     * Consent::CATEGORIES. Vazio = nenhuma extensão ligada pede aceitação, e
     * o aviso de cookies não aparece.
     *
     * @return list<string>
     */
    public function consentCategories(): array {
        $used = [];

        foreach ($this->consented as $entry) {
            foreach ($entry['categories'] as $category) {
                $used[$category] = true;
            }
        }

        return array_values(array_filter(Consent::CATEGORIES, static fn (string $category): bool => isset($used[$category])));
    }

    /**
     * @param array{enabled: bool, config: array<string, mixed>, secrets: array<string, string>} $row
     */
    private function start(string $code, array $row, string $app): void {
        $manifest = Manifest::read($this->directory, $code);

        if (!in_array($app, $manifest->apps(), true)) {
            return;
        }

        $blockers = $this->blockers($manifest, $row['config'], array_keys($row['secrets']));

        if ($blockers !== []) {
            throw new \RuntimeException(implode('; ', $blockers));
        }

        // EX-02 (ADR 0096): extensão que declara categorias de consentimento
        // só roda para o visitante que aceitou pelo menos uma delas. Para os
        // demais ela conta como desligada: o PHP dela não é carregado, nenhum
        // ouvinte é registrado e nenhuma origem entra na política. Sem o
        // serviço `consent` no registro (não deveria acontecer no catálogo),
        // vale a resposta segura: ninguém aceitou.
        if ($manifest->consent() !== []) {
            $consent = $this->registry->get('consent');
            $allowed = $consent instanceof Consent && $consent->allowsAny($manifest->consent());

            $this->consented[$code] = [
                'name'       => $manifest->names(),
                'categories' => $manifest->consent(),
                'cookies'    => $manifest->cookies(),
                'running'    => $allowed,
            ];

            if (!$allowed) {
                return;
            }
        }

        // Só agora o PHP da extensão é carregado. O arquivo de entrada é
        // incluído pelo caminho fixo <pasta>/<código>/extension.php (o código
        // já passou por Manifest::isCode: sem barra, sem ponto).
        $class = Manifest::className($code);

        if (!class_exists($class, false)) {
            $entry = $this->directory . $code . '/' . Manifest::ENTRY;

            // Qualquer coisa antes da abertura do PHP (o caso comum é o
            // editor gravar o arquivo com BOM) faz o `declare(strict_types=1)`
            // ou o `namespace` falhar na compilação, com um erro fatal que
            // nenhum try/catch segura. Conferido antes de incluir.
            if (!is_file($entry) || @file_get_contents($entry, false, null, 0, 5) !== '<?php') {
                throw new \RuntimeException(Manifest::ENTRY . ' must exist and start with "<?php" (no BOM, no text before it)');
            }

            // Texto solto no arquivo (sobra depois do fechamento do PHP, um
            // echo fora de método) não pode chegar ao navegador antes dos
            // cabeçalhos da resposta.
            $level = ob_get_level();
            ob_start();

            try {
                require_once $entry;
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }

        if (!class_exists($class, false) || !is_subclass_of($class, Extension::class)) {
            throw new \RuntimeException(Manifest::ENTRY . ' must declare ' . $class . ' extending ' . Extension::class);
        }

        // Todos os ouvintes são conferidos ANTES de qualquer registro: ou a
        // extensão entra inteira, ou não entra.
        foreach ($manifest->events() as $event) {
            if (!method_exists($class, $event['handler']) || !(new \ReflectionMethod($class, $event['handler']))->isPublic()
                || (new \ReflectionMethod($class, $event['handler']))->isStatic()) {
                throw new \RuntimeException('handler ' . $event['handler'] . ' is not a public method of ' . $class);
            }
        }

        $box      = $this->registry->get('secret_box');
        $box      = $box instanceof SecretBox ? $box : new SecretBox();
        $settings = $manifest->resolve($row['config']);
        $locked   = [];

        foreach ($manifest->secretKeys() as $key) {
            $stored  = (string)($row['secrets'][$key] ?? '');
            $context = SecretBox::extensionContext($code, $key);

            if ($stored !== '' && $box->locked($stored, $context)) {
                $locked[]       = $key;
                $settings[$key] = '';

                continue;
            }

            $settings[$key] = $stored === '' ? '' : $box->open($stored, $context);
        }

        $instance = new $class($this->registry, $manifest, $settings, $app, $locked);

        $events = $this->registry->get('event');

        if ($events instanceof Event) {
            $scriptSources = $manifest->csp()['script-src'] ?? [];

            foreach ($manifest->events() as $event) {
                $events->register(
                    $event['trigger'],
                    $this->listener($instance, $code, $event['trigger'], $event['handler'], $scriptSources),
                    $event['priority']
                );
            }
        }

        // Origens de fora: só no site público (Csp::addSources ignora o admin).
        if ($app === 'catalog' && $manifest->csp() !== []) {
            $csp      = $this->registry->get('csp');
            $response = $this->registry->get('response');

            if ($csp instanceof Csp && $csp->addSources($manifest->csp()) !== [] && $response instanceof Response) {
                $header = $csp->header();

                if ($header !== '') {
                    $response->setHeader($header);
                }
            }
        }

        $this->running[$code] = $instance;
    }

    /**
     * Embrulha um ouvinte de extensão: o erro dele vai para o log e o evento
     * segue para os demais ouvintes e para o núcleo.
     *
     * @param list<string> $scriptSources
     */
    private function listener(Extension $instance, string $code, string $trigger, string $handler, array $scriptSources): \Closure {
        $registry = $this->registry;

        return static function (array &$args) use ($instance, $code, $trigger, $handler, $scriptSources, $registry): void {
            $page = ($args[0] ?? null) instanceof PageOutput ? $args[0] : null;

            if ($page !== null) {
                $page->actingAs($code, $scriptSources);
            }

            // Um echo dentro do ouvinte sairia antes dos cabeçalhos da resposta
            // (que levam a política de conteúdo) e fora do PageOutput: é
            // descartado, com uma linha no log para quem escreveu a extensão.
            $level = ob_get_level();
            $stray = 0;
            ob_start();

            try {
                // array_values() entrega CÓPIAS: a extensão observa o evento, não
                // troca os dados que o núcleo usa depois (objetos, como o
                // PageOutput, continuam sendo o mesmo objeto).
                $instance->{$handler}(...array_values($args));
            } catch (\Throwable $e) {
                self::log(
                    $registry,
                    'extension ' . $code . ': error in ' . $handler . ' (' . $trigger . '): ' . $e->getMessage()
                    . ' at ' . basename($e->getFile()) . ':' . $e->getLine()
                );
            } finally {
                while (ob_get_level() > $level) {
                    $stray += (int)ob_get_length();
                    ob_end_clean();
                }

                if ($page !== null) {
                    $page->actingAs('');
                }
            }

            if ($stray > 0) {
                self::log($registry, 'extension ' . $code . ': ' . $stray . ' bytes printed by ' . $handler . ' (' . $trigger . ') were discarded');
            }
        };
    }

    // ── log ─────────────────────────────────────────────────────────────────

    /** Texto vindo de extensão numa linha só, sem caracteres de controle, com tamanho limitado. */
    public static function oneLine(string $message): string {
        $message = (string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message);

        return strlen($message) > 600 ? substr($message, 0, 600) . '...' : $message;
    }

    public static function log(Registry $registry, string $message): void {
        $log = $registry->get('log');

        if (is_object($log) && method_exists($log, 'write')) {
            $log->write(self::oneLine($message));
        }
    }
}
