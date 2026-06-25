# Laravel Secure Fields

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vimatech/laravel-secure-fields.svg)](https://packagist.org/packages/vimatech/laravel-secure-fields)
[![CI](https://github.com/vimatech-io/laravel-secure-fields/actions/workflows/ci.yml/badge.svg)](https://github.com/vimatech-io/laravel-secure-fields/actions/workflows/ci.yml)
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
- Laravel 11, 12, or 13
- OpenSSL extension

```bash
composer require vimatech/laravel-secure-fields
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

## Generating Keys

> **Important:** Always generate dedicated keys. Never leave `SECURE_FIELDS_KEY` or `SECURE_FIELDS_HASH_KEY` empty — an empty value silently falls back to a key derived from `APP_KEY`, which means a compromised `APP_KEY` would expose both session/cookie encryption **and** all field-level ciphertext.

Generate a 32-byte encryption key (base64-encoded):

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Generate a 32-byte hash key (hex-encoded or base64):

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add both to your `.env`:

```env
SECURE_FIELDS_KEY=<output of first command>
SECURE_FIELDS_HASH_KEY=<output of second command>
```

**Minimum requirements:**
- `SECURE_FIELDS_KEY` — 32 bytes, base64-encoded (validated at boot)
- `SECURE_FIELDS_HASH_KEY` — minimum 32 bytes/characters (validated at boot)

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
        'email'    => SecureField::class,
        'phone'    => SecureField::class,
        'ssn'      => SecureField::class,
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
    $table->text('email');                       // required field — not nullable
    $table->string('email_hash', 64);            // blind index for searching
    $table->text('phone')->nullable();           // optional field
    $table->string('phone_hash', 64)->nullable();
    $table->text('ssn');
    $table->text('metadata')->nullable();        // optional JSON field
    $table->timestamps();
});
```

> **Important:** Use `TEXT` columns for encrypted fields — encrypted payloads are larger than plaintext. Add `nullable()` only when the field is genuinely optional in your domain. The cast handles `null` values correctly in both cases.

### 3. Use it naturally

```php
// Create — automatically encrypted
$user = User::create([
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'ssn'   => '123-45-6789',
    'metadata' => ['plan' => 'premium', 'preferences' => ['dark_mode' => true]],
]);

// Read — automatically decrypted
echo $user->email; // "john@example.com"

// The database stores encrypted ciphertext — never plaintext
```

## Searchable Encrypted Fields

Search encrypted fields without exposing plaintext:

```php
// Exact-match search on encrypted field
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

### Search normalization

Blind index hashes are **case-insensitive** and **whitespace-trimmed**. These three searches are equivalent and will find the same record:

```php
User::secureWhere('email', 'John@Example.COM')
User::secureWhere('email', '  john@example.com  ')
User::secureWhere('email', 'john@example.com')
```

This normalization is applied consistently on both write and search, so records are always findable regardless of input case.

## Field Masking

Encrypted fields are **hidden by default** from `toArray()` and `toJson()` to prevent accidental exposure. `toMaskedArray()` makes them visible with masking applied:

```php
$user->masked('phone');       // "********7890"
$user->masked('ssn');         // "*******6789"
$user->masked('phone', 2);    // "**********90"

// Returns all model fields with secure fields replaced by masked values
$user->toMaskedArray();       // ['id' => 1, 'phone' => '********7890', ...]
```

You can also mask individual fields with custom parameters:

```php
$user->masked('phone', visibleEnd: 4, maskChar: '#'); // "########7890"
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

Rotate encryption keys without downtime. The rotation command re-encrypts all field values with the new key while the `SECURE_FIELDS_KEY` in your `.env` already points to the new key.

### Rotation workflow

1. Generate a new key: `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`
2. Update `SECURE_FIELDS_KEY` in `.env` to the new key
3. Run the rotation command with the **old** key
4. Verify data integrity
5. Remove the old key from any backups or records

### Running the rotation

The old key is read via a **secure interactive prompt** that does not appear in process listings or shell history:

```bash
# Interactive prompt (recommended — key never appears in shell history)
php artisan secure-fields:rotate "App\Models\User"
# > Enter the old encryption key (base64): [hidden input]

# Or pass via --old-key for automated scripts
# WARNING: --old-key is visible in `ps aux` and shell history
php artisan secure-fields:rotate "App\Models\User" --old-key=BASE64_OLD_KEY

# Preview without persisting
php artisan secure-fields:rotate "App\Models\User" --dry-run

# Rotate specific fields
php artisan secure-fields:rotate "App\Models\User" \
    --fields=email \
    --fields=phone \
    --chunk=1000
```

> **Security note:** For automated pipelines, prefer passing the old key via an environment variable read inside a wrapper script rather than as a CLI argument:
> ```bash
> OLD_KEY=$(vault kv get -field=old_key secret/secure-fields)
> php artisan secure-fields:rotate "App\Models\User" --old-key="$OLD_KEY"
> ```

### Hash key rotation

The `SECURE_FIELDS_HASH_KEY` is **separate** from the encryption key and used only for HMAC blind indexes. If you need to rotate the hash key:

1. Changing `SECURE_FIELDS_HASH_KEY` will invalidate all existing blind indexes — `secureWhere()` queries will return no results for existing records until indexes are rebuilt.
2. A `secure-fields:rehash` command for rebuilding indexes is planned for a future release.
3. Until then, rotate the hash key only during a maintenance window where you can rebuild indexes manually.

## Serialization Protection

Encrypted fields are automatically hidden from `toArray()` and `toJson()` to prevent accidental exposure in API responses or logs:

```php
$user->toArray();        // email, phone, ssn are excluded
$user->toSecureArray();  // same — always excludes all encrypted fields
$user->toMaskedArray();  // includes masked versions of encrypted fields
```

## Audit Logging

The package can log every field decryption event, enabling access trail for GDPR, HIPAA, and SOC 2 compliance.

### Configuration

```env
SECURE_FIELDS_AUDIT=true
SECURE_FIELDS_AUDIT_DRIVER=database   # 'database' or 'log'
SECURE_FIELDS_AUDIT_CHANNEL=stack     # Laravel log channel (for 'log' driver)
```

### What is logged

| Event | Trigger | Recorded fields |
|---|---|---|
| `decrypt` | Reading an encrypted attribute | model, model_id, field, user_id, action, ip_address, user_agent |
| `key_rotation` | `secure-fields:rotate` completes | model, records_processed, user_id, action, ip_address, user_agent |

### Deduplication

Within a single request, the same `(model, id, field)` combination is logged **at most once**, regardless of how many times the attribute is accessed. This prevents log flooding when iterating over collections.

### Database driver

With `SECURE_FIELDS_AUDIT_DRIVER=database`, audit rows are **batched and written in a single INSERT** at the end of the request — not one INSERT per access. This keeps the hot path free of synchronous database writes.

The audit table must be published and migrated before enabling the database driver:

```bash
php artisan vendor:publish --tag=secure-fields-migrations
php artisan migrate
```

### FrankenPHP / Laravel Octane

The `AuditLogger` is bound as `scoped()` in the service container, meaning a fresh instance is created for each request in Octane and FrankenPHP worker mode. The deduplication cache and pending batch are request-scoped and never leak between requests.

### Log driver

The `log` driver writes to a Laravel log channel with no additional database queries — a good default for high-throughput applications.

```env
SECURE_FIELDS_AUDIT=true
SECURE_FIELDS_AUDIT_DRIVER=log
SECURE_FIELDS_AUDIT_CHANNEL=daily
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
    // Base64-encoded 32-byte encryption key.
    // REQUIRED in production — see "Generating Keys" section.
    // Falls back to HKDF derivation from APP_KEY if not set (not recommended).
    'key' => env('SECURE_FIELDS_KEY'),

    'cipher' => 'aes-256-gcm',

    'hashing' => [
        // Minimum 32 bytes. REQUIRED in production.
        // Falls back to HKDF derivation from APP_KEY if not set (not recommended).
        'key'       => env('SECURE_FIELDS_HASH_KEY'),
        'algorithm' => 'sha256',
    ],

    'rotation' => [
        'chunk_size' => 500,
        'queue'      => env('SECURE_FIELDS_QUEUE'),
        'connection' => env('SECURE_FIELDS_QUEUE_CONNECTION'),
    ],

    'masking' => [
        'character'   => '*',
        'visible_end' => 4,
    ],

    'audit' => [
        'enabled'     => env('SECURE_FIELDS_AUDIT', false),
        'driver'      => env('SECURE_FIELDS_AUDIT_DRIVER', 'log'), // 'database' or 'log'
        'log_channel' => env('SECURE_FIELDS_AUDIT_CHANNEL', 'stack'),
    ],
];
```

## Environment Variables

```env
# Encryption key — 32 bytes, base64-encoded (REQUIRED)
SECURE_FIELDS_KEY=

# Hash key for blind indexes — minimum 32 bytes (REQUIRED)
SECURE_FIELDS_HASH_KEY=

# Audit logging
SECURE_FIELDS_AUDIT=false
SECURE_FIELDS_AUDIT_DRIVER=log
SECURE_FIELDS_AUDIT_CHANNEL=stack
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
        'email'    => SecureField::class,
        'phone'    => SecureField::class,
        'ssn'      => SecureField::class,
        'metadata' => SecureJson::class,
    ];

    protected array $secureSearchable = ['email', 'phone'];
}

// 2. Use it naturally
$user = User::create([
    'email'    => 'john@example.com',
    'phone'    => '+1234567890',
    'ssn'      => '123-45-6789',
    'metadata' => ['plan' => 'premium'],
]);

$user->email;           // "john@example.com" (decrypted)
$user->masked('phone'); // "********7890"
$user->masked('ssn');   // "*******6789"

// 3. Search encrypted fields
User::secureWhere('email', 'john@example.com')->first();
User::secureWhere('email', 'JOHN@EXAMPLE.COM')->first(); // same result — case-insensitive

// 4. Serialization is safe by default
$user->toArray();       // email, phone, ssn excluded
$user->toMaskedArray(); // ['id' => 1, 'email' => '**************com', ...]
```

## Security Notes

### Encryption

- Uses **AES-256-GCM** — authenticated encryption providing confidentiality and integrity
- Every encryption generates a **unique random 12-byte IV** — no IV reuse
- **16-byte authentication tags** protect against tampering
- Keys are derived via **HKDF** when using the `APP_KEY` fallback (not recommended for production)

### Key Management

- Always set **dedicated** `SECURE_FIELDS_KEY` and `SECURE_FIELDS_HASH_KEY` values
- The `APP_KEY` fallback couples your session/cookie security to your field encryption — a compromised `APP_KEY` exposes both
- Store keys in a secrets manager (AWS Secrets Manager, HashiCorp Vault, etc.) rather than `.env` files for production

### Searchable Fields

- Uses **HMAC-SHA256** with a separate key for blind indexes
- Hash indexes enable **exact-match only** — no partial search, no LIKE queries
- The hash is **deterministic** but **one-way** — cannot be reversed to plaintext
- Uses **constant-time comparison** to prevent timing attacks
- Search values are **normalized** (lowercased, trimmed) before hashing — ensure data is stored with the same normalization

### Best Practices

- Use a dedicated `SECURE_FIELDS_KEY` separate from `APP_KEY`
- Use a dedicated `SECURE_FIELDS_HASH_KEY` for search indexes
- Rotate encryption keys periodically
- Enable audit logging in production (`SECURE_FIELDS_AUDIT=true`)
- Use `TEXT` columns — encrypted data is larger than plaintext
- Add `nullable()` only when the field is genuinely optional in your domain
- Never log decrypted sensitive values
- Configure `TrustProxies` middleware for accurate IP logging in audit records

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

Built and maintained by [Vimatech](https://vimatech.io).
Created by [Adel Zemzemi](https://github.com/adelzemzemi).
