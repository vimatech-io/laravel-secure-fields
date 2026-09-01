<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | The base64-encoded 32-byte key used for AES-256-GCM encryption.
    | If not set, the package will derive a key from your APP_KEY.
    |
    */
    'key' => env('SECURE_FIELDS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Hashing
    |--------------------------------------------------------------------------
    |
    | Configuration for deterministic hash indexes used in searchable fields.
    |
    */
    'hashing' => [
        'key' => env('SECURE_FIELDS_HASH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Masking
    |--------------------------------------------------------------------------
    |
    | Default configuration for field masking.
    |
    */
    'masking' => [
        'character' => '*',
        'visible_end' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable audit logging for decryption events.
    |
    */
    'audit' => [
        'enabled' => env('SECURE_FIELDS_AUDIT', false),
        'driver' => env('SECURE_FIELDS_AUDIT_DRIVER', 'log'), // 'database' or 'log'
        'log_channel' => env('SECURE_FIELDS_AUDIT_CHANNEL', 'stack'),
    ],

];
