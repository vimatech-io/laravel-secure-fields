<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Services;

use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Contracts\HashEngine;

class SecureFieldsManager
{
    public function __construct(
        private readonly Encryptor $encryptor,
        private readonly HashEngine $hashEngine,
    ) {}

    public function encrypt(string $value): string
    {
        return $this->encryptor->encrypt($value);
    }

    public function decrypt(string $payload): string
    {
        return $this->encryptor->decrypt($payload);
    }

    public function hash(string $value): string
    {
        return $this->hashEngine->hash($value);
    }

    public function verifyHash(string $value, string $hash): bool
    {
        return $this->hashEngine->verify($value, $hash);
    }

    public function rotate(string $payload, string $oldKey): string
    {
        return $this->encryptor->rotate($payload, $oldKey);
    }
}
