<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Exceptions;

class EncryptionException extends SecureFieldsException
{
    public static function encryptionFailed(): self
    {
        return new self('Encryption failed. Check that the OpenSSL extension is installed and supports aes-256-gcm.');
    }

    public static function invalidKey(): self
    {
        return new self(
            'SECURE_FIELDS_KEY is not a valid encryption key. It must be exactly 32 bytes once base64-decoded, '
            .'for example from: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;".'
        );
    }

    public static function invalidHashKey(): self
    {
        return new self(
            'SECURE_FIELDS_HASH_KEY is too short. It must be at least 32 characters, '
            .'for example from: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;".'
        );
    }
}
