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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shopee' => [
        'partner_id' => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'access_token' => env('SHOPEE_ACCESS_TOKEN'),
        'refresh_token' => env('SHOPEE_REFRESH_TOKEN'),
        'shop_id' => env('SHOPEE_SHOP_ID'),
        'base_url' => env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'),
        'webhook_secret' => env('SHOPEE_WEBHOOK_SECRET', env('SHOPEE_PARTNER_KEY')),
        'auto_stock_sync' => env('SHOPEE_AUTO_STOCK_SYNC', false),
    ],

];
