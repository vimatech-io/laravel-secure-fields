<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuditLogger
{
    /**
     * Log a decryption event.
     */
    public function logDecryption(Model $model, string $field, int|string|null $userId = null): void;

    /**
     * Log a key rotation event.
     */
    public function logRotation(string $model, int $recordsProcessed): void;
}
