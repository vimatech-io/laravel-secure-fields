# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 2.x     | ✅                 |

## Reporting a Vulnerability

If you discover a security vulnerability within Laravel Secure Fields, please send an email to **hello@adelzemzemi.com**.

**Please do not report security vulnerabilities through public GitHub issues.**

Every report is read and taken seriously.

## What to include

- A description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Response timeline

This package is maintained on a best-effort basis. The timings below are targets, not guarantees.

- **Acknowledgement:** we aim to acknowledge your report within 5 business days.
- **Initial assessment:** we aim to share a first assessment within 10 business days of acknowledgement.
- **Fix release:** prioritised according to severity. We will keep you informed of progress.

If you have not received an acknowledgement after 10 business days, please send a follow-up email to the same address.

## Disclosure policy

We follow coordinated disclosure. Please give us a reasonable opportunity to release a fix before any public disclosure, and we will agree the timing with you.

## Stored payload format

Recorded here for forensics and manual recovery. `SecureField`, `SecureJson` and the
`SecureFields` facade all store a value as:

```
base64( {"iv":base64(iv),"ciphertext":base64(ciphertext),"tag":base64(tag)} )
```

The outer layer is base64 over the JSON document; each of the three components is
base64-encoded individually inside it. The cipher is AES-256-GCM with a fresh random
12-byte IV per encryption and a 16-byte authentication tag, so the same plaintext never
produces the same payload twice, and any modification of the IV, ciphertext or tag makes
decryption fail rather than return altered plaintext.

The 32-byte AES key is `base64_decode(SECURE_FIELDS_KEY)`. When that variable is unset it
is derived instead as `hash_hkdf('sha256', <decoded APP_KEY>, 32, 'secure-fields-encryption')`.

Blind index columns (`<field>_hash`) hold `hash_hmac('sha256', mb_strtolower(trim($value)), $hashKey)`
as lowercase hex. The HMAC key is `SECURE_FIELDS_HASH_KEY` used verbatim, or
`hash_hkdf('sha256', <decoded APP_KEY>, 32, 'secure-fields-hashing')` when unset.
