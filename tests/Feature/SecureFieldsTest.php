<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use VimaTech\SecureFields\Services\SecureFieldsManager;
use VimaTech\SecureFields\Tests\Fixtures\TestUser;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
});

test('encrypts field on save and decrypts on retrieval', function () {
    $user = TestUser::create([
        'email' => 'john@example.com',
        'phone' => '+1234567890',
    ]);

    // Verify raw DB value is encrypted (not plaintext)
    $raw = DB::table('test_users')
        ->where('id', $user->id)
        ->first();

    expect($raw->email)->not->toBe('john@example.com');
    expect($raw->phone)->not->toBe('+1234567890');

    // Verify retrieval decrypts properly
    $freshUser = TestUser::find($user->id);
    expect($freshUser->email)->toBe('john@example.com');
    expect($freshUser->phone)->toBe('+1234567890');
});

test('handles null values gracefully', function () {
    $user = TestUser::create([
        'email' => null,
        'phone' => null,
    ]);

    $freshUser = TestUser::find($user->id);
    expect($freshUser->email)->toBeNull();
    expect($freshUser->phone)->toBeNull();
});

test('encrypted values are unique per save (random IV)', function () {
    $user1 = TestUser::create(['email' => 'same@example.com']);
    $user2 = TestUser::create(['email' => 'same@example.com']);

    $raw1 = DB::table('test_users')
        ->where('id', $user1->id)->first();
    $raw2 = DB::table('test_users')
        ->where('id', $user2->id)->first();

    expect($raw1->email)->not->toBe($raw2->email);
});

test('stores hash index for searchable fields', function () {
    $user = TestUser::create([
        'email' => 'search@example.com',
        'phone' => '+9876543210',
    ]);

    $raw = DB::table('test_users')
        ->where('id', $user->id)->first();

    expect($raw->email_hash)->not->toBeNull();
    expect($raw->phone_hash)->not->toBeNull();
    expect(strlen($raw->email_hash))->toBe(64);
});

test('secureWhere finds records by encrypted field', function () {
    TestUser::create(['email' => 'findme@example.com']);
    TestUser::create(['email' => 'other@example.com']);

    $found = TestUser::secureWhere('email', 'findme@example.com')->first();

    expect($found)->not->toBeNull();
    expect($found->email)->toBe('findme@example.com');
});

test('secureWhere returns null for non-matching value', function () {
    TestUser::create(['email' => 'exists@example.com']);

    $found = TestUser::secureWhere('email', 'doesnotexist@example.com')->first();

    expect($found)->toBeNull();
});

test('encrypts and decrypts JSON fields', function () {
    $metadata = ['key1' => 'value1', 'nested' => ['a' => 1, 'b' => 2]];

    $user = TestUser::create([
        'email' => 'json@example.com',
        'metadata' => $metadata,
    ]);

    $freshUser = TestUser::find($user->id);
    expect($freshUser->metadata)->toBe($metadata);

    // Verify stored value is not plaintext JSON
    $raw = DB::table('test_users')
        ->where('id', $user->id)->first();
    expect($raw->metadata)->not->toBe(json_encode($metadata));
});

test('masked output works correctly', function () {
    $user = TestUser::create([
        'phone' => '+1234567890',
    ]);

    $freshUser = TestUser::find($user->id);
    $masked = $freshUser->masked('phone');

    expect($masked)->toBe('*******7890');
});

test('masked output with short values', function () {
    $user = TestUser::create([
        'phone' => '1234',
    ]);

    $freshUser = TestUser::find($user->id);
    $masked = $freshUser->masked('phone');

    expect($masked)->toBe('****');
});

test('masked output with null values', function () {
    $user = TestUser::create(['phone' => null]);

    $freshUser = TestUser::find($user->id);
    expect($freshUser->masked('phone'))->toBeNull();
});

test('secure fields are hidden from serialization', function () {
    $user = TestUser::create([
        'email' => 'hidden@example.com',
        'phone' => '+1111111111',
    ]);

    $freshUser = TestUser::find($user->id);
    $array = $freshUser->toArray();

    expect($array)->not->toHaveKey('email');
    expect($array)->not->toHaveKey('phone');
    expect($array)->not->toHaveKey('email_hash');
    expect($array)->not->toHaveKey('phone_hash');
});

test('toSecureArray excludes all encrypted fields', function () {
    $user = TestUser::create([
        'email' => 'test@example.com',
        'phone' => '+1234567890',
    ]);

    $freshUser = TestUser::find($user->id);
    $secureArray = $freshUser->toSecureArray();

    expect($secureArray)->not->toHaveKey('email');
    expect($secureArray)->not->toHaveKey('phone');
});

test('toMaskedArray returns masked secure fields', function () {
    $user = TestUser::create([
        'email' => 'masked@example.com',
        'phone' => '+1234567890',
    ]);

    $freshUser = TestUser::find($user->id);
    $masked = $freshUser->toMaskedArray();

    expect($masked['email'])->toContain('*');
    expect($masked['phone'])->toBe('*******7890');
});

test('updating encrypted field re-encrypts the value', function () {
    $user = TestUser::create(['email' => 'original@example.com']);

    $raw1 = DB::table('test_users')
        ->where('id', $user->id)->value('email');

    $user->email = 'updated@example.com';
    $user->save();

    $raw2 = DB::table('test_users')
        ->where('id', $user->id)->value('email');

    expect($raw2)->not->toBe($raw1);

    $freshUser = TestUser::find($user->id);
    expect($freshUser->email)->toBe('updated@example.com');
});

test('facade encrypt and decrypt work', function () {
    $facade = app(SecureFieldsManager::class);

    $encrypted = $facade->encrypt('test value');
    $decrypted = $facade->decrypt($encrypted);

    expect($decrypted)->toBe('test value');
});

test('facade hash and verify work', function () {
    $facade = app(SecureFieldsManager::class);

    $hash = $facade->hash('test@example.com');
    expect($facade->verifyHash('test@example.com', $hash))->toBeTrue();
    expect($facade->verifyHash('wrong@example.com', $hash))->toBeFalse();
});
