<?php
/** Manual functional fixture runner. Deliberately not executed in this task. */
require_once __DIR__ . '/../dev/system/library/blog/block_document.php';

$fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy-conversion.json'), true);
if (!is_array($fixtures)) { fwrite(STDERR, "Invalid fixture JSON\n"); exit(1); }
$converter = new \Reamur\System\Library\Blog\BlockDocument();
$failures = [];
foreach ($fixtures as $fixture) {
    $result = $converter->convertLegacy($fixture['html']);
    $types = [];
    if (is_array($result['document'])) foreach ($result['document']['blocks'] as $block) $types[] = $block['type'];
    if ($result['status'] !== $fixture['expected_status']) $failures[] = $fixture['id'] . ': status mismatch';
    if ($types !== $fixture['expected_types']) $failures[] = $fixture['id'] . ': type mismatch';
    if ($result['status'] === 'review_required' && ($result['source_html'] !== $fixture['html'] || $result['document'] !== null)) $failures[] = $fixture['id'] . ': source not preserved/fail-closed';
    if ($result['status'] === 'converted') {
        if (isset($fixture['expected_content'])) {
            foreach ($fixture['expected_content'] as $i => $text) if (($result['document']['blocks'][$i]['content'] ?? null) !== $text) $failures[] = $fixture['id'] . ': text mismatch at ' . $i;
        }
        $json = $converter->serializeDocument($result['document']);
        $roundTrip = $converter->deserializeDocument($json);
        if (!$roundTrip['valid'] || $roundTrip['document'] !== $result['document']) $failures[] = $fixture['id'] . ': JSON round-trip mismatch';
        $again = $converter->convertLegacy($fixture['html']);
        if ($converter->serializeDocument($again['document']) !== $json) $failures[] = $fixture['id'] . ': nondeterministic output';
    }
}
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo 'F9-13 fixtures PASS (' . count($fixtures) . ")\n";
