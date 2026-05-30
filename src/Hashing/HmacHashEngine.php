<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Hashing;

use VimaTech\SecureFields\Contracts\HashEngine;
use VimaTech\SecureFields\Exceptions\EncryptionException;

class HmacHashEngine implements HashEngine
{
    private string $key;

    public function __construct(string $key)
    {
        if (strlen($key) < 32) {
            throw EncryptionException::invalidKey();
        }

        $this->key = $key;
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value), 'UTF-8'), $this->key);
    }

    public function verify(string $value, string $hash): bool
    {
        return hash_equals($hash, $this->hash($value));
    }
}
