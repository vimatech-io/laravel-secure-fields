<?php

declare(strict_types=1);

use VimaTech\SecureFields\Encryption\AesGcmEncryptor;
use VimaTech\SecureFields\Exceptions\DecryptionException;
use VimaTech\SecureFields\Exceptions\EncryptionException;

beforeEach(function () {
    $this->key = base64_encode(random_bytes(32));
    $this->encryptor = new AesGcmEncryptor($this->key);
});

test('encrypts and decrypts a string value', function () {
    $plaintext = 'Hello, World!';

    $encrypted = $this->encryptor->encrypt($plaintext);
    $decrypted = $this->encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
    expect($encrypted)->not->toBe($plaintext);
});

test('produces different ciphertexts for same plaintext (random IV)', function () {
    $plaintext = 'same value';

    $encrypted1 = $this->encryptor->encrypt($plaintext);
    $encrypted2 = $this->encryptor->encrypt($plaintext);

    expect($encrypted1)->not->toBe($encrypted2);
});

test('handles empty string encryption', function () {
    $encrypted = $this->encryptor->encrypt('');
    $decrypted = $this->encryptor->decrypt($encrypted);

    expect($decrypted)->toBe('');
});

test('handles unicode content', function () {
    $plaintext = '日本語テスト 🔐 données sensibles';

    $encrypted = $this->encryptor->encrypt($plaintext);
    $decrypted = $this->encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
});

test('handles long content', function () {
    $plaintext = str_repeat('A', 100000);

    $encrypted = $this->encryptor->encrypt($plaintext);
    $decrypted = $this->encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
});

test('throws on invalid payload', function () {
    $this->encryptor->decrypt('not-valid-base64!!!');
})->throws(DecryptionException::class);

test('throws on tampered ciphertext', function () {
    $encrypted = $this->encryptor->encrypt('secret');

    // Tamper with the payload
    $decoded = base64_decode($encrypted);
    $data = json_decode($decoded, true);
    $ciphertext = base64_decode($data['ciphertext']);
    $ciphertext[0] = chr(ord($ciphertext[0]) ^ 0xFF);
    $data['ciphertext'] = base64_encode($ciphertext);
    $tampered = base64_encode(json_encode($data));

    $this->encryptor->decrypt($tampered);
})->throws(DecryptionException::class);

test('throws on tampered auth tag', function () {
    $encrypted = $this->encryptor->encrypt('secret');

    $decoded = base64_decode($encrypted);
    $data = json_decode($decoded, true);
    $data['tag'] = base64_encode(random_bytes(16));
    $tampered = base64_encode(json_encode($data));

    $this->encryptor->decrypt($tampered);
})->throws(DecryptionException::class);

test('throws on invalid key length', function () {
    new AesGcmEncryptor(base64_encode('short'));
})->throws(EncryptionException::class);

test('throws on non-base64 key', function () {
    new AesGcmEncryptor('not-valid-base64!!!');
})->throws(EncryptionException::class);

test('different keys cannot decrypt each others ciphertexts', function () {
    $otherKey = base64_encode(random_bytes(32));
    $otherEncryptor = new AesGcmEncryptor($otherKey);

    $encrypted = $this->encryptor->encrypt('secret');

    $otherEncryptor->decrypt($encrypted);
})->throws(DecryptionException::class);

test('rotates encryption key', function () {
    $oldKey = $this->key;
    $newKey = base64_encode(random_bytes(32));
    $newEncryptor = new AesGcmEncryptor($newKey);

    $originalPlaintext = 'sensitive data';
    $encrypted = $this->encryptor->encrypt($originalPlaintext);

    $rotated = $newEncryptor->rotate($encrypted, $oldKey);
    $decrypted = $newEncryptor->decrypt($rotated);

    expect($decrypted)->toBe($originalPlaintext);
    expect($rotated)->not->toBe($encrypted);
});
