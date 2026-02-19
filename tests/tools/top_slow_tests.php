<?php declare(strict_types=1);

$junitPath = $argv[1] ?? 'storage/logs/junit.xml';
$limit = isset($argv[2]) ? (int) $argv[2] : 30;
$limit = $limit > 0 ? $limit : 30;

if (!is_file($junitPath)) {
    fwrite(STDERR, "JUnit file not found: {$junitPath}\n");
    exit(1);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($junitPath);
if ($xml === false) {
    fwrite(STDERR, "Failed to parse XML: {$junitPath}\n");
    foreach (libxml_get_errors() as $error) {
        fwrite(STDERR, trim($error->message) . PHP_EOL);
    }
    exit(1);
}

$rows = [];
$testCases = $xml->xpath('//testcase');

if ($testCases === false) {
    fwrite(STDERR, "No testcase nodes found in {$junitPath}\n");
    exit(1);
}

foreach ($testCases as $case) {
    $className = trim((string) $case['classname']);
    $name = trim((string) $case['name']);
    $seconds = (float) ($case['time'] ?? 0);
    $id = $className . '::' . $name;

    $rows[] = [
        'id' => $id,
        'seconds' => $seconds,
    ];
}

usort($rows, static function (array $a, array $b): int {
    return $b['seconds'] <=> $a['seconds'];
});

$rows = array_slice($rows, 0, $limit);

echo "Top {$limit} slowest testcases from {$junitPath}\n";
echo str_repeat('-', 95) . PHP_EOL;
printf("%-4s %-76s %12s\n", '#', 'Testcase', 'Seconds');
echo str_repeat('-', 95) . PHP_EOL;

$rank = 1;
foreach ($rows as $row) {
    printf("%-4d %-76s %12.6f\n", $rank, $row['id'], $row['seconds']);
    $rank++;
}
