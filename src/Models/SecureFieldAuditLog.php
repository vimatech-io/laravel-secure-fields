<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Models;

use Illuminate\Database\Eloquent\Model;

class SecureFieldAuditLog extends Model
{
    protected $table = 'secure_field_audit_logs';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
