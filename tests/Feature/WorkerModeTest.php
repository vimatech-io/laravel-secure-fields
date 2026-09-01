<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Tests\Fixtures\TestUser;

/** Ends a request the way Laravel Octane does: terminate, then drop scoped instances. */
function endRequestWithScopedFlush(): void
{
    app()->terminate();
    app()->forgetScopedInstances();
}

/** Ends a request the way a hand-written worker script does: terminate only. */
function endRequestKeepingInstances(): void
{
    app()->terminate();
}

function countTerminatingCallbacks(): int
{
    /** @var array<int, mixed> $callbacks */
    $callbacks = (new ReflectionProperty(app(), 'terminatingCallbacks'))->getValue(app());

    return count($callbacks);
}

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    config()->set('secure-fields.audit.enabled', true);
    config()->set('secure-fields.audit.driver', 'database');

    $this->user = TestUser::create(['email' => 'worker@example.com']);
});

test('the deduplication cache does not survive a request that drops scoped instances', function () {
    TestUser::find($this->user->id)->email;
    endRequestWithScopedFlush();

    TestUser::find($this->user->id)->email;
    endRequestWithScopedFlush();

    expect(DB::table('secure_field_audit_logs')->count())->toBe(2);
});

test('the deduplication cache does not survive even when the instance is reused', function () {
    TestUser::find($this->user->id)->email;
    endRequestKeepingInstances();

    $first = app(AuditLogger::class);

    TestUser::find($this->user->id)->email;
    endRequestKeepingInstances();

    expect(app(AuditLogger::class))->toBe($first)
        ->and(DB::table('secure_field_audit_logs')->count())->toBe(2);
});

test('a buffered row is written once even if the flush runs twice', function () {
    TestUser::find($this->user->id)->email;

    app()->terminate();
    app(AuditLogger::class)->flush();
    app()->terminate();

    expect(DB::table('secure_field_audit_logs')->count())->toBe(1);
});

test('the termination hook is registered once, not once per request', function () {
    TestUser::find($this->user->id)->email;
    endRequestWithScopedFlush();

    $afterFirstRequest = countTerminatingCallbacks();

    foreach (range(1, 5) as $ignored) {
        TestUser::find($this->user->id)->email;
        endRequestWithScopedFlush();
    }

    expect(countTerminatingCallbacks())->toBe($afterFirstRequest);
});
