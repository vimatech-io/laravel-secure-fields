# Changelog

All notable changes to `vimatech/laravel-secure-fields` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-09-01

### Fixed

- A failed audit log flush is now reported as an `error` on the configured audit channel instead of being swallowed. A trail that empties without saying so is worse than no trail.
- Buffered audit rows are flushed on application termination rather than only in `__destruct()`. Rows were being written after the database connection had already gone, so decryption events could be lost silently on every request.

### Changed

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
