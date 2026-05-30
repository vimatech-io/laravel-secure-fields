<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Exceptions\DecryptionException;

/**
 * @implements CastsAttributes<string, string>
 */
class SecureField implements CastsAttributes
{
    private static ?Encryptor $encryptor = null;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $decrypted = self::encryptor()->decrypt($value);
        } catch (DecryptionException $e) {
            throw new DecryptionException('Decryption failed.', 0, $e);
        }

        app(AuditLogger::class)->logDecryption($model, $key);

        return $decrypted;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::encryptor()->encrypt((string) $value);
    }

    private static function encryptor(): Encryptor
    {
        return self::$encryptor ??= app(Encryptor::class);
    }
}
