<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use VimaTech\SecureFields\Tests\Fixtures\TestUser;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    config()->set('secure-fields.audit.enabled', true);
    config()->set('secure-fields.audit.driver', 'database');
    config()->set('secure-fields.audit.log_channel', 'audit-test');
});

test('a failed audit flush is reported on the configured channel', function () {
    $user = TestUser::create(['email' => 'audit@example.com']);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains((string) $message, 'audit log flush failed'));

    Log::shouldReceive('channel')->with('audit-test')->andReturn($logger);

    // the audit table is deliberately not migrated here, so the insert fails
    TestUser::find($user->id)->email;

    app()->terminate();
});

test('audit rows reach the table when it exists', function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    $user = TestUser::create(['email' => 'audit@example.com']);
    TestUser::find($user->id)->email;

    expect(DB::table('secure_field_audit_logs')->count())->toBe(0);

    app()->terminate();

    $row = DB::table('secure_field_audit_logs')->first();

    expect($row)->not->toBeNull()
        ->and($row->field)->toBe('email')
        ->and($row->action)->toBe('decrypt');
});
