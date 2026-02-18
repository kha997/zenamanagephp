<?php declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
require $autoloadPath;

$testingDirectory = dirname(__DIR__) . '/storage/framework/testing';
if (!is_dir($testingDirectory)) {
    mkdir($testingDirectory, 0775, true);
}

$runToken = sprintf('%d_%s', getmypid(), str_replace('.', '', sprintf('%.6f', microtime(true))));
$sqlitePath = $testingDirectory . '/phpunit_' . $runToken . '.sqlite';

if (!file_exists($sqlitePath)) {
    touch($sqlitePath);
}

$setEnv = static function (string $key, string $value): void {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

$setEnv('APP_ENV', 'testing');
$setEnv('DB_CONNECTION', 'sqlite');
$setEnv('DB_DATABASE', $sqlitePath);

register_shutdown_function(static function () use ($sqlitePath): void {
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
});
