<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Exceptions;

use RuntimeException;

class SecureFieldsException extends RuntimeException
{
    public static function missingEncryptionKey(): self
    {
        return new self(
            'No encryption key is configured. Set SECURE_FIELDS_KEY to a base64-encoded 32-byte key, '
            .'for example with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;". '
            .'If this application already stored values encrypted with a key derived from APP_KEY, '
            .'set secure-fields.derive_keys_from_app_key to true instead — any other key leaves those values unreadable.'
        );
    }

    public static function missingHashKey(): self
    {
        return new self(
            'No hash key is configured. Set SECURE_FIELDS_HASH_KEY to at least 32 characters, '
            .'for example with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;". '
            .'If this application already built blind indexes with a key derived from APP_KEY, '
            .'set secure-fields.derive_keys_from_app_key to true instead — any other key stops secureWhere() from matching.'
        );
    }
}
