<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services & Automation (Semana 7 OAuth & Semana 15 n8n)
    |--------------------------------------------------------------------------
    */

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', 'http://localhost:5678/webhook/urbansole'),
        'enabled' => env('N8N_WEBHOOK_ENABLED', true),
        'notify_email' => env('N8N_NOTIFY_EMAIL', 'admin@urbansole.com'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/auth/oauth/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', 'http://localhost:8000/api/auth/oauth/callback'),
    ],

];
