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

    //configuración de supabase
    
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        // Lo consulta el controlador de login del panel.
        'key' => env('SUPABASE_PUBLIC_KEY', env('SUPABASE_ANON_KEY')),
        // Se conserva para código que utilice el nombre anterior.
        'anon_key' => env('SUPABASE_ANON_KEY', env('SUPABASE_PUBLIC_KEY')),
        // No es necesaria para el login del panel.
        'service_key' => env('SUPABASE_SERVICE_KEY'),
    ],


];
