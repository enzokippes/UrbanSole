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

    'bank' => [
        'alias' => env('STORE_BANK_ALIAS', 'URBANSOLE.TIENDA.MP'),
        'cbu' => env('STORE_BANK_CBU', '0000003100048291038472'),
        'holder' => env('STORE_BANK_HOLDER', 'UrbanSole Indumentaria SRL'),
        'bank_name' => env('STORE_BANK_NAME', 'Mercado Pago / Banco Galicia'),
    ],

];
