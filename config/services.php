<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // CIN / GSTIN / PAN lookups that pre-fill company forms. "fake" works offline; "codium" is the live API.
    'company_lookup' => [
        'driver' => env('COMPANY_LOOKUP_DRIVER', 'fake'),
        'cache_hours' => 24,
        'codium' => [
            'login_url' => env('CODIUM_API_LOGIN_LINK'),
            'cin_url' => env('CODIUM_API_CIN_LINK'),
            'gst_url' => env('CODIUM_API_GST_LINK'),
            'pan_url' => env('CODIUM_API_PAN_LINK'),
            'company_code' => env('CODIUM_API_COMPANY_CODE'),
            'password' => env('CODIUM_API_PASSWORD'),
            'jwt_secret' => env('CODIUM_API_JWT_SECRET'),
            'surepass_key' => env('CODIUM_API_SUREPASS_KEY'),
            'stack_token' => env('CODIUM_API_STACK_TOKEN'),
            'email' => env('CODIUM_API_EMAIL'),
            'verify_ssl' => (bool) env('CODIUM_API_VERIFY_SSL', true),
            'timeout' => 15,
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
