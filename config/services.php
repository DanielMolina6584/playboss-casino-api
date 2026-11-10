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

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'firestore_database' => env('FIREBASE_FIRESTORE_DATABASE', '(default)'),
    ],

    'google_drive' => [
        'credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'),
        // Solo en modo OAuth (Gmail personal); se obtiene con php artisan playboss:drive-token.
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
    ],

    // Wompi Colombia. Sandbox: llaves pub_test_/prv_test_ y https://sandbox.wompi.co/v1
    'wompi' => [
        'public_key' => env('WOMPI_PUBLIC_KEY'),
        'private_key' => env('WOMPI_PRIVATE_KEY'),
        'events_secret' => env('WOMPI_EVENTS_SECRET'),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
        'base_url' => env('WOMPI_BASE_URL', 'https://sandbox.wompi.co/v1'),
        'redirect_url' => env('WOMPI_REDIRECT_URL'),
        'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
        'expiration_minutes' => (int) env('WOMPI_EXPIRATION_MINUTES', 60),
    ],

    // reCAPTCHA v3 (Google). Deshabilitado por defecto: en local/tests no se exige.
    'recaptcha' => [
        'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
        'secret' => env('RECAPTCHA_SECRET'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    // Inicio de sesión con Google (Google Identity Services): solo se valida el id_token en el back.
    'google_auth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
    ],

    'payment_gateway' => [
        'driver' => env('PAYMENT_GATEWAY_DRIVER', 'firebase_function'),
        'method_code' => env('PAYMENT_METHOD_CODE', env('PAYMENT_GATEWAY_DRIVER', 'firebase_function') === 'wompi' ? 'WOMPI' : 'FIREBASE'),
        'firebase_function_url' => env('FIREBASE_PAYMENT_FUNCTION_URL'),
        'firebase_function_token' => env('FIREBASE_PAYMENT_FUNCTION_TOKEN'),
        'minimum_amount_minor' => (int) env('PAYMENT_MINIMUM_AMOUNT_MINOR', 100),
    ],

];
