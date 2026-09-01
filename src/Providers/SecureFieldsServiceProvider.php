<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Providers;

use Illuminate\Support\ServiceProvider;
use VimaTech\SecureFields\Commands\RotateKeysCommand;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Contracts\HashEngine;
use VimaTech\SecureFields\Encryption\AesGcmEncryptor;
use VimaTech\SecureFields\Exceptions\SecureFieldsException;
use VimaTech\SecureFields\Hashing\HmacHashEngine;
use VimaTech\SecureFields\Services\DatabaseAuditLogger;
use VimaTech\SecureFields\Services\SecureFieldsManager;

class SecureFieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/secure-fields.php', 'secure-fields');

        $this->app->singleton(Encryptor::class, function ($app) {
            $key = $this->resolveEncryptionKey();

            return new AesGcmEncryptor($key);
        });

        $this->app->singleton(HashEngine::class, function ($app) {
            $key = $this->resolveHashKey();

            return new HmacHashEngine($key);
        });

        $this->app->scoped(AuditLogger::class, DatabaseAuditLogger::class);

        $this->app->singleton(SecureFieldsManager::class, function () {
            /** @var Encryptor $encryptor */
            $encryptor = app(Encryptor::class);
            /** @var HashEngine $hashEngine */
            $hashEngine = app(HashEngine::class);

            return new SecureFieldsManager($encryptor, $hashEngine);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/secure-fields.php' => config_path('secure-fields.php'),
            ], 'secure-fields-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'secure-fields-migrations');

            $this->commands([
                RotateKeysCommand::class,
            ]);
        }
    }

    private function resolveEncryptionKey(): string
    {
        /** @var string|null $key */
        $key = config('secure-fields.key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (! $this->keyDerivationEnabled()) {
            throw SecureFieldsException::missingEncryptionKey();
        }

        $derived = hash_hkdf('sha256', $this->decodeAppKey(), 32, 'secure-fields-encryption');

        return base64_encode($derived);
    }

    private function resolveHashKey(): string
    {
        /** @var string|null $key */
        $key = config('secure-fields.hashing.key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (! $this->keyDerivationEnabled()) {
            throw SecureFieldsException::missingHashKey();
        }

        return hash_hkdf('sha256', $this->decodeAppKey(), 32, 'secure-fields-hashing');
    }

    private function keyDerivationEnabled(): bool
    {
        return (bool) config('secure-fields.derive_keys_from_app_key', false);
    }

    private function decodeAppKey(): string
    {
        /** @var string $appKey */
        $appKey = config('app.key');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false) {
                throw new \RuntimeException('APP_KEY contains an invalid base64-encoded value.');
            }

            return $decoded;
        }

        return $appKey;
    }
}
