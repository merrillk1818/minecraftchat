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

    'python_api' => [
        'url'          => env('AI_API_URL', 'http://python-api:8000'),
        'internal_key' => env('AI_INTERNAL_KEY', ''),
    ],

    'turnstile' => [
        'site_key'        => env('TURNSTILE_SITE_KEY', ''),
        'secret_key'      => env('TURNSTILE_SECRET_KEY', ''),
        'site_key_chat'   => env('TURNSTILE_SITE_KEY_CHAT', ''),
        'secret_key_chat' => env('TURNSTILE_SECRET_KEY_CHAT', ''),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
