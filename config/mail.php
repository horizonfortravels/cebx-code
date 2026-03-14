<?php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => ['transport' => 'smtp', 'url' => env('MAIL_URL'), 'host' => env('MAIL_HOST', 'smtp.mailgun.org'), 'port' => env('MAIL_PORT', 587), 'encryption' => env('MAIL_ENCRYPTION', 'tls'), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => null, 'local_domain' => env('MAIL_EHLO_DOMAIN')],
        'ses' => ['transport' => 'ses'],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@shipping-gateway.sa'), 'name' => env('MAIL_FROM_NAME', 'Shipping Gateway')],
];
