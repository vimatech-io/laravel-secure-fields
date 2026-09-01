<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Services\SecureFieldsManager;
use VimaTech\SecureFields\Tests\Fixtures\TestUser;

function swapEncryptionKey(string $key): void
{
    config()->set('secure-fields.key', $key);
    app()->forgetInstance(Encryptor::class);
    app()->forgetInstance(SecureFieldsManager::class);
}

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    $this->oldKey = base64_encode(random_bytes(32));
    $this->newKey = base64_encode(random_bytes(32));

    swapEncryptionKey($this->oldKey);
});

function rotateTestUsers(array $extra = []): PendingCommand
{
    return test()->artisan('secure-fields:rotate', array_merge([
        'model' => TestUser::class,
        '--old-key' => test()->oldKey,
        '--force' => true,
    ], $extra));
}

test('a second rotation leaves every already-rotated value untouched', function () {
    $user = TestUser::create([
        'email' => 'rotate@example.com',
        'phone' => '+1234567890',
    ]);

    swapEncryptionKey($this->newKey);

    rotateTestUsers()->assertExitCode(0);

    $afterFirst = DB::table('test_users')->where('id', $user->id)->first();
    expect(TestUser::find($user->id)->email)->toBe('rotate@example.com');

    rotateTestUsers()->assertExitCode(0);

    $afterSecond = DB::table('test_users')->where('id', $user->id)->first();

    expect($afterSecond->email)->toBe($afterFirst->email)
        ->and($afterSecond->phone)->toBe($afterFirst->phone)
        ->and(TestUser::find($user->id)->email)->toBe('rotate@example.com');
});

test('rotation stops on a value neither key can read and writes nothing for that batch', function () {
    $user = TestUser::create(['email' => 'good@example.com']);

    swapEncryptionKey($this->newKey);

    DB::table('test_users')->insert([
        'id' => $user->id + 1,
        'email' => 'not a valid payload',
    ]);

    $before = DB::table('test_users')->where('id', $user->id)->value('email');

    rotateTestUsers()->assertExitCode(1);

    expect(DB::table('test_users')->where('id', $user->id)->value('email'))->toBe($before);
});

test('--continue-on-error rotates what it can and still reports failure', function () {
    $user = TestUser::create(['email' => 'good@example.com']);

    swapEncryptionKey($this->newKey);

    DB::table('test_users')->insert([
        'id' => $user->id + 1,
        'email' => 'not a valid payload',
    ]);

    $before = DB::table('test_users')->where('id', $user->id)->value('email');

    rotateTestUsers(['--continue-on-error' => true])->assertExitCode(1);

    expect(DB::table('test_users')->where('id', $user->id)->value('email'))->not->toBe($before)
        ->and(TestUser::find($user->id)->email)->toBe('good@example.com');
});
