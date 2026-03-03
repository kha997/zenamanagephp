<?php

declare(strict_types=1);

$root = __DIR__;
$skipPrefixes = [
    $root . '/vendor/',
    $root . '/node_modules/',
    $root . '/frontend/node_modules/',
    $root . '/storage/',
    $root . '/bootstrap/cache/',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$hasErrors = false;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $normalizedPath = str_replace('\\', '/', $path);

    $shouldSkip = false;
    foreach ($skipPrefixes as $prefix) {
        if (str_starts_with($normalizedPath, str_replace('\\', '/', $prefix))) {
            $shouldSkip = true;
            break;
        }
    }

    if ($shouldSkip) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read file: {$path}\n");
        $hasErrors = true;
        continue;
    }

    preg_match_all('/^use\s+([^;]+);/m', $content, $matches);
    $imports = array_map('trim', $matches[1] ?? []);
    $duplicates = array_values(array_unique(array_diff_assoc($imports, array_unique($imports))));

    if ($duplicates === []) {
        continue;
    }

    $hasErrors = true;
    $relativePath = ltrim(str_replace(str_replace('\\', '/', $root), '', $normalizedPath), '/');
    echo "Duplicate imports found in {$relativePath}\n";
    foreach ($duplicates as $duplicate) {
        echo "  - {$duplicate}\n";
    }
}

if ($hasErrors) {
    exit(1);
}

echo "No duplicate imports found.\n";
