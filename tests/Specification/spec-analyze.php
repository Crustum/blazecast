<?php
declare(strict_types=1);

/**
 * Analyze Autobahn fuzzingclient results.
 *
 * Reads tests/Specification/reports/index.json (written by wstest with
 * outdir /mnt/autobahn/reports mapped to ./reports) and exits non-zero
 * when any non-INFORMATIONAL case is not OK / NON-STRICT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$file = __DIR__ . '/reports/index.json';

if (!file_exists($file)) {
    echo 'No test results found. Expected: ' . $file . PHP_EOL;
    echo 'Did the fuzzingclient run? See run-spec-tests.cmd/.sh' . PHP_EOL;

    exit(1);
}

$decoded = json_decode((string)file_get_contents($file), true);
if (!is_array($decoded) || $decoded === []) {
    echo 'Invalid or empty results file: ' . $file . PHP_EOL;

    exit(1);
}

// index.json is { "<agent>": { "<case>": {...} } }; take the first agent.
$results = reset($decoded);
if (!is_array($results)) {
    echo 'Unexpected results format in: ' . $file . PHP_EOL;

    exit(1);
}

$hasFailures = false;
$passed = 0;
$failed = 0;
$informational = 0;

foreach ($results as $name => $result) {
    $behavior = is_array($result) ? ($result['behavior'] ?? 'UNKNOWN') : 'UNKNOWN';

    if ($behavior === 'INFORMATIONAL') {
        $informational++;

        continue;
    }

    if (in_array($behavior, ['OK', 'NON-STRICT'], true)) {
        $passed++;
        echo 'PASS Test case ' . $name . ' (' . $behavior . ').' . PHP_EOL;
    } else {
        $hasFailures = true;
        $failed++;
        echo 'FAIL Test case ' . $name . ' (' . $behavior . ').' . PHP_EOL;
    }
}

echo sprintf(
    'Summary: %d passed, %d failed, %d informational.' . PHP_EOL,
    $passed,
    $failed,
    $informational,
);

exit($hasFailures ? 1 : 0);
