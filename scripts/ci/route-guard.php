<?php
declare(strict_types=1);

/**
 * Route Guard (CI)
 * - Fail on doubled prefix patterns: api/v1/api/v1 or api/v1/v1
 * - Fail on duplicate METHOD+URI for API surface only (uri starts with "api/")
 *
 * Reads `php artisan route:list --json` from STDIN.
 */

$raw = stream_get_contents(STDIN);
$j = json_decode($raw, true);

if (!is_array($j)) {
    fwrite(STDERR, "ROUTE_GUARD_FAILED\nINVALID_ROUTE_LIST_JSON\n");
    exit(1);
}

// 1) Double prefix detection (only meaningful for API)
foreach ($j as $r) {
    $uri = (string)($r['uri'] ?? '');
    if ($uri === '') continue;

    // Examples we must forbid:
    // - api/v1/api/v1/...
    // - api/v1/v1/...
    if (preg_match('#^api/api/(zena|v1/zena)/#', $uri)) {
        fwrite(STDERR, "ROUTE_GUARD_FAILED\nDOUBLE_PREFIX_FOUND:\n{$uri}\n");
        exit(1);
    }

    if (preg_match('#^api/v1/(api/v1|v1)/#', $uri)) {
        fwrite(STDERR, "ROUTE_GUARD_FAILED\nDOUBLE_PREFIX_FOUND:\n{$uri}\n");
        exit(1);
    }
}

// 2) Duplicate METHOD+URI check (API surface only)
$seen = [];
foreach ($j as $r) {
    $uri = (string)($r['uri'] ?? '');
    $method = (string)($r['method'] ?? '');

    if ($uri === '' || !str_starts_with($uri, 'api/')) {
        continue; // ignore non-API (web/debug/legacy) routes
    }

    // Normalize method: Laravel often prints "GET|HEAD"
    $normMethod = str_contains($method, 'GET') ? 'GET' : $method;

    $key = $normMethod . ' ' . $uri;
    $seen[$key] = ($seen[$key] ?? 0) + 1;
}

$dups = array_filter($seen, fn($c) => $c > 1);
if ($dups) {
    ksort($dups);
    fwrite(STDERR, "ROUTE_GUARD_FAILED\nDUPLICATE_METHOD_URI_FOUND:\n");
    foreach ($dups as $key => $c) {
        fwrite(STDERR, "{$c}  {$key}\n");
    }
    exit(1);
}

echo "ROUTE_GUARD_OK\n";
exit(0);
