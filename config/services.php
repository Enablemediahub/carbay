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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'arkesel' => [
        'api_key' => env('ARKESEL_API_KEY'),
        'sender_id' => env('ARKESEL_SENDER_ID'),
        'endpoint' => env('ARKESEL_ENDPOINT', 'https://sms.arkesel.com/api/v2/sms/send'),
    ],

    'plate_recognizer' => [
        'api_token' => env('PLATE_RECOGNIZER_API_TOKEN'),
        'endpoint' => env('PLATE_RECOGNIZER_ENDPOINT', 'https://api.platerecognizer.com/v1/plate-reader/'),
        'regions' => array_filter(explode(',', env('PLATE_RECOGNIZER_REGIONS', 'gh'))),
    ],

    'paystack' => [
        'endpoint' => env('PAYSTACK_INITIALIZE_ENDPOINT', 'https://api.paystack.co/transaction/initialize'),
        'transfer_endpoint' => env('PAYSTACK_TRANSFER_ENDPOINT', 'https://api.paystack.co/transfer'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
