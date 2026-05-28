<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Exceptions;

class DecryptionException extends SecureFieldsException
{
    public static function invalidPayload(): self
    {
        return new self('The encrypted payload is invalid or has been tampered with.');
    }

    public static function authTagMismatch(): self
    {
        return new self('Authentication tag verification failed. Data may have been tampered with.');
    }

    public static function invalidKey(): self
    {
        return new self('The encryption key is invalid or missing.');
    }
}
