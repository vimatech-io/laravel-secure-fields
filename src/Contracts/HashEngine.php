<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Contracts;

interface HashEngine
{
    /**
     * Generate a deterministic, non-reversible hash for searching.
     */
    public function hash(string $value): string;

    /**
     * Verify a value matches a hash using constant-time comparison.
     */
    public function verify(string $value, string $hash): bool;
}
