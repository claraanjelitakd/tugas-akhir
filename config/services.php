<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'linkedin' => [
        'provider' => env('LINKEDIN_PROVIDER', 'official'),
        'driver' => env('LINKEDIN_DRIVER', 'mock'),
        'mock_path' => env('LINKEDIN_MOCK_PATH', storage_path('app/mock/linkedin')),
        'api_base_url' => env('LINKEDIN_API_BASE_URL'),
        'api_key' => env('LINKEDIN_API_KEY'),
    ],

    'apify' => [
        'api_token' => env('APIFY_API_TOKEN'),
        'linkedin_actor_id' => env(
            'APIFY_LINKEDIN_ACTOR_ID',
            'data_forge_org~linkedin-scraper'
        ),
        'mock_path' => env('APIFY_MOCK_PATH', storage_path('app/mock/apify')),
    ],

];
