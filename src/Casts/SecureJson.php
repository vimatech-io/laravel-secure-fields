<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Contracts\Encryptor;

/**
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>>
 */
class SecureJson implements CastsAttributes
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $decrypted = app(Encryptor::class)->decrypt($value);
        app(AuditLogger::class)->logDecryption($model, $key);

        /** @var array<string, mixed> */
        return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return app(Encryptor::class)->encrypt($json);
    }
}
