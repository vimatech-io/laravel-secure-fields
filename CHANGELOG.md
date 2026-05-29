# Changelog

All notable changes to `laravel-secure-fields` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
