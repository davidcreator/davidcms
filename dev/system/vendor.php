<?php
// composer runtime
$autoloader->register('Composer', DIR_STORAGE . 'vendor/composer/', true);
if (!class_exists('Composer\\InstalledVersions') && is_file(DIR_STORAGE . 'vendor/composer/InstalledVersions.php')) {
    require_once(DIR_STORAGE . 'vendor/composer/InstalledVersions.php');
}

// aws/aws-crt-php
$autoloader->register('AWS/CRT', DIR_STORAGE . 'vendor/aws/aws-crt-php/src/AWS/CRT/', true);
$autoloader->register('AWS/CRT/Auth', DIR_STORAGE . 'vendor/aws/aws-crt-php/src/AWS/CRT/Auth/', true);
$autoloader->register('AWS/CRT/HTTP', DIR_STORAGE . 'vendor/aws/aws-crt-php/src/AWS/CRT/HTTP/', true);
$autoloader->register('AWS/CRT/IO', DIR_STORAGE . 'vendor/aws/aws-crt-php/src/AWS/CRT/IO/', true);
$autoloader->register('AWS/CRT/Internal', DIR_STORAGE . 'vendor/aws/aws-crt-php/src/AWS/CRT/Internal/', true);

// aws/aws-sdk-php
$autoloader->register('Aws', DIR_STORAGE . 'vendor/aws/aws-sdk-php/src/', true);
require_once(DIR_STORAGE . 'vendor/aws/aws-sdk-php/src/functions.php');

// dflydev/dot-access-data
$autoloader->register('Dflydev\DotAccessData', DIR_STORAGE . 'vendor/dflydev/dot-access-data/src/', true);

// doctrine/deprecations
$autoloader->register('Doctrine\Deprecations', DIR_STORAGE . 'vendor/doctrine/deprecations/src/', true);

// doctrine/lexer
$autoloader->register('Doctrine\Common\Lexer', DIR_STORAGE . 'vendor/doctrine/lexer/src/', true);

// guzzlehttp/guzzle
$autoloader->register('GuzzleHttp', DIR_STORAGE . 'vendor/guzzlehttp/guzzle/src/', true);
require_once(DIR_STORAGE . 'vendor/guzzlehttp/guzzle/src/functions_include.php');

// guzzlehttp/promises
$autoloader->register('GuzzleHttp\Promise', DIR_STORAGE . 'vendor/guzzlehttp/promises/src/', true);
require_once(DIR_STORAGE . 'vendor/guzzlehttp/promises/src/functions_include.php');

// guzzlehttp/psr7
$autoloader->register('GuzzleHttp\Psr7', DIR_STORAGE . 'vendor/guzzlehttp/psr7/src/', true);
require_once(DIR_STORAGE . 'vendor/guzzlehttp/psr7/src/functions_include.php');

// guzzlehttp/uri-template
$autoloader->register('GuzzleHttp\UriTemplate', DIR_STORAGE . 'vendor/guzzlehttp/uri-template/src/', true);

// jawira/plantuml-encoding
require_once(DIR_STORAGE . 'vendor/jawira/plantuml-encoding/src/plantuml_functions.php');

// jawira/plantuml

// jean85/pretty-package-versions
$autoloader->register('Jean85', DIR_STORAGE . 'vendor/jean85/pretty-package-versions/src/', true);

// latte/latte
$autoloader->register('Latte', DIR_STORAGE . 'vendor/latte/latte/src/Latte/', true);
$autoloader->register('Latte', DIR_STORAGE . 'vendor/latte/latte/src/Latte/', true);
$autoloader->register('Tools', DIR_STORAGE . 'vendor/latte/latte/src/Tools/', true);
$autoloader->register('Bridges/Tracy', DIR_STORAGE . 'vendor/latte/latte/src/Bridges/Tracy/', true);
$autoloader->register('Latte/Compiler', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/', true);
$autoloader->register('Latte/Essential', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Essential/', true);
$autoloader->register('Latte/Loaders', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Loaders/', true);
$autoloader->register('Latte/Runtime', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Runtime/', true);
$autoloader->register('Latte/Sandbox', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Sandbox/', true);
$autoloader->register('Bridges/Tracy/dist', DIR_STORAGE . 'vendor/latte/latte/src/Bridges/Tracy/dist/', true);
$autoloader->register('Latte/Compiler/Nodes', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/Nodes/', true);
$autoloader->register('Latte/Essential/Nodes', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Essential/Nodes/', true);
$autoloader->register('Latte/Sandbox/Nodes', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Sandbox/Nodes/', true);
$autoloader->register('Latte/Compiler/Nodes/Html', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/Nodes/Html/', true);
$autoloader->register('Latte/Compiler/Nodes/Php', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/Nodes/Php/', true);
$autoloader->register('Latte/Compiler/Nodes/Php/Expression', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/Nodes/Php/Expression/', true);
$autoloader->register('Latte/Compiler/Nodes/Php/Scalar', DIR_STORAGE . 'vendor/latte/latte/src/Latte/Compiler/Nodes/Php/Scalar/', true);

// league/commonmark
$autoloader->register('League\CommonMark', DIR_STORAGE . 'vendor/league/commonmark/src/', true);

// league/config
$autoloader->register('League\Config', DIR_STORAGE . 'vendor/league/config/src/', true);

// league/csv
$autoloader->register('League\Csv', DIR_STORAGE . 'vendor/league/csv/src/', true);
require_once(DIR_STORAGE . 'vendor/league/csv/src/functions_include.php');

// league/flysystem
$autoloader->register('League\Flysystem', DIR_STORAGE . 'vendor/league/flysystem/src/', true);

// league/mime-type-detection
$autoloader->register('League\MimeTypeDetection', DIR_STORAGE . 'vendor/league/mime-type-detection/src/', true);

// league/tactician
$autoloader->register('League\Tactician', DIR_STORAGE . 'vendor/league/tactician/src/', true);

// league/uri-interfaces
$autoloader->register('League\Uri', DIR_STORAGE . 'vendor/league/uri-interfaces//', true);

// league/uri
$autoloader->register('League\Uri', DIR_STORAGE . 'vendor/league/uri//', true);

// masterminds/html5
$autoloader->register('Masterminds', DIR_STORAGE . 'vendor/masterminds/html5/src/', true);

// monolog/monolog
$autoloader->register('Monolog', DIR_STORAGE . 'vendor/monolog/monolog/src/Monolog/', true);

// mtdowling/jmespath.php
$autoloader->register('JmesPath', DIR_STORAGE . 'vendor/mtdowling/jmespath.php/src/', true);
require_once(DIR_STORAGE . 'vendor/mtdowling/jmespath.php/src/JmesPath.php');

// mustache/mustache
$autoloader->register('Mustache', DIR_STORAGE . 'vendor/mustache/mustache/src/', true);

// nette/schema
$autoloader->register('Schema', DIR_STORAGE . 'vendor/nette/schema/src/Schema/', true);
$autoloader->register('Schema/Elements', DIR_STORAGE . 'vendor/nette/schema/src/Schema/Elements/', true);

// nette/utils
$autoloader->register('Iterators', DIR_STORAGE . 'vendor/nette/utils/src/Iterators/', true);
$autoloader->register('Utils', DIR_STORAGE . 'vendor/nette/utils/src/Utils/', true);

// nikic/php-parser
$autoloader->register('PhpParser', DIR_STORAGE . 'vendor/nikic/php-parser/lib/PhpParser/', true);

// parsica-php/parsica
$autoloader->register('Parsica\Parsica', DIR_STORAGE . 'vendor/parsica-php/parsica/src/', true);
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/characters.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/combinators.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/numeric.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/predicates.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/primitives.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/recursion.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/sideEffects.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/space.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/strings.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/Expression/expression.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/Internal/FP.php');
require_once(DIR_STORAGE . 'vendor/parsica-php/parsica/src/Curry/functions.php');

// phar-io/manifest
$autoloader->register('exceptions', DIR_STORAGE . 'vendor/phar-io/manifest/src/exceptions/', true);
$autoloader->register('values', DIR_STORAGE . 'vendor/phar-io/manifest/src/values/', true);
$autoloader->register('xml', DIR_STORAGE . 'vendor/phar-io/manifest/src/xml/', true);

// phar-io/version
$autoloader->register('constraints', DIR_STORAGE . 'vendor/phar-io/version/src/constraints/', true);
$autoloader->register('exceptions', DIR_STORAGE . 'vendor/phar-io/version/src/exceptions/', true);

// phpdocumentor/filesystem
$autoloader->register('phpDocumentor\FileSystem', DIR_STORAGE . 'vendor/phpdocumentor/filesystem/src/', true);

// phpdocumentor/flyfinder
$autoloader->register('Flyfinder', DIR_STORAGE . 'vendor/phpdocumentor/flyfinder/src/', true);

// phpdocumentor/graphviz
$autoloader->register('phpDocumentor\GraphViz', DIR_STORAGE . 'vendor/phpdocumentor/graphviz/src/phpDocumentor/GraphViz/', true);
$autoloader->register('phpDocumentor\GraphViz\PHPStan', DIR_STORAGE . 'vendor/phpdocumentor/graphviz/./src/phpDocumentor/PHPStan/', true);

// phpdocumentor/guides-graphs
$autoloader->register('phpDocumentor\Guides', DIR_STORAGE . 'vendor/phpdocumentor/guides-graphs/src/', true);

// phpdocumentor/guides-markdown
$autoloader->register('phpDocumentor\Guides', DIR_STORAGE . 'vendor/phpdocumentor/guides-markdown/src/', true);

// phpdocumentor/guides-restructured-text
$autoloader->register('phpDocumentor\Guides', DIR_STORAGE . 'vendor/phpdocumentor/guides-restructured-text/src/', true);

// phpdocumentor/guides
$autoloader->register('phpDocumentor\Guides', DIR_STORAGE . 'vendor/phpdocumentor/guides/src/', true);

// phpdocumentor/json-path
$autoloader->register('phpDocumentor\JsonPath', DIR_STORAGE . 'vendor/phpdocumentor/json-path/src/', true);

// phpdocumentor/phpdocumentor
$autoloader->register('phpDocumentor', DIR_STORAGE . 'vendor/phpdocumentor/phpdocumentor/src/phpDocumentor/', true);

// phpdocumentor/reflection-common
$autoloader->register('phpDocumentor\Reflection', DIR_STORAGE . 'vendor/phpdocumentor/reflection-common/src/', true);

// phpdocumentor/reflection-docblock
$autoloader->register('phpDocumentor\Reflection', DIR_STORAGE . 'vendor/phpdocumentor/reflection-docblock/src/', true);

// phpdocumentor/reflection
$autoloader->register('phpDocumentor', DIR_STORAGE . 'vendor/phpdocumentor/reflection/src/phpDocumentor/', true);
require_once(DIR_STORAGE . 'vendor/phpdocumentor/reflection/src/php-parser/Modifiers.php');

// phpdocumentor/type-resolver
$autoloader->register('phpDocumentor\Reflection', DIR_STORAGE . 'vendor/phpdocumentor/type-resolver/src/', true);

// phpstan/phpdoc-parser
$autoloader->register('PHPStan\PhpDocParser', DIR_STORAGE . 'vendor/phpstan/phpdoc-parser/src/', true);

// psr/cache
$autoloader->register('Psr\Cache', DIR_STORAGE . 'vendor/psr/cache/src/', true);

// psr/clock
$autoloader->register('Psr\Clock', DIR_STORAGE . 'vendor/psr/clock/src/', true);

// psr/container
$autoloader->register('Psr\Container', DIR_STORAGE . 'vendor/psr/container/src/', true);

// psr/event-dispatcher
$autoloader->register('Psr\EventDispatcher', DIR_STORAGE . 'vendor/psr/event-dispatcher/src/', true);

// psr/http-client
$autoloader->register('Psr\Http\Client', DIR_STORAGE . 'vendor/psr/http-client/src/', true);

// psr/http-factory
$autoloader->register('Psr\Http\Message', DIR_STORAGE . 'vendor/psr/http-factory/src/', true);

// psr/http-message
$autoloader->register('Psr\Http\Message', DIR_STORAGE . 'vendor/psr/http-message/src/', true);

// psr/log
$autoloader->register('Psr\Log', DIR_STORAGE . 'vendor/psr/log/Psr/Log/', true);

// ralouphie/getallheaders
require_once(DIR_STORAGE . 'vendor/ralouphie/getallheaders/src/getallheaders.php');

// scssphp/scssphp
$autoloader->register('ScssPhp\ScssPhp', DIR_STORAGE . 'vendor/scssphp/scssphp/src/', true);

// symfony/cache
$autoloader->register('Symfony\Component\Cache', DIR_STORAGE . 'vendor/symfony/cache//', true);

// symfony/clock
$autoloader->register('Symfony\Component\Clock', DIR_STORAGE . 'vendor/symfony/clock//', true);
require_once(DIR_STORAGE . 'vendor/symfony/clock/Resources/now.php');

// symfony/config
$autoloader->register('Symfony\Component\Config', DIR_STORAGE . 'vendor/symfony/config//', true);

// symfony/console
$autoloader->register('Symfony\Component\Console', DIR_STORAGE . 'vendor/symfony/console//', true);

// symfony/contracts
$autoloader->register('Symfony\Contracts', DIR_STORAGE . 'vendor/symfony/contracts//', true);
require_once(DIR_STORAGE . 'vendor/symfony/contracts/Deprecation/function.php');

// symfony/dependency-injection
$autoloader->register('Symfony\Component\DependencyInjection', DIR_STORAGE . 'vendor/symfony/dependency-injection//', true);

// symfony/deprecation-contracts
require_once(DIR_STORAGE . 'vendor/symfony/deprecation-contracts/function.php');

// symfony/dom-crawler
$autoloader->register('Symfony\Component\DomCrawler', DIR_STORAGE . 'vendor/symfony/dom-crawler//', true);

// symfony/dotenv
$autoloader->register('Symfony\Component\Dotenv', DIR_STORAGE . 'vendor/symfony/dotenv//', true);

// symfony/event-dispatcher
$autoloader->register('Symfony\Component\EventDispatcher', DIR_STORAGE . 'vendor/symfony/event-dispatcher//', true);

// symfony/expression-language
$autoloader->register('Symfony\Component\ExpressionLanguage', DIR_STORAGE . 'vendor/symfony/expression-language//', true);

// symfony/filesystem
$autoloader->register('Symfony\Component\Filesystem', DIR_STORAGE . 'vendor/symfony/filesystem//', true);

// symfony/finder
$autoloader->register('Symfony\Component\Finder', DIR_STORAGE . 'vendor/symfony/finder//', true);

// symfony/html-sanitizer
$autoloader->register('Symfony\Component\HtmlSanitizer', DIR_STORAGE . 'vendor/symfony/html-sanitizer//', true);

// symfony/http-client
$autoloader->register('Symfony\Component\HttpClient', DIR_STORAGE . 'vendor/symfony/http-client//', true);

// symfony/polyfill-ctype
$autoloader->register('Symfony\Polyfill\Ctype', DIR_STORAGE . 'vendor/symfony/polyfill-ctype//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-ctype/bootstrap.php');

// symfony/polyfill-intl-grapheme
$autoloader->register('Symfony\Polyfill\Intl\Grapheme', DIR_STORAGE . 'vendor/symfony/polyfill-intl-grapheme//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-intl-grapheme/bootstrap.php');

// symfony/polyfill-intl-idn
$autoloader->register('Symfony\Polyfill\Intl\Idn', DIR_STORAGE . 'vendor/symfony/polyfill-intl-idn//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-intl-idn/bootstrap.php');

// symfony/polyfill-intl-normalizer
$autoloader->register('Symfony\Polyfill\Intl\Normalizer', DIR_STORAGE . 'vendor/symfony/polyfill-intl-normalizer//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-intl-normalizer/bootstrap.php');

// symfony/polyfill-mbstring
$autoloader->register('Symfony\Polyfill\Mbstring', DIR_STORAGE . 'vendor/symfony/polyfill-mbstring//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-mbstring/bootstrap.php');

// symfony/polyfill-php80
$autoloader->register('Symfony\Polyfill\Php80', DIR_STORAGE . 'vendor/symfony/polyfill-php80//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-php80/bootstrap.php');

// symfony/polyfill-php83
$autoloader->register('Symfony\Polyfill\Php83', DIR_STORAGE . 'vendor/symfony/polyfill-php83//', true);
require_once(DIR_STORAGE . 'vendor/symfony/polyfill-php83/bootstrap.php');

// symfony/process
$autoloader->register('Symfony\Component\Process', DIR_STORAGE . 'vendor/symfony/process//', true);

// symfony/property-access
$autoloader->register('Symfony\Component\PropertyAccess', DIR_STORAGE . 'vendor/symfony/property-access//', true);

// symfony/property-info
$autoloader->register('Symfony\Component\PropertyInfo', DIR_STORAGE . 'vendor/symfony/property-info//', true);

// symfony/routing
$autoloader->register('Symfony\Component\Routing', DIR_STORAGE . 'vendor/symfony/routing//', true);

// symfony/stopwatch
$autoloader->register('Symfony\Component\Stopwatch', DIR_STORAGE . 'vendor/symfony/stopwatch//', true);

// symfony/string
$autoloader->register('Symfony\Component\String', DIR_STORAGE . 'vendor/symfony/string//', true);
require_once(DIR_STORAGE . 'vendor/symfony/string/Resources/functions.php');

// symfony/type-info
$autoloader->register('Symfony\Component\TypeInfo', DIR_STORAGE . 'vendor/symfony/type-info//', true);

// symfony/var-exporter
$autoloader->register('Symfony\Component\VarExporter', DIR_STORAGE . 'vendor/symfony/var-exporter//', true);

// symfony/yaml
$autoloader->register('Symfony\Component\Yaml', DIR_STORAGE . 'vendor/symfony/yaml//', true);

// twig/twig
$autoloader->register('Twig', DIR_STORAGE . 'vendor/twig/twig/src/', true);
require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/core.php');
require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/debug.php');
require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/escaper.php');
require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/string_loader.php');

// threejs/three.js
$autoloader->register('Three', DIR_STORAGE . 'vendor/threejs/three.js/', true);

// webmozart/assert
$autoloader->register('Webmozart\Assert', DIR_STORAGE . 'vendor/webmozart/assert/src/', true);