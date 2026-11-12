<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Sandbox das fontes Twig de tema (ADR 0087, S2).
 *
 * Fonte de tema e ENTRADA NAO CONFIAVEL: vem de um formulario do admin ou de
 * um pacote `.reamurtheme.json` importado. Antes deste arquivo, so a previa e
 * o canvas do editor usavam Twig Sandbox; o site publicado renderizava a mesma
 * fonte num Environment comum, protegido apenas por uma regex. Resultado:
 * `{{ constant('DB_PASSWORD') }}` imprimia a senha do banco e
 * `{{ ['id']|map('system')|join }}` executava comando no servidor.
 *
 * Regras deste arquivo:
 *  1. UMA politica (tags, filtros, funcoes) para previa, canvas, previa de
 *     pagina do CMS e site publicado. O que o editor mostra e o que o site
 *     renderiza nao podem divergir.
 *  2. Toda fonte de tema roda num Environment DEDICADO e sempre em sandbox.
 *     Tudo o que ela alcanca por include tambem roda em sandbox, inclusive os
 *     templates padrao (uma fonte de tema nao pode usar um template confiavel
 *     como procurador).
 *  3. O Environment confiavel (arquivos do sistema) nunca enxerga fonte de
 *     tema. Quem precisa de um trecho do tema pede a este arquivo.
 *  4. Violacao da politica nao derruba a pagina: a fonte culpada sai, o
 *     template padrao entra no lugar e o motivo e reportado.
 */
namespace Reamur\System\Library\Template;

final class ThemeSandbox {
    /** Limite de bytes por fonte (o mesmo de ThemePackage). */
    public const MAX_SOURCE = 131072;

    /** Tags, filtros e funcoes liberados para fonte de tema (ADR 0080/0083/0087). */
    public const TAGS      = ['if', 'for', 'set', 'block', 'extends', 'include'];
    public const FILTERS   = ['escape', 'e', 'default', 'join', 'length', 'upper', 'lower', 'title', 'trim', 'date', 'json_encode', 'slice', 'nl2br'];
    public const FUNCTIONS = ['range', 'cycle', 'parent'];

    /**
     * Funcao que os arquivos CONFIAVEIS do sistema usam para pedir um trecho do
     * tema (common/header.twig → partial/site_header). O adaptador registra a
     * versao real so no Environment confiavel. Aqui ela existe como casca e
     * esta FORA da politica: fonte de tema que a chame, ou que inclua um
     * arquivo que a chame, e barrada antes de executar.
     */
    public const TRUSTED_PARTIAL = 'theme_partial';

    /**
     * Camada de HTML, independente do Twig: o sandbox nao sabe o que e
     * `<script>` nem `onclick=`. NAO e a defesa contra Twig perigoso: a regex
     * de `|raw` abaixo e contornavel (`{{ x|# c` + quebra de linha + `raw }}`);
     * quem barra `raw` de verdade e a politica.
     */
    private const BLOCKED_HTML = '~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i';

    /** @var array<string, string> nome do template ('chave.twig') => fonte */
    private array $sources = [];

    /** @var array<string, string> chave => motivo de ter saido */
    private array $dropped = [];

    private ?\Twig\Environment $env = null;

    /**
     * @param array<mixed, mixed>   $templates chave => fonte, como vem do snapshot do tema
     * @param list<string>          $keys      chaves que um tema pode redefinir
     * @param list<string>          $paths     pastas dos templates padrao (include cai aqui)
     * @param array<string, mixed>  $globals   globais ja saneados pelo adaptador
     * @param \Closure(string, string): void|null $report chamado como (chave, motivo) quando uma fonte sai
     */
    public function __construct(
        array $templates,
        array $keys,
        private array $paths,
        private string|false $cache = false,
        private array $globals = [],
        private ?\Closure $report = null
    ) {
        foreach ($templates as $key => $source) {
            // Fonte vazia e aceita, como sempre foi no site publicado: o tema pode
            // esvaziar um trecho de proposito.
            if (!is_string($key) || !in_array($key, $keys, true) || !is_string($source)) {
                continue;
            }

            if (self::blocked($source)) {
                $this->drop($key, 'Source rejected by the theme allowlist.');

                continue;
            }

            $this->sources[$key . '.twig'] = $source;
        }
    }

    /** Politica unica. Modo estrito: nada e liberado por omissao (comportamento do Twig 4). */
    public static function policy(): \Twig\Sandbox\SecurityPolicy {
        $policy = new \Twig\Sandbox\SecurityPolicy(self::TAGS, self::FILTERS, [], [], self::FUNCTIONS);

        if (method_exists($policy, 'setStrict')) {
            $policy->setStrict(true);
        }

        return $policy;
    }

    /** Environment dedicado e SEMPRE em sandbox para o loader dado. */
    public static function environment(\Twig\Loader\LoaderInterface $loader, string|false $cache = false): \Twig\Environment {
        $env = new \Twig\Environment($loader, ['autoescape' => 'html', 'cache' => $cache, 'auto_reload' => true]);
        $env->addExtension(new \Twig\Extension\SandboxExtension(self::policy(), true));
        $env->addFunction(new \Twig\TwigFunction(self::TRUSTED_PARTIAL, static function (): string {
            throw new \LogicException('theme_partial is not available inside the theme sandbox.');
        }));

        return $env;
    }

    /** Tamanho e camada de HTML. true = a fonte nao entra. */
    public static function blocked(string $source): bool {
        return strlen($source) > self::MAX_SOURCE || preg_match(self::BLOCKED_HTML, $source) === 1;
    }

    /**
     * Confere UMA fonte contra a politica, sem renderizar. Para salvar e
     * importar: o erro aparece na hora, com a linha, em vez de virar template
     * padrao em silencio no site.
     *
     * Erro de sintaxe NAO e reprovado aqui: fonte que nao compila nao executa,
     * e o editor precisa conseguir salvar um rascunho incompleto (o canvas ja
     * mostra o erro de sintaxe).
     *
     * @return array{message: string, line: int}|null null = dentro da politica
     */
    public static function check(string $source): ?array {
        if (strlen($source) > self::MAX_SOURCE) {
            return ['message' => 'Source exceeds the allowed size.', 'line' => 0];
        }

        if (preg_match(self::BLOCKED_HTML, $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            return ['message' => 'Blocked construct: ' . substr(trim((string)$match[0][0]), 0, 40), 'line' => substr_count($source, "\n", 0, (int)$match[0][1]) + 1];
        }

        try {
            $env      = self::environment(new \Twig\Loader\ArrayLoader(['source.twig' => $source]));
            $template = $env->load('source.twig')->unwrap($env);

            // `checkSecurity()` e o metodo que o Twig gera em todo template compilado
            // com a extensao de sandbox; e o mesmo que roda no inicio do render.
            // `unwrap()` e este metodo sao internos do Twig: se uma versao futura os
            // mudar, a conferencia REPROVA (nao aprova) e os testes de
            // ThemeSandboxPublishedTest acusam.
            if (!method_exists($template, 'checkSecurity')) {
                return ['message' => 'Source could not be verified.', 'line' => 0];
            }

            $template->checkSecurity();
        } catch (\Twig\Sandbox\SecurityError $e) {
            return ['message' => substr($e->getRawMessage(), 0, 200), 'line' => max(0, $e->getTemplateLine())];
        } catch (\Twig\Error\SyntaxError $e) {
            return null;
        } catch (\Throwable $e) {
            return ['message' => 'Source could not be verified.', 'line' => 0];
        }

        return null;
    }

    /** O tema nao redefine nada (ou tudo ja saiu): o chamador pode usar so o confiavel. */
    public function isEmpty(): bool {
        return $this->sources === [];
    }

    /** @return array<string, string> chave => motivo, das fontes que sairam ate agora */
    public function dropped(): array {
        return $this->dropped;
    }

    /** @param array<string, mixed> $globals */
    public function setGlobals(array $globals): void {
        $this->globals = $globals;
        $this->env     = null;
    }

    /**
     * Renderiza uma chave de tema. $trusted e o Environment dos arquivos do
     * sistema; so e usado quando nao sobra fonte de tema para a pagina.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $key, array $data, \Twig\Environment $trusted): string {
        $name = $key . '.twig';

        for ($attempt = count($this->sources); $attempt >= 0 && $this->sources !== []; $attempt--) {
            try {
                return $this->env()->render($name, $data);
            } catch (\Twig\Sandbox\SecurityError $e) {
                $source  = $e->getSourceContext();
                $culprit = $source ? $source->getName() : '';
                $line    = $e->getTemplateLine();
                $message = substr($e->getRawMessage(), 0, 200) . ($line > 0 ? ' (line ' . $line . ')' : '');

                if (!isset($this->sources[$culprit])) {
                    // A violacao esta num arquivo do sistema que a fonte de tema
                    // alcancou (ex.: incluiu 'common/header.twig', que usa |raw).
                    // Nao da para saber qual fonte o chamou: a pagina sai inteira
                    // dos arquivos padrao, que nao enxergam o tema.
                    if ($this->report !== null) {
                        ($this->report)($key, 'Theme reached a system template outside the sandbox policy: ' . $message);
                    }

                    break;
                }

                unset($this->sources[$culprit]);
                $this->env = null;
                $this->drop(substr($culprit, 0, -5), $message);
            }
        }

        return $trusted->render($name, $data);
    }

    private function drop(string $key, string $message): void {
        $this->dropped[$key] = $message;

        if ($this->report !== null) {
            ($this->report)($key, $message);
        }
    }

    private function env(): \Twig\Environment {
        if ($this->env === null) {
            $env = self::environment(new \Twig\Loader\ChainLoader([
                new \Twig\Loader\ArrayLoader($this->sources),
                new \Twig\Loader\FilesystemLoader($this->paths),
            ]), $this->cache);

            foreach ($this->globals as $name => $value) {
                $env->addGlobal($name, $value);
            }

            $this->env = $env;
        }

        return $this->env;
    }
}
