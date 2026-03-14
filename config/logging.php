<?php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => ['channel' => 'null', 'trace' => false],
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => ['single', 'stderr'], 'ignore_exceptions' => false],
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true],
        'stderr' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'debug'), 'handler' => Monolog\Handler\StreamHandler::class, 'with' => ['stream' => 'php://stderr']],
        'audit' => ['driver' => 'daily', 'path' => storage_path('logs/audit.log'), 'level' => 'info', 'days' => 365],
    ],
];
