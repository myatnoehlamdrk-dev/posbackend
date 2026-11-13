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

    'imgbb' => [
        'api_key' => env('IMGBB_API_KEY'),
        'base_url' => env('IMGBB_BASE_URL', 'https://api.imgbb.com/1/upload'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploaded image storage
    |--------------------------------------------------------------------------
    |
    | 'imgbb' (the default whenever an API key is configured) sends every
    | upload to ImgBB's CDN. That is the only durable option on hosts whose
    | disk is ephemeral: a file written to a Render instance is gone at the
    | next deploy, so the row keeps pointing at a URL that now 404s.
    |
    | 'local' keeps uploads on this server's disk and serves them through
    | /uploads with CORS — faster and with no third-party host in the loop,
    | but only safe when that disk survives restarts (a mounted volume, a
    | normal VPS). Without a key, local is the only working choice.
    |
    */
    'images' => [
        'store' => env('IMAGES_STORE', env('IMGBB_API_KEY') ? 'imgbb' : 'local'),
    ],

];
