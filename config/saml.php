<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAML Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SAML integration with external identity providers
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default SAML provider to use
    |
    */
    'default_provider' => env('SAML_DEFAULT_PROVIDER', 'azure_ad'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for different SAML providers
    |
    */
    'providers' => [
        'azure_ad' => [
            'entity_id' => env('AZURE_AD_ENTITY_ID'),
            'sso_url' => env('AZURE_AD_SSO_URL'),
            'slo_url' => env('AZURE_AD_SLO_URL'),
            'x509_cert' => env('AZURE_AD_X509_CERT'),
            'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'assertion_consumer_service_url' => env('APP_URL') . '/api/v1/auth/saml/azure/callback',
            'single_logout_service_url' => env('APP_URL') . '/api/v1/auth/saml/azure/slo',
        ],

        'okta' => [
            'entity_id' => env('OKTA_ENTITY_ID'),
            'sso_url' => env('OKTA_SSO_URL'),
            'slo_url' => env('OKTA_SLO_URL'),
            'x509_cert' => env('OKTA_X509_CERT'),
            'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'assertion_consumer_service_url' => env('APP_URL') . '/api/v1/auth/saml/okta/callback',
            'single_logout_service_url' => env('APP_URL') . '/api/v1/auth/saml/okta/slo',
        ],

        'adfs' => [
            'entity_id' => env('ADFS_ENTITY_ID'),
            'sso_url' => env('ADFS_SSO_URL'),
            'slo_url' => env('ADFS_SLO_URL'),
            'x509_cert' => env('ADFS_X509_CERT'),
            'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'assertion_consumer_service_url' => env('APP_URL') . '/api/v1/auth/saml/adfs/callback',
            'single_logout_service_url' => env('APP_URL') . '/api/v1/auth/saml/adfs/slo',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    |
    | SAML application configuration
    |
    */
    'app' => [
        'entity_id' => env('SAML_APP_ENTITY_ID', env('APP_URL') . '/saml'),
        'assertion_consumer_service_url' => env('APP_URL') . '/api/v1/auth/saml/callback',
        'single_logout_service_url' => env('APP_URL') . '/api/v1/auth/saml/slo',
        'x509_cert' => env('SAML_APP_X509_CERT'),
        'x509_private_key' => env('SAML_APP_X509_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration for SAML integration
    |
    */
    'security' => [
        'sign_assertions' => env('SAML_SIGN_ASSERTIONS', true),
        'sign_responses' => env('SAML_SIGN_RESPONSES', true),
        'want_assertions_signed' => env('SAML_WANT_ASSERTIONS_SIGNED', true),
        'want_responses_signed' => env('SAML_WANT_RESPONSES_SIGNED', true),
        'want_name_id' => env('SAML_WANT_NAME_ID', true),
        'want_assertions_encrypted' => env('SAML_WANT_ASSERTIONS_ENCRYPTED', false),
        'want_responses_encrypted' => env('SAML_WANT_RESPONSES_ENCRYPTED', false),
        'relax_destination_validation' => env('SAML_RELAX_DESTINATION_VALIDATION', false),
        'destination_strictly_equals' => env('SAML_DESTINATION_STRICTLY_EQUALS', true),
        'audience_restriction' => env('SAML_AUDIENCE_RESTRICTION', true),
        'want_audience_restriction' => env('SAML_WANT_AUDIENCE_RESTRICTION', true),
        'reject_unsigned_assertions' => env('SAML_REJECT_UNSIGNED_ASSERTIONS', true),
        'reject_unsigned_responses' => env('SAML_REJECT_UNSIGNED_RESPONSES', true),
        'reject_unsigned_requests' => env('SAML_REJECT_UNSIGNED_REQUESTS', true),
        'reject_deprecated_alg' => env('SAML_REJECT_DEPRECATED_ALG', true),
        'reject_blacklisted_alg' => env('SAML_REJECT_BLACKLISTED_ALG', true),
        'reject_duplicated_attributes' => env('SAML_REJECT_DUPLICATED_ATTRIBUTES', true),
        'want_xml_validation' => env('SAML_WANT_XML_VALIDATION', true),
        'retrieve_parameters_from_server' => env('SAML_RETRIEVE_PARAMETERS_FROM_SERVER', false),
        'sp_entity_id' => env('SAML_SP_ENTITY_ID'),
        'sp_assertion_consumer_service' => env('SAML_SP_ASSERTION_CONSUMER_SERVICE'),
        'sp_single_logout_service' => env('SAML_SP_SINGLE_LOGOUT_SERVICE'),
        'sp_name_id_format' => env('SAML_SP_NAME_ID_FORMAT'),
        'sp_x509cert' => env('SAML_SP_X509CERT'),
        'sp_private_key' => env('SAML_SP_PRIVATE_KEY'),
        'sp_x509cert_new' => env('SAML_SP_X509CERT_NEW'),
        'sp_private_key_new' => env('SAML_SP_PRIVATE_KEY_NEW'),
        'sp_assertion_consumer_service_binding' => env('SAML_SP_ASSERTION_CONSUMER_SERVICE_BINDING'),
        'sp_single_logout_service_binding' => env('SAML_SP_SINGLE_LOGOUT_SERVICE_BINDING'),
        'sp_assertion_consumer_service_url' => env('SAML_SP_ASSERTION_CONSUMER_SERVICE_URL'),
        'sp_single_logout_service_url' => env('SAML_SP_SINGLE_LOGOUT_SERVICE_URL'),
        'sp_name_id_format' => env('SAML_SP_NAME_ID_FORMAT'),
        'sp_x509cert' => env('SAML_SP_X509CERT'),
        'sp_private_key' => env('SAML_SP_PRIVATE_KEY'),
        'sp_x509cert_new' => env('SAML_SP_X509CERT_NEW'),
        'sp_private_key_new' => env('SAML_SP_PRIVATE_KEY_NEW'),
        'sp_assertion_consumer_service_binding' => env('SAML_SP_ASSERTION_CONSUMER_SERVICE_BINDING'),
        'sp_single_logout_service_binding' => env('SAML_SP_SINGLE_LOGOUT_SERVICE_BINDING'),
        'sp_assertion_consumer_service_url' => env('SAML_SP_ASSERTION_CONSUMER_SERVICE_URL'),
        'sp_single_logout_service_url' => env('SAML_SP_SINGLE_LOGOUT_SERVICE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Map SAML attributes to local user fields
    |
    */
    'field_mapping' => [
        'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'first_name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
        'last_name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
        'department' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/department',
        'job_title' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/jobtitle',
        'manager' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/manager',
        'groups' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/groups',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache configuration for SAML metadata and sessions
    |
    */
    'cache' => [
        'enabled' => true,
        'prefix' => 'saml:',
        'ttl' => 3600, // 1 hour
    ],
];
