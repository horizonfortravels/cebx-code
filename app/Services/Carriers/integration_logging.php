<?php

/**
 * Integration Logging Config — E-1: Integration Observability
 *
 * Dedicated logging channel for carrier and payment API calls.
 * Independent from default Laravel logger — does NOT modify config/logging.php.
 *
 * INTEGRATION INSTRUCTIONS:
 * Add this channel to config/logging.php → 'channels' array:
 *
 *   'integration' => [
 *       'driver'    => 'daily',
 *       'path'      => storage_path('logs/integration.log'),
 *       'level'     => env('INTEGRATION_LOG_LEVEL', 'debug'),
 *       'days'      => 30,
 *   ],
 *
 * Or, if you prefer a separate config, place this file as config/integration_logging.php
 * and the IntegrationLogger service will use it as fallback.
 */
return [

    // ═══ Channel Configuration ═══
    'channel' => env('INTEGRATION_LOG_CHANNEL', 'integration'),

    // ═══ Log settings ═══
    'driver' => 'daily',
    'path'   => storage_path('logs/integration.log'),
    'level'  => env('INTEGRATION_LOG_LEVEL', 'debug'),
    'days'   => 30,

    // ═══ Sensitive fields to redact in logs ═══
    'redact_fields' => [
        'password', 'Password', 'secret', 'secret_key', 'api_key',
        'card_number', 'cvv', 'pin', 'AccountPin', 'source_token',
        'card_token', 'token',
    ],

    // ═══ Max payload size to log (bytes) ═══
    'max_payload_size' => 10000,
];
