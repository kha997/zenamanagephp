<?php declare(strict_types=1);

/**
 * zenamanage Bootstrap File
 * 
 * File này khởi tạo toàn bộ hệ thống zenamanage
 * bao gồm autoloader, cấu hình, database connection, và các services
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Kiểm tra xem dòng có chứa dấu '=' hay không
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Set error reporting
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Initialize database connection
use Src\Foundation\Foundation;

try {
    $foundation = new Foundation();
    $foundation->initializeDatabase();
    
    // Register global services
    $GLOBALS['zena_foundation'] = $foundation;
    $GLOBALS['zena_db'] = $foundation->getDatabase();
    $GLOBALS['zena_eventbus'] = $foundation->getEventBus();
    
} catch (Exception $e) {
    if ($_ENV['APP_DEBUG'] ?? false) {
        die('Bootstrap Error: ' . $e->getMessage());
    } else {
        die('Application initialization failed.');
    }
}

/**
 * Helper function để lấy Foundation instance
 * 
 * @return Foundation
 */
function app(): Foundation {
    return $GLOBALS['zena_foundation'];
}

/**
 * Helper function để lấy Database connection
 * 
 * @return \PDO
 */
function db(): \Illuminate\Database\Connection {
    return $GLOBALS['zena_db'];
}

/**
 * Helper function để lấy EventBus instance
 * 
 * @return \Src\Foundation\EventBus
 */
function eventBus(): \Src\Foundation\EventBus {
    return $GLOBALS['zena_eventbus'];
}

/**
 * Helper function để lấy config value
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config(string $key, $default = null) {
    return $_ENV[$key] ?? $default;
}