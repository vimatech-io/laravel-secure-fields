<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Models;

use Illuminate\Database\Eloquent\Model;

class SecureFieldAuditLog extends Model
{
    protected $table = 'secure_field_audit_logs';

    protected $fillable = [
        'model_type',
        'model_id',
        'field',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
