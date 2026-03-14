<?php

/**
 * Feature Flags / Kill-Switch Configuration
 *
 * Enable/disable any module, carrier, or feature instantly.
 * Override in .env: FEATURE_CARRIER_ARAMEX=true
 *
 * Usage: config('features.carrier_aramex')
 *        if (config('features.phase2_crud')) { ... }
 *
 * NEW FILE: config/features.php
 */
return [

    // ── Carrier Integrations ──
    'carrier_aramex'  => env('FEATURE_CARRIER_ARAMEX', false),
    'carrier_dhl'     => env('FEATURE_CARRIER_DHL', false),
    'carrier_smsa'    => env('FEATURE_CARRIER_SMSA', false),
    'carrier_fedex'   => env('FEATURE_CARRIER_FEDEX', false),
    'carrier_jnt'     => env('FEATURE_CARRIER_JNT', false),

    // ── Payment Gateways ──
    'payment_moyasar' => env('FEATURE_PAYMENT_MOYASAR', false),
    'payment_stripe'  => env('FEATURE_PAYMENT_STRIPE', false),
    'payment_stcpay'  => env('FEATURE_PAYMENT_STCPAY', false),
    'payment_applepay'=> env('FEATURE_PAYMENT_APPLEPAY', false),

    // ── Customs APIs ──
    'customs_fasah'   => env('FEATURE_CUSTOMS_FASAH', false),
    'customs_zatca'   => env('FEATURE_CUSTOMS_ZATCA', false),

    // ── E-Commerce Platforms ──
    'ecommerce_salla'   => env('FEATURE_ECOMMERCE_SALLA', false),
    'ecommerce_zid'     => env('FEATURE_ECOMMERCE_ZID', false),
    'ecommerce_shopify' => env('FEATURE_ECOMMERCE_SHOPIFY', false),

    // ── Notification Channels ──
    'notify_email' => env('FEATURE_NOTIFY_EMAIL', false),
    'notify_sms'   => env('FEATURE_NOTIFY_SMS', false),
    'notify_push'  => env('FEATURE_NOTIFY_PUSH', false),

    // ── Core Modules ──
    'sea_freight'        => env('FEATURE_SEA_FREIGHT', true),
    'air_freight'        => env('FEATURE_AIR_FREIGHT', true),
    'land_transport'     => env('FEATURE_LAND_TRANSPORT', true),
    'customs_clearance'  => env('FEATURE_CUSTOMS_CLEARANCE', true),
    'phase2_crud'        => env('FEATURE_PHASE2_CRUD', true),
    'financial_auto_invoice' => env('FEATURE_FINANCIAL_AUTO_INVOICE', true),
    'refund_workflow'    => env('FEATURE_REFUND_WORKFLOW', true),

    // ── AI/ML Features ──
    'ai_anomaly_detection' => env('FEATURE_AI_ANOMALY', false),
    'ai_delay_prediction'  => env('FEATURE_AI_DELAY', false),
    'ai_risk_scoring'      => env('FEATURE_AI_RISK', false),

    // ── Safety ──
    'sandbox_mode' => env('FEATURE_SANDBOX_MODE', true),
    'demo_data'    => env('FEATURE_DEMO_DATA', false),
];
