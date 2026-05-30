<?php

declare(strict_types=1);

use VimaTech\SecureFields\Exceptions\EncryptionException;
use VimaTech\SecureFields\Hashing\HmacHashEngine;

// 32-byte keys (minimum required)
const HMAC_KEY_A = 'test-secret-key-must-be-32-bytes';
const HMAC_KEY_B = 'other-secret-key-must-be-32-bytes';

beforeEach(function () {
    $this->hashEngine = new HmacHashEngine(HMAC_KEY_A);
});

test('generates deterministic hash', function () {
    $hash1 = $this->hashEngine->hash('john@example.com');
    $hash2 = $this->hashEngine->hash('john@example.com');

    expect($hash1)->toBe($hash2);
});

test('hash is case-insensitive', function () {
    expect($this->hashEngine->hash('John@Example.com'))
        ->toBe($this->hashEngine->hash('john@example.com'));
});

test('trims whitespace before hashing', function () {
    expect($this->hashEngine->hash('  john@example.com  '))
        ->toBe($this->hashEngine->hash('john@example.com'));
});

test('different values produce different hashes', function () {
    expect($this->hashEngine->hash('john@example.com'))
        ->not->toBe($this->hashEngine->hash('jane@example.com'));
});

test('different keys produce different hashes', function () {
    $other = new HmacHashEngine(HMAC_KEY_B);

    expect($this->hashEngine->hash('test@example.com'))
        ->not->toBe($other->hash('test@example.com'));
});

test('verifies hash correctly', function () {
    $hash = $this->hashEngine->hash('john@example.com');

    expect($this->hashEngine->verify('john@example.com', $hash))->toBeTrue();
    expect($this->hashEngine->verify('wrong@example.com', $hash))->toBeFalse();
});

test('hash output is 64 character hex string', function () {
    $hash = $this->hashEngine->hash('test');

    expect(strlen($hash))->toBe(64);
    expect(ctype_xdigit($hash))->toBeTrue();
});

test('throws on key shorter than 32 bytes', function () {
    expect(fn () => new HmacHashEngine('too-short'))
        ->toThrow(EncryptionException::class);
});
