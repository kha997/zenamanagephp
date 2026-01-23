<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'perf_assertions' => filter_var(env('PERF_ASSERTIONS', false), FILTER_VALIDATE_BOOLEAN),
    
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
