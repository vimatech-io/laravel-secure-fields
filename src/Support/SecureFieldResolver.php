<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Support;

use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;

final class SecureFieldResolver
{
    /**
     * @var array<class-string<Model>, array<string>>
     */
    private static array $cache = [];

    /**
     * Cached per class: casts are declared statically and never change at runtime.
     *
     * @return array<string>
     */
    public static function resolve(Model $model): array
    {
        $class = $model::class;

        if (! array_key_exists($class, self::$cache)) {
            self::$cache[$class] = array_keys(array_filter(
                $model->getCasts(),
                fn (mixed $cast) => is_string($cast)
                    && (is_a($cast, SecureField::class, true) || is_a($cast, SecureJson::class, true))
            ));
        }

        return self::$cache[$class];
    }
}
