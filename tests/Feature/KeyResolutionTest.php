<?php

declare(strict_types=1);

use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Contracts\HashEngine;
use VimaTech\SecureFields\Exceptions\SecureFieldsException;
use VimaTech\SecureFields\Services\SecureFieldsManager;

function forgetSecureFieldsBindings(): void
{
    app()->forgetInstance(Encryptor::class);
    app()->forgetInstance(HashEngine::class);
    app()->forgetInstance(SecureFieldsManager::class);
}

beforeEach(function () {
    config()->set('secure-fields.key', null);
    config()->set('secure-fields.hashing.key', null);
    config()->set('secure-fields.derive_keys_from_app_key', false);

    forgetSecureFieldsBindings();
});

test('refuses to encrypt rather than silently deriving a key from APP_KEY', function () {
    expect(fn () => app(Encryptor::class))
        ->toThrow(SecureFieldsException::class, 'SECURE_FIELDS_KEY');
});

test('refuses to hash rather than silently deriving a key from APP_KEY', function () {
    expect(fn () => app(HashEngine::class))
        ->toThrow(SecureFieldsException::class, 'SECURE_FIELDS_HASH_KEY');
});

test('the refusal names the escape hatch as well as the fix', function () {
    expect(fn () => app(Encryptor::class))
        ->toThrow(SecureFieldsException::class, 'derive_keys_from_app_key');
});

test('derivation from APP_KEY still works when it is explicitly chosen', function () {
    config()->set('secure-fields.derive_keys_from_app_key', true);
    forgetSecureFieldsBindings();

    $encryptor = app(Encryptor::class);

    expect($encryptor->decrypt($encryptor->encrypt('value')))->toBe('value')
        ->and(app(HashEngine::class)->hash('value'))->toBeString();
});

test('registering the package does not resolve a key', function () {
    expect(app()->bound(Encryptor::class))->toBeTrue()
        ->and(app()->bound(HashEngine::class))->toBeTrue();
});

test('values written under APP_KEY derivation stay readable once the derived key is pinned', function () {
    config()->set('secure-fields.derive_keys_from_app_key', true);
    forgetSecureFieldsBindings();

    $payload = app(Encryptor::class)->encrypt('sensitive');

    $appKey = base64_decode(substr((string) config('app.key'), 7), true);
    $pinned = base64_encode(hash_hkdf('sha256', (string) $appKey, 32, 'secure-fields-encryption'));

    config()->set('secure-fields.key', $pinned);
    config()->set('secure-fields.derive_keys_from_app_key', false);
    forgetSecureFieldsBindings();

    expect(app(Encryptor::class)->decrypt($payload))->toBe('sensitive');
});
