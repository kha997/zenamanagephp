<?php

/**
 * FRONTEND CONFIGURATION - SINGLE SOURCE OF TRUTH
 * 
 * ⚠️ CRITICAL: This file defines which frontend system is ACTIVE.
 * Only ONE frontend system can be active at a time.
 * 
 * Changing this requires:
 * 1. Updating routes/web.php
 * 2. Updating documentation
 * 3. Running validation script: php artisan frontend:validate
 */

return [
    /**
     * Active Frontend System
     * 
     * Options:
     * - 'react' => React SPA (Port 5173) - Modern, TypeScript, Interactive
     * - 'blade' => Blade Templates (Port 8000) - Server-rendered, Simple
     * 
     * ⚠️ DO NOT SET BOTH TO TRUE
     */
    'active' => env('FRONTEND_ACTIVE', 'react'),

    /**
     * Frontend Systems Configuration
     */
    'systems' => [
        'react' => [
            'enabled' => true,
            'port' => 5173,
            'base_url' => env('FRONTEND_REACT_URL', 'http://localhost:5173'),
            'routes' => [
                '/login',
                '/forgot-password',
                '/reset-password',
                '/app/*',
            ],
            'description' => 'React SPA - Modern frontend with TypeScript',
        ],
        'blade' => [
            'enabled' => false, // ⚠️ MUST BE FALSE if React is active
            'port' => 8000,
            'base_url' => env('FRONTEND_BLADE_URL', 'http://localhost:8000'),
            'routes' => [
                '/admin/*', // Admin routes still use Blade
            ],
            'description' => 'Blade Templates - Server-rendered views',
        ],
    ],

    /**
     * Route Validation Rules
     * 
     * These rules ensure no route conflicts between systems
     */
    'validation' => [
        'strict_mode' => env('FRONTEND_STRICT_MODE', true),
        'check_duplicates' => true,
        'check_ports' => true,
    ],
];

