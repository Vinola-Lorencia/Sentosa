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

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
    ],

    'meta' => [
        'phone_number_id' => env('META_PHONE_NUMBER_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'verify_token' => env('META_VERIFY_TOKEN'),
    ],

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

    'whatsapp' => [
        // Shared secret Fonnte must send as ?key=... on the webhook URL.
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET', ''),
    ],

    'shop' => [
        // Shown to WhatsApp customers who pick "Transfer Bank". Left empty in
        // the repo on purpose — set the real values in .env only.
        'bank_name' => env('SHOP_BANK_NAME', ''),
        'bank_account' => env('SHOP_BANK_ACCOUNT', ''),
        'bank_account_name' => env('SHOP_BANK_ACCOUNT_NAME', ''),
        // Owner's WhatsApp number notified when a transfer receipt arrives.
        // Empty = no notification.
        'owner_whatsapp' => env('SHOP_OWNER_WHATSAPP', ''),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'secret_key' => env('SUPABASE_SECRET_KEY'),
        'storage_bucket' => env('SUPABASE_STORAGE_BUCKET'),
    ],

];
