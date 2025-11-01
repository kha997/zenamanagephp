<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenID Connect Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OIDC integration with external identity providers
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default OIDC provider to use
    |
    */
    'default_provider' => env('OIDC_DEFAULT_PROVIDER', 'google'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for different OIDC providers
    |
    */
    'providers' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/oidc/google/callback'),
            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'userinfo_endpoint' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuer' => 'https://accounts.google.com',
            'scopes' => ['openid', 'email', 'profile'],
        ],

        'microsoft' => [
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'redirect_uri' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/oidc/microsoft/callback'),
            'authorization_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_endpoint' => 'https://graph.microsoft.com/v1.0/me',
            'jwks_uri' => 'https://login.microsoftonline.com/common/discovery/v2.0/keys',
            'issuer' => 'https://login.microsoftonline.com/common/v2.0',
            'scopes' => ['openid', 'email', 'profile'],
        ],

        'azure_ad' => [
            'client_id' => env('AZURE_AD_CLIENT_ID'),
            'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
            'redirect_uri' => env('AZURE_AD_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/oidc/azure/callback'),
            'authorization_endpoint' => 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token',
            'userinfo_endpoint' => 'https://graph.microsoft.com/v1.0/me',
            'jwks_uri' => 'https://login.microsoftonline.com/{tenant_id}/discovery/v2.0/keys',
            'issuer' => 'https://login.microsoftonline.com/{tenant_id}/v2.0',
            'scopes' => ['openid', 'email', 'profile'],
            'tenant_id' => env('AZURE_AD_TENANT_ID'),
        ],

        'okta' => [
            'client_id' => env('OKTA_CLIENT_ID'),
            'client_secret' => env('OKTA_CLIENT_SECRET'),
            'redirect_uri' => env('OKTA_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/oidc/okta/callback'),
            'authorization_endpoint' => env('OKTA_DOMAIN') . '/oauth2/default/v1/authorize',
            'token_endpoint' => env('OKTA_DOMAIN') . '/oauth2/default/v1/token',
            'userinfo_endpoint' => env('OKTA_DOMAIN') . '/oauth2/default/v1/userinfo',
            'jwks_uri' => env('OKTA_DOMAIN') . '/oauth2/default/v1/keys',
            'issuer' => env('OKTA_DOMAIN') . '/oauth2/default',
            'scopes' => ['openid', 'email', 'profile'],
        ],

        'auth0' => [
            'client_id' => env('AUTH0_CLIENT_ID'),
            'client_secret' => env('AUTH0_CLIENT_SECRET'),
            'redirect_uri' => env('AUTH0_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/oidc/auth0/callback'),
            'authorization_endpoint' => env('AUTH0_DOMAIN') . '/authorize',
            'token_endpoint' => env('AUTH0_DOMAIN') . '/oauth/token',
            'userinfo_endpoint' => env('AUTH0_DOMAIN') . '/userinfo',
            'jwks_uri' => env('AUTH0_DOMAIN') . '/.well-known/jwks.json',
            'issuer' => env('AUTH0_DOMAIN') . '/',
            'scopes' => ['openid', 'email', 'profile'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration for OIDC integration
    |
    */
    'security' => [
        'state_expires_in' => 600, // 10 minutes
        'nonce_expires_in' => 600, // 10 minutes
        'max_age' => 3600, // 1 hour
        'require_email_verification' => true,
        'auto_create_users' => env('OIDC_AUTO_CREATE_USERS', false),
        'auto_assign_roles' => env('OIDC_AUTO_ASSIGN_ROLES', false),
        'default_role' => env('OIDC_DEFAULT_ROLE', 'user'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Map OIDC user attributes to local user fields
    |
    */
    'field_mapping' => [
        'name' => 'name',
        'email' => 'email',
        'first_name' => 'given_name',
        'last_name' => 'family_name',
        'picture' => 'picture',
        'locale' => 'locale',
        'timezone' => 'zoneinfo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache configuration for OIDC tokens and metadata
    |
    */
    'cache' => [
        'enabled' => true,
        'prefix' => 'oidc:',
        'ttl' => 3600, // 1 hour
    ],
];
