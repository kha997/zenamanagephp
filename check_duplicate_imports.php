<?php

declare(strict_types=1);

$roots = [
    'app',
    'routes',
    'tests',
    'src',
    'database',
    'scripts',
];

$files = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }
}

sort($files);

$hasErrors = false;

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);

    if ($content === false) {
        fwrite(STDERR, "Unable to read {$filePath}\n");
        $hasErrors = true;
        continue;
    }

    preg_match_all('/^use\s+([^;]+);/m', $content, $matches);

    if (empty($matches[1])) {
        continue;
    }

    $imports = array_map('trim', $matches[1]);
    $duplicates = array_values(array_unique(array_diff_assoc($imports, array_unique($imports))));

    if ($duplicates === []) {
        continue;
    }

    $hasErrors = true;
    echo "Duplicate imports found in {$filePath}\n";
    foreach ($duplicates as $duplicate) {
        echo "  - {$duplicate}\n";
    }
}

if ($hasErrors) {
    exit(1);
}

echo "No duplicate imports found.\n";
