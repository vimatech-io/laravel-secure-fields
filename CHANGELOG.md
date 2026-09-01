# Changelog

All notable changes to `vimatech/laravel-secure-fields` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-09-01

### Changed

- **Breaking.** The package refuses to encrypt or hash when no dedicated key is configured. It previously derived one from `APP_KEY` without saying so, so an application could run for months believing it had key separation while every stored value silently depended on `APP_KEY` — and rotating `APP_KEY`, a routine documented Laravel operation, made that data unreadable. Set `SECURE_FIELDS_KEY` and `SECURE_FIELDS_HASH_KEY`, or opt back into derivation with `derive_keys_from_app_key`. See *Upgrading from 1.x*.
- **Breaking.** `AuditLogger::logDecryption()` takes `int|string|null $userId` instead of `?int`, so applications whose users have UUID or ULID keys can be audited. Anything implementing the interface must widen that parameter.
- The published audit migration stores `model_id` and `user_id` as `string(64)` rather than `unsignedBigInteger`, so models without auto-incrementing keys can be audited. Existing installations keep the table they migrated; alter those two columns only if you need non-integer keys.
- Exception messages now say what to do rather than only what failed, and a decryption failure names the model and field that failed instead of reporting a bare `Decryption failed.`
- A `SECURE_FIELDS_HASH_KEY` shorter than 32 characters was reported with the AES key's message, which pointed at the wrong environment variable. It now has its own.

### Added

- `derive_keys_from_app_key` (env `SECURE_FIELDS_DERIVE_KEYS_FROM_APP_KEY`), off by default. Deriving both keys from `APP_KEY` is still supported, but it is now a choice rather than what happens when you configure nothing.

### Upgrading from 1.x

If both `SECURE_FIELDS_KEY` and `SECURE_FIELDS_HASH_KEY` are already set, there is nothing to do.

If you relied on the `APP_KEY` fallback, **do not simply set a new key** — every value you have stored was encrypted with the derived one and would become unreadable. Pick one of:

1. **Keep the derivation, explicitly.** Set `SECURE_FIELDS_DERIVE_KEYS_FROM_APP_KEY=true`. Nothing else changes, and your data stays readable. The coupling to `APP_KEY` remains: rotating `APP_KEY` still destroys the data.

2. **Pin the derived encryption key, then move off it.** Compute it once:

   ```php
   base64_encode(hash_hkdf('sha256', base64_decode(substr(config('app.key'), 7)), 32, 'secure-fields-encryption'))
   ```

   Set the result as `SECURE_FIELDS_KEY`. Existing values stay readable, and `APP_KEY` can then be rotated safely. Move to a freshly generated key afterwards with `secure-fields:rotate`.

The hash key has no equivalent of step 2: the derived value is 32 raw bytes used verbatim as the HMAC key, so it cannot be written into a `.env` value. Either keep `derive_keys_from_app_key` enabled, or set a dedicated `SECURE_FIELDS_HASH_KEY` and rebuild every blind index — a different hash key invalidates all of them, and `secureWhere()` stops matching until each record is read and its searchable fields re-assigned and saved. If you want to pin the derived hash key instead, decode it inside your published config file rather than through the environment.

## [1.1.0] - 2026-09-01

### Added

- `secure-fields:rotate` is idempotent. Each value is tried against the current key first and left alone when it already reads, so an interrupted rotation resumes by re-running the same command with no state to repair.
- `secure-fields:rotate --continue-on-error` skips values that neither key can read. Without it the command now stops at the first such value.

### Fixed

- A failed audit log flush is now reported as an `error` on the configured audit channel instead of being swallowed. A trail that empties without saying so is worse than no trail.
- Buffered audit rows are flushed on application termination rather than only in `__destruct()`. Rows were being written after the database connection had already gone, so decryption events could be lost silently on every request.

### Removed

- Config keys `cipher`, `hashing.algorithm`, `rotation.chunk_size`, `rotation.queue` and `rotation.connection`. None of them was ever read: the cipher and hash algorithm are fixed in code, the rotation chunk size comes from `--chunk`, and no queued rotation exists. If you published the config file your copy still holds them — they never drove anything, and deleting them from your copy changes no behaviour.

### Changed

- `secure-fields:rotate` stops instead of continuing when a value can be read with neither key, and writes nothing for the batch it stopped in. It previously logged the record, carried on, and returned a failure exit code at the very end. Pass `--continue-on-error` for the old behaviour.
- `secure-fields:rotate` reports rotated and already-current counts separately, and the audit entry records the number of values actually re-encrypted rather than the number of records read.
- The list of secure fields is resolved in one place, `Support\SecureFieldResolver`. `HasSecureFields` and `secure-fields:rotate` each carried their own copy of the rule and had already drifted apart.

## [1.0.4] - 2026-09-01

### Fixed

- Masking no longer reveals the value when `visibleEnd` is negative. `masked()` and `toMaskedArray()` now throw `SecureFieldsException` instead of shifting the visible window forward.

### Changed

- `masked()` and `toMaskedArray()` share a single masking implementation.
- `SECURITY.md` now states the stored payload format, the AES and HMAC key derivation, and the blind index formula, for forensics and manual recovery.

## [1.0.3] - 2026-06-26

### Changed

- Unify CI into a single workflow; add `.gitattributes` (`export-ignore`) and Packagist version/downloads badges.

## [1.0.2] - 2026-06-06

### Added

- Laravel 13 support.

## [1.0.1] - 2026-06-05

### Changed

- Rename the package to `vimatech/laravel-secure-fields` on Packagist.

## [1.0.0] - 2024-01-01

### Added

- AES-256-GCM encrypted Eloquent casts (`SecureField`, `SecureJson`)
- Searchable encrypted fields via HMAC-SHA256 blind indexes
- `HasSecureFields` trait with automatic hidden serialization
- Field masking support (`masked()`, `toMaskedArray()`)
- Key rotation artisan command with dry-run and batch processing
- Audit logging (database and log channel drivers)
- Config file with full customization
- Facade for manual encrypt/decrypt/hash operations
- PHPStan level max compliance
- Pest test suite
- GitHub Actions CI
