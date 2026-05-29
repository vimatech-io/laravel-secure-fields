<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
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

        return app(Encryptor::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(Encryptor::class)->encrypt((string) $value);
    }
}
