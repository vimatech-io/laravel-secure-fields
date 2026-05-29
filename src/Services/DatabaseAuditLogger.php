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
    public function logDecryption(Model $model, string $field, ?int $userId = null): void
    {
        if (! config('secure-fields.audit.enabled', false)) {
            return;
        }

        $userId = $userId ?? Auth::id();

        if (config('secure-fields.audit.driver') === 'database') {
            SecureFieldAuditLog::create([
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $field,
                'user_id' => $userId,
                'action' => 'decrypt',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
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
                'metadata' => ['records_processed' => $recordsProcessed],
            ]);
        } else {
            /** @var string $channel */
            $channel = config('secure-fields.audit.log_channel', 'stack');
            Log::channel($channel)->info('Key rotation completed', [
                'model' => $model,
                'records_processed' => $recordsProcessed,
            ]);
        }
    }
}
