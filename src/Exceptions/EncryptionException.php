<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Exceptions;

class EncryptionException extends SecureFieldsException
{
    public static function encryptionFailed(): self
    {
        return new self('Encryption operation failed.');
    }

    public static function invalidKey(): self
    {
        return new self('The encryption key is invalid. Key must be 32 bytes for AES-256-GCM.');
    }
}
