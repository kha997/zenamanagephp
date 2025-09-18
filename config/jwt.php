<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for JWT authentication system
    |
    */

    'secret' => env('JWT_SECRET', env('APP_KEY')),
    
    'algo' => env('JWT_ALGO', 'HS256'),
    
    /*
    |--------------------------------------------------------------------------
    | Token TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Access token TTL in seconds
    | Default: 1 hour (3600 seconds)
    |
    */
    'ttl' => env('JWT_TTL', 3600),
    
    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL
    |--------------------------------------------------------------------------
    |
    | Refresh token TTL in seconds
    | Default: 14 days (1209600 seconds)
    |
    */
    'refresh_ttl' => env('JWT_REFRESH_TTL', 1209600),
    
    /*
    |--------------------------------------------------------------------------
    | Token Rotation
    |--------------------------------------------------------------------------
    |
    | Enable refresh token rotation for enhanced security
    | When enabled, each refresh generates a new refresh token
    |
    */
    'rotation_enabled' => env('JWT_ROTATION_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Token Blacklist
    |--------------------------------------------------------------------------
    |
    | Enable token blacklisting for logout/ban functionality
    | Requires Redis or database storage
    |
    */
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Issuer and Audience
    |--------------------------------------------------------------------------
    |
    | JWT issuer and audience claims
    |
    */
    'issuer' => env('JWT_ISSUER', env('APP_NAME', 'ZENA Manage')),
    'audience' => env('JWT_AUDIENCE', env('APP_URL')),
];
