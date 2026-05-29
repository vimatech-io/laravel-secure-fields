# Laravel Secure Fields

[![Tests](https://github.com/vimatech-io/laravel-secure-fields/actions/workflows/ci.yml/badge.svg)](https://github.com/vimatech-io/laravel-secure-fields/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)

**Secure encrypted Eloquent model fields for Laravel.**

Laravel Secure Fields lets you encrypt sensitive database fields with AES-256-GCM while preserving a natural Eloquent developer experience — searchable, maskable, and rotatable.

## Why Laravel Secure Fields?

Most Laravel apps storing sensitive data eventually need to answer:

- How do I encrypt PII fields at rest?
- How do I search encrypted data without decrypting everything?
- How do I rotate keys without downtime?
- How do I prevent accidental plaintext leaks in API responses?

Laravel Secure Fields provides a focused encryption layer for that.

## Feature Matrix

| Feature | Supported |
|---|---|
| AES-256-GCM encryption | ✅ |
| Random IV per encryption | ✅ |
| Auth tag validation | ✅ |
| Searchable encrypted fields (blind index) | ✅ |
| Key rotation command | ✅ |
| Field masking | ✅ |
| Encrypted JSON fields | ✅ |
| Serialization protection | ✅ |
| Audit logging | ✅ |
| Facade | ✅ |
| LIKE / partial search | ❌ (by design) |
| Homomorphic encryption | ❌ |
| UI components | ❌ |

## Laravel Secure Fields vs Laravel's Built-in Encryption

Laravel Secure Fields manages:
- **Field-level** encryption with proper AES-256-GCM
- **Searchable** encrypted fields via blind indexes
- **Key rotation** tooling
- **Serialization safety** and masking

Laravel's `encrypt()` / `Crypt` facade:
- General-purpose encryption
- No searchability
- No field-level tooling
- No rotation command

They are complementary — this package is purpose-built for Eloquent model fields.

## Use Cases

- PII storage (SSN, phone, email)
- GDPR / HIPAA compliance
- Payment-related data
- API keys and secrets storage
- Healthcare records
- Legal documents
- Multi-tenant sensitive data

## Installation

### Requirements

- PHP 8.3+
- Laravel 11+
- OpenSSL extension

> **Note:** This package is not yet published on Packagist. For now, install it via path or VCS repository.

```bash
# Once published:
composer require vimatech-io/laravel-secure-fields

# Or via path (local development):
# In your project's composer.json, add:
# "repositories": [{ "type": "path", "url": "../path-to/laravel-secure-fields" }]
```

### Publish config

```bash
php artisan vendor:publish --tag=secure-fields-config
```

### Publish migrations (optional, for audit logging)

```bash
php artisan vendor:publish --tag=secure-fields-migrations
php artisan migrate
```

## Quick Start

### 1. Add encrypted fields to your model

```php
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Traits\HasSecureFields;

class User extends Model
{
    use HasSecureFields;

    protected $casts = [
        'email' => SecureField::class,
        'phone' => SecureField::class,
        'ssn' => SecureField::class,
        'metadata' => SecureJson::class,
    ];

    protected array $secureSearchable = [
        'email',
        'phone',
    ];
}
```

### 2. Create your migration

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->text('email');           // encrypted value (use TEXT, not VARCHAR)
    $table->string('email_hash', 64); // blind index for searching
    $table->text('phone');
    $table->string('phone_hash', 64);
    $table->text('ssn');
    $table->text('metadata');
    $table->timestamps();
});
```

> **Important:** Use `TEXT` columns for encrypted fields — encrypted payloads are larger than plaintext.

### 3. Use it naturally

```php
// Create — automatically encrypted
$user = User::create([
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'ssn' => '123-45-6789',
    'metadata' => ['plan' => 'premium', 'preferences' => ['dark_mode' => true]],
]);

// Read — automatically decrypted
echo $user->email; // "john@example.com"

// The database stores encrypted ciphertext — never plaintext
```

## Searchable Encrypted Fields

Search encrypted fields without exposing plaintext:

```php
// Find by encrypted field
$user = User::secureWhere('email', 'john@example.com')->first();

// Chain with other queries
$users = User::secureWhere('phone', '+1234567890')
    ->where('active', true)
    ->get();
```

The package stores a deterministic HMAC-SHA256 hash alongside the encrypted value, enabling exact-match queries while the actual data remains encrypted.

### How it works

1. On save: encrypts the value AND stores `HMAC-SHA256(plaintext)` in a `{field}_hash` column
2. On search: hashes the search term and queries the hash column
3. The hash is one-way — it cannot be reversed to obtain the plaintext

## Field Masking

Display sensitive data safely:

```php
$user->masked('phone');       // "********7890"
$user->masked('ssn');         // "*******6789"
$user->masked('phone', 2);   // "**********90"

// Masked array export
$user->toMaskedArray();
```

## Encrypted JSON Fields

Encrypt entire JSON structures:

```php
protected $casts = [
    'metadata' => SecureJson::class,
];

// Works like a normal JSON cast, but encrypted at rest
$user->metadata = ['api_key' => 'sk_live_...', 'tokens' => 42];
$user->save();

echo $user->metadata['api_key']; // "sk_live_..."
```

## Key Rotation

Rotate encryption keys without downtime:

```bash
# Preview what will be rotated
php artisan secure-fields:rotate "App\Models\User" --old-key=BASE64_OLD_KEY --dry-run

# Rotate keys
php artisan secure-fields:rotate "App\Models\User" --old-key=BASE64_OLD_KEY

# Rotate specific fields with custom chunk size
php artisan secure-fields:rotate "App\Models\User" \
    --old-key=BASE64_OLD_KEY \
    --fields=email \
    --fields=phone \
    --chunk=1000
```

### Key rotation workflow

1. Generate a new key: `php -r "echo base64_encode(random_bytes(32));"`
2. Update `SECURE_FIELDS_KEY` in `.env` to the new key
3. Run rotation command with the old key
4. Verify data integrity
5. Remove old key from any backups/records

## Serialization Protection

Encrypted fields are automatically hidden from `toArray()` and `toJson()`:

```php
$user->toArray();        // email, phone, ssn are excluded
$user->toSecureArray();  // explicitly excludes all encrypted fields
$user->toMaskedArray();  // includes masked versions
```

## Facade Usage

```php
use VimaTech\SecureFields\Facades\SecureFields;

// Encrypt/decrypt manually
$encrypted = SecureFields::encrypt('sensitive data');
$decrypted = SecureFields::decrypt($encrypted);

// Hash for searching
$hash = SecureFields::hash('john@example.com');
$matches = SecureFields::verifyHash('john@example.com', $hash); // true
```

## Configuration

```php
// config/secure-fields.php

return [
    // Custom encryption key (base64-encoded 32 bytes)
    // Falls back to deriving from APP_KEY via HKDF
    'key' => env('SECURE_FIELDS_KEY'),

    'cipher' => 'aes-256-gcm',

    'hashing' => [
        'key' => env('SECURE_FIELDS_HASH_KEY'),
        'algorithm' => 'sha256',
    ],

    'rotation' => [
        'chunk_size' => 500,
        'queue' => env('SECURE_FIELDS_QUEUE'),
        'connection' => env('SECURE_FIELDS_QUEUE_CONNECTION'),
    ],

    'masking' => [
        'character' => '*',
        'visible_end' => 4,
    ],

    'audit' => [
        'enabled' => env('SECURE_FIELDS_AUDIT', false),
        'driver' => env('SECURE_FIELDS_AUDIT_DRIVER', 'log'), // 'database' or 'log'
        'log_channel' => env('SECURE_FIELDS_AUDIT_CHANNEL', 'stack'),
    ],
];
```

## Environment Variables

```env
# Optional: Custom encryption key (32 bytes, base64-encoded)
SECURE_FIELDS_KEY=

# Optional: Custom hash key for searchable fields
SECURE_FIELDS_HASH_KEY=

# Optional: Enable audit logging
SECURE_FIELDS_AUDIT=false
SECURE_FIELDS_AUDIT_DRIVER=log
```

## Complete Example

```php
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Traits\HasSecureFields;

// 1. Define your model
class User extends Model
{
    use HasSecureFields;

    protected $casts = [
        'email' => SecureField::class,
        'phone' => SecureField::class,
        'ssn' => SecureField::class,
        'metadata' => SecureJson::class,
    ];

    protected array $secureSearchable = ['email', 'phone'];
}

// 2. Use it naturally
$user = User::create([
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'ssn' => '123-45-6789',
    'metadata' => ['plan' => 'premium'],
]);

$user->email;                    // "john@example.com" (decrypted)
$user->masked('phone');          // "********7890"
$user->masked('ssn');            // "*******6789"

// 3. Search encrypted fields
User::secureWhere('email', 'john@example.com')->first();

// 4. Serialization is safe by default
$user->toArray();                // email, phone, ssn excluded
$user->toMaskedArray();          // masked versions included
```

## Security Notes

### Encryption

- Uses **AES-256-GCM** — authenticated encryption providing confidentiality and integrity
- Every encryption generates a **unique random 12-byte IV** — no IV reuse
- **16-byte authentication tags** protect against tampering
- Keys are derived via **HKDF** when using APP_KEY fallback

### Searchable Fields

- Uses **HMAC-SHA256** with a separate key for blind indexes
- Hash indexes enable **exact-match only** — no partial search, no LIKE queries
- The hash is **deterministic** but **one-way** — cannot reverse to plaintext
- Uses **constant-time comparison** to prevent timing attacks

### Best Practices

- Use a dedicated `SECURE_FIELDS_KEY` separate from `APP_KEY`
- Use a dedicated `SECURE_FIELDS_HASH_KEY` for search indexes
- Rotate keys periodically
- Enable audit logging in production
- Use `TEXT` columns — encrypted data is larger than plaintext
- Never log decrypted sensitive values

## Philosophy

Laravel Secure Fields is intentionally focused.

The package manages:
- **Encryption** at the field level
- **Searchability** via blind indexes
- **Key rotation** tooling
- **Serialization safety**

Design principles:
- Backend-only, UI agnostic
- Security-first defaults
- Laravel-native API
- Minimal configuration required
- Clean and testable

It does not aim to become a permissions framework, a full-disk encryption system, or a key management service.

## Testing

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

## Contributing

Contributions are welcome.

Please ensure:
- Tests pass (`composer test`)
- PHPStan passes (`composer analyse`)
- Code style is formatted with Pint (`composer format`)

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review our [Security Policy](SECURITY.md) for reporting vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

Built and maintained by [VimaTech](https://vimatech.io).
Created by [Adel Zemzemi](https://github.com/adelzemzemi).
