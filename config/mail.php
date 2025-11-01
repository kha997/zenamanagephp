<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as needed.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "postmark", "log", "array"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        ],

        'postmark' => [
            'transport' => 'postmark',
            'token' => env('POSTMARK_TOKEN'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        // Custom providers
        'gmail' => [
            'transport' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => env('GMAIL_PORT', 587),
            'encryption' => 'tls',
            'username' => env('GMAIL_USERNAME'),
            'password' => env('GMAIL_APP_PASSWORD'),
            'timeout' => null,
        ],

        'sendgrid' => [
            'transport' => 'smtp',
            'host' => 'smtp.sendgrid.net',
            'port' => env('SENDGRID_PORT', 587),
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => env('SENDGRID_API_KEY'),
            'timeout' => null,
        ],

        'outlook' => [
            'transport' => 'smtp',
            'host' => 'smtp-mail.outlook.com',
            'port' => env('OUTLOOK_PORT', 587),
            'encryption' => 'tls',
            'username' => env('OUTLOOK_USERNAME'),
            'password' => env('OUTLOOK_PASSWORD'),
            'timeout' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'ZenaManage'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for email queuing
    |
    */

    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'connection' => env('MAIL_QUEUE_CONNECTION', 'default'),
        'queue' => env('MAIL_QUEUE_NAME', 'emails'),
        'retry_after' => env('MAIL_QUEUE_RETRY_AFTER', 90),
        'max_tries' => env('MAIL_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for email sending
    |
    */

    'rate_limits' => [
        'enabled' => env('MAIL_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('MAIL_RATE_LIMIT_PER_MINUTE', 60),
        'max_per_hour' => env('MAIL_RATE_LIMIT_PER_HOUR', 1000),
        'max_per_day' => env('MAIL_RATE_LIMIT_PER_DAY', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Template Caching
    |--------------------------------------------------------------------------
    |
    | Configuration for email template caching
    |
    */

    'template_cache' => [
        'enabled' => env('MAIL_TEMPLATE_CACHE_ENABLED', true),
        'ttl' => env('MAIL_TEMPLATE_CACHE_TTL', 3600), // 1 hour
        'driver' => env('MAIL_TEMPLATE_CACHE_DRIVER', 'file'),
    ],
];
