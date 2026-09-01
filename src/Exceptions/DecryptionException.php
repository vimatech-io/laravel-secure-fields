<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Exceptions;

class DecryptionException extends SecureFieldsException
{
    public static function invalidPayload(): self
    {
        return new self('The stored value is not a payload this package produced, or it has been altered.');
    }

    public static function authTagMismatch(): self
    {
        return new self('The stored value failed authentication. It was encrypted with a different key, or it has been altered.');
    }

    public static function invalidKey(): self
    {
        return new self('The old key given to key rotation is not valid. It must be exactly 32 bytes once base64-decoded.');
    }

    public static function forField(string $model, string $field, self $previous): self
    {
        return new self(
            "Failed to decrypt [{$model}::{$field}]. The stored value was encrypted with a different key, or it has been altered.",
            0,
            $previous
        );
    }
}
