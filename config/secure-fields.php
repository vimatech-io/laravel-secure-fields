<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | The base64-encoded 32-byte key used for AES-256-GCM encryption.
    |
    */
    'key' => env('SECURE_FIELDS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Key Derivation From APP_KEY
    |--------------------------------------------------------------------------
    |
    | Without a dedicated key the package refuses to encrypt. Enable this to
    | derive one from APP_KEY instead. Doing so ties every stored value to
    | APP_KEY: rotating it, or setting a dedicated key later, leaves everything
    | already written unreadable.
    |
    */
    'derive_keys_from_app_key' => env('SECURE_FIELDS_DERIVE_KEYS_FROM_APP_KEY', false),

    /*
    |--------------------------------------------------------------------------
    | Hashing
    |--------------------------------------------------------------------------
    |
    | Configuration for deterministic hash indexes used in searchable fields.
    | At least 32 characters, used verbatim as the HMAC key.
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
