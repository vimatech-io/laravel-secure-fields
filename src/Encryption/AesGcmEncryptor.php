<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Encryption;

use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Exceptions\DecryptionException;
use VimaTech\SecureFields\Exceptions\EncryptionException;

class AesGcmEncryptor implements Encryptor
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct(string $key)
    {
        $decodedKey = base64_decode($key, true);

        if ($decodedKey === false || strlen($decodedKey) !== 32) {
            throw EncryptionException::invalidKey();
        }

        $this->key = $decodedKey;
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $value,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw EncryptionException::encryptionFailed();
        }

        $payload = json_encode([
            'iv' => base64_encode($iv),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ], JSON_THROW_ON_ERROR);

        return base64_encode($payload);
    }

    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw DecryptionException::invalidPayload();
        }

        $data = json_decode($decoded, true);

        if (! is_array($data) || ! isset($data['iv'], $data['ciphertext'], $data['tag'])) {
            throw DecryptionException::invalidPayload();
        }

        $iv = base64_decode((string) $data['iv'], true);
        $ciphertext = base64_decode((string) $data['ciphertext'], true);
        $tag = base64_decode((string) $data['tag'], true);

        if ($iv === false || $ciphertext === false || $tag === false) {
            throw DecryptionException::invalidPayload();
        }

        if (strlen($iv) !== self::IV_LENGTH) {
            throw DecryptionException::invalidPayload();
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw DecryptionException::authTagMismatch();
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw DecryptionException::authTagMismatch();
        }

        return $plaintext;
    }

    public function rotate(string $payload, string $oldKey): string
    {
        $decodedOldKey = base64_decode($oldKey, true);

        if ($decodedOldKey === false || strlen($decodedOldKey) !== 32) {
            throw DecryptionException::invalidKey();
        }

        $oldEncryptor = new self($oldKey);
        $plaintext = $oldEncryptor->decrypt($payload);

        return $this->encrypt($plaintext);
    }
}
