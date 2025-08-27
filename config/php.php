<?php declare(strict_types=1);

/**
 * PHP Configuration for zenamanage
 * 
 * File này chứa cấu hình PHP specific cho dự án
 */

return [
    // PHP executable path
    'php_path' => '/Applications/XAMPP/bin/php',
    
    // PHP version requirements
    'min_version' => '8.0.0',
    
    // Required PHP extensions
    'required_extensions' => [
        'pdo',
        'pdo_mysql',
        'json',
        'mbstring',
        'openssl',
        'curl',
        'fileinfo'
    ],
    
    // PHP settings
    'settings' => [
        'memory_limit' => '256M',
        'max_execution_time' => 300,
        'upload_max_filesize' => '10M',
        'post_max_size' => '10M'
    ]
];