<?php

declare(strict_types=1);

use VimaTech\SecureFields\Support\SecureFieldResolver;
use VimaTech\SecureFields\Tests\Fixtures\TestUser;

test('resolves every secure cast and nothing else', function () {
    expect(SecureFieldResolver::resolve(new TestUser))
        ->toBe(['email', 'phone', 'ssn', 'notes', 'metadata']);
});

test('ignores casts that are not secure', function () {
    expect(SecureFieldResolver::resolve(new TestUser))
        ->not->toContain('id');
});
