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
    /**
     * Tracks (model:id:field) pairs already logged in this request.
     * Safe in FrankenPHP/Octane worker mode because this class is bound
     * via scoped() and is recreated for each request.
     *
     * @var array<string, true>
     */
    private array $logged = [];

    public function logDecryption(Model $model, string $field, ?int $userId = null): void
    {
        if (! config('secure-fields.audit.enabled', false)) {
            return;
        }

        $dedupKey = get_class($model).':'.$model->getKey().':'.$field;

        if (isset($this->logged[$dedupKey])) {
            return;
        }

        $this->logged[$dedupKey] = true;

        $userId = $userId ?? Auth::id();

        if (config('secure-fields.audit.driver') === 'database') {
            SecureFieldAuditLog::create([
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $field,
                'user_id' => $userId,
                'action' => 'decrypt',
                'ip_address' => $this->resolveIpAddress(),
                'user_agent' => $this->resolveUserAgent(),
            ]);
        } else {
            /** @var string $channel */
            $channel = config('secure-fields.audit.log_channel', 'stack');
            Log::channel($channel)->info('Secure field accessed', [
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
        if (! config('secure-fields.audit.enabled', false)) {
            return;
        }

        if (config('secure-fields.audit.driver') === 'database') {
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
            /** @var string $channel */
            $channel = config('secure-fields.audit.log_channel', 'stack');
            Log::channel($channel)->info('Key rotation completed', [
                'model' => $model,
                'records_processed' => $recordsProcessed,
                'ip_address' => $this->resolveIpAddress(),
            ]);
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
