<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Contracts;

use VimaTech\SecureFields\Exceptions\DecryptionException;

interface Encryptor
{
    /**
     * Encrypt a plaintext value.
     *
     * @return string The encrypted payload (base64-encoded JSON containing iv, ciphertext, tag)
     */
    public function encrypt(string $value): string;

    /**
     * Decrypt an encrypted payload.
     *
     * @param  string  $payload  The encrypted payload
     * @return string The decrypted plaintext
     *
     * @throws DecryptionException
     */
    public function decrypt(string $payload): string;

    /**
     * Rotate encryption: decrypt with old key, re-encrypt with new key.
     */
    public function rotate(string $payload, string $oldKey): string;
}
