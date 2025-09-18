<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced password policy settings
    |
    */

    'policy' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
        'history_count' => env('PASSWORD_HISTORY_COUNT', 5),
        'max_failed_attempts' => env('PASSWORD_MAX_FAILED_ATTEMPTS', 5),
        'lockout_duration_minutes' => env('PASSWORD_LOCKOUT_DURATION', 30),
        'common_passwords_check' => env('PASSWORD_COMMON_CHECK', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Password Strength Scoring
    |--------------------------------------------------------------------------
    |
    | Password strength calculation weights
    |
    */
    'strength' => [
        'length_weight' => 20,
        'uppercase_weight' => 20,
        'lowercase_weight' => 20,
        'numbers_weight' => 20,
        'symbols_weight' => 20,
        'common_penalty' => 30,
        'user_info_penalty' => 20,
        'history_penalty' => 30,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Common Passwords List
    |--------------------------------------------------------------------------
    |
    | List of commonly used passwords to check against
    |
    */
    'common_passwords' => [
        'password', '123456', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', 'monkey',
        '1234567890', 'password1', 'qwerty123', 'dragon', 'master',
        'hello', 'login', 'princess', 'rockyou', '1234567',
        '123123', 'omgpop', '123321', '666666', '18atcskd2w',
        '7777777', '1qaz2wsx', '654321', '555555', '3rjs1la7qe',
        'google', '1q2w3e4r', '123qwe', 'zxcvbnm', '1q2w3e',
    ],
];
