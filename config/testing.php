<?php

/**
 * Testing environment configuration
 * 
 * Round 161: Fixed to respect DB_CONNECTION env var when explicitly set
 * When DB_CONNECTION=mysql is set (e.g., in Playwright webServer), use MySQL
 * Otherwise, default to sqlite for unit tests
 */
return [
    // Round 161: Respect DB_CONNECTION env var - don't force sqlite
    // This allows E2E tests to use MySQL when DB_CONNECTION=mysql is set
    'default' => env('DB_CONNECTION', 'sqlite'),
    
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
        
        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ],
];