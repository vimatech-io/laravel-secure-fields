<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Contracts\Encryptor;

/**
 * @implements CastsAttributes<string, string>
 */
class SecureField implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $decrypted = app(Encryptor::class)->decrypt($value);
        app(AuditLogger::class)->logDecryption($model, $key);

        return $decrypted;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(Encryptor::class)->encrypt((string) $value);
    }
}
