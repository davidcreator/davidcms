<?php declare(strict_types=1);
/** Manual functional fixture runner. Included for controlled validation; not executed in this task. */
require_once __DIR__ . '/../dev/system/library/blog/block_document.php';
require_once __DIR__ . '/../dev/system/library/blog/block_renderer.php';

$fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/renderer.json'), true);
if (!is_array($fixtures)) { fwrite(STDERR, "Invalid renderer fixtures\n"); exit(1); }
$renderer = new \Reamur\System\Library\Blog\BlockRenderer();
$failures = [];
foreach ($fixtures as $fixture) {
    $json = json_encode($fixture['document'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $result = $renderer->renderJson((string)$json, (int)$fixture['stored_version']);
    if ((bool)$result['valid'] !== (bool)$fixture['valid']) $failures[] = $fixture['id'] . ': validity mismatch';
    if (!$fixture['valid'] && $result['html'] !== '') $failures[] = $fixture['id'] . ': invalid document emitted markup';
    if (isset($fixture['contains']) && strpos($result['html'], $fixture['contains']) === false) $failures[] = $fixture['id'] . ': required output fragment missing';
    foreach ((array)($fixture['contains_all'] ?? []) as $fragment) if (strpos($result['html'], $fragment) === false) $failures[] = $fixture['id'] . ': required output fragment missing';
    if (isset($fixture['not_contains']) && strpos($result['html'], $fixture['not_contains']) !== false) $failures[] = $fixture['id'] . ': forbidden output fragment present';
}
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo 'F9-15 fixtures PASS (' . count($fixtures) . ")\n";
