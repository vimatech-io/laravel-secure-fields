<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Models\SecureFieldAuditLog;

class DatabaseAuditLogger implements AuditLogger
{
    private bool $enabled;

    private string $driver;

    private string $channel;

    /**
     * Tracks (model:id:field) pairs already logged in this request.
     * Cleared by flush(), which the service provider calls on termination, so
     * the request boundary holds even where an instance outlives the request.
     *
     * @var array<string, true>
     */
    private array $logged = [];

    /**
     * Pending database audit rows flushed in bulk on destruction.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pending = [];

    public function __construct()
    {
        $this->enabled = (bool) config('secure-fields.audit.enabled', false);
        $this->driver = (string) config('secure-fields.audit.driver', 'log');
        $this->channel = (string) config('secure-fields.audit.log_channel', 'stack');
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function logDecryption(Model $model, string $field, int|string|null $userId = null): void
    {
        if (! $this->enabled) {
            return;
        }

        /** @var int|string $modelKey */
        $modelKey = $model->getKey();
        $dedupKey = get_class($model).':'.$modelKey.':'.$field;

        if (isset($this->logged[$dedupKey])) {
            return;
        }

        $this->logged[$dedupKey] = true;

        $userId = $userId ?? Auth::id();

        if ($this->driver === 'database') {
            $now = now()->toDateTimeString();
            $this->pending[] = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $field,
                'user_id' => $userId,
                'action' => 'decrypt',
                'ip_address' => $this->resolveIpAddress(),
                'user_agent' => $this->resolveUserAgent(),
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        } else {
            Log::channel($this->channel)->info('Secure field accessed', [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $field,
                'user_id' => $userId,
                'action' => 'decrypt',
                'ip_address' => $this->resolveIpAddress(),
            ]);
        }
    }

    public function logRotation(string $model, int $recordsProcessed): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($this->driver === 'database') {
            SecureFieldAuditLog::create([
                'model_type' => $model,
                'model_id' => null,
                'field' => '*',
                'user_id' => Auth::id(),
                'action' => 'key_rotation',
                'ip_address' => $this->resolveIpAddress(),
                'user_agent' => $this->resolveUserAgent(),
                'metadata' => ['records_processed' => $recordsProcessed],
            ]);
        } else {
            Log::channel($this->channel)->info('Key rotation completed', [
                'model' => $model,
                'records_processed' => $recordsProcessed,
                'ip_address' => $this->resolveIpAddress(),
            ]);
        }
    }

    /**
     * Writes the buffered rows and ends the request for this logger. Calling it
     * twice is a no-op: the buffer is taken before the insert, never after.
     */
    public function flush(): void
    {
        $rows = $this->pending;

        $this->pending = [];
        $this->logged = [];

        if ($rows === []) {
            return;
        }

        try {
            SecureFieldAuditLog::insert($rows);
        } catch (\Throwable $e) {
            $this->reportFlushFailure($e, count($rows));
        }
    }

    private function reportFlushFailure(\Throwable $e, int $rows): void
    {
        $message = "secure-fields: audit log flush failed, {$rows} decryption events lost";

        try {
            Log::channel($this->channel)->error($message, ['exception' => $e->getMessage()]);
        } catch (\Throwable) {
            // __destruct can run after the log manager is gone; the loss must still be recorded
            error_log($message.': '.$e->getMessage());
        }
    }

    private function resolveIpAddress(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            return app('request')->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUserAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            return app('request')->userAgent();
        } catch (\Throwable) {
            return null;
        }
    }
}
