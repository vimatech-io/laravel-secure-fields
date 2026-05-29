<?php

declare(strict_types=1);

use VimaTech\SecureFields\Hashing\HmacHashEngine;

beforeEach(function () {
    $this->hashEngine = new HmacHashEngine('test-secret-key');
});

test('generates deterministic hash', function () {
    $value = 'john@example.com';

    $hash1 = $this->hashEngine->hash($value);
    $hash2 = $this->hashEngine->hash($value);

    expect($hash1)->toBe($hash2);
});

test('hash is case-insensitive', function () {
    $hash1 = $this->hashEngine->hash('John@Example.com');
    $hash2 = $this->hashEngine->hash('john@example.com');

    expect($hash1)->toBe($hash2);
});

test('trims whitespace before hashing', function () {
    $hash1 = $this->hashEngine->hash('  john@example.com  ');
    $hash2 = $this->hashEngine->hash('john@example.com');

    expect($hash1)->toBe($hash2);
});

test('different values produce different hashes', function () {
    $hash1 = $this->hashEngine->hash('john@example.com');
    $hash2 = $this->hashEngine->hash('jane@example.com');

    expect($hash1)->not->toBe($hash2);
});

test('different keys produce different hashes', function () {
    $other = new HmacHashEngine('other-secret-key');

    $hash1 = $this->hashEngine->hash('test@example.com');
    $hash2 = $other->hash('test@example.com');

    expect($hash1)->not->toBe($hash2);
});

test('verifies hash correctly', function () {
    $value = 'john@example.com';
    $hash = $this->hashEngine->hash($value);

    expect($this->hashEngine->verify($value, $hash))->toBeTrue();
    expect($this->hashEngine->verify('wrong@example.com', $hash))->toBeFalse();
});

test('hash output is 64 character hex string', function () {
    $hash = $this->hashEngine->hash('test');

    expect(strlen($hash))->toBe(64);
    expect(ctype_xdigit($hash))->toBeTrue();
});
