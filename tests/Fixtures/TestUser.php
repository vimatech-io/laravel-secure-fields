<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Traits\HasSecureFields;

class TestUser extends Model
{
    use HasSecureFields;

    protected $table = 'test_users';

    protected $guarded = [];

    protected $casts = [
        'email' => SecureField::class,
        'phone' => SecureField::class,
        'ssn' => SecureField::class,
        'notes' => SecureField::class,
        'metadata' => SecureJson::class,
    ];

    protected array $secureSearchable = [
        'email',
        'phone',
    ];
}
