<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Facades;

use Illuminate\Support\Facades\Facade;
use VimaTech\SecureFields\Services\SecureFieldsManager;

/**
 * @method static string encrypt(string $value)
 * @method static string decrypt(string $payload)
 * @method static string hash(string $value)
 * @method static bool verifyHash(string $value, string $hash)
 * @method static string rotate(string $payload, string $oldKey)
 *
 * @see SecureFieldsManager
 */
class SecureFields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SecureFieldsManager::class;
    }
}
