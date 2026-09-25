# Security policy

`erikwang2013/encryption` is a cryptography component library: a flaw here can silently weaken every application that depends on it. Reports are welcome and will be taken seriously.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

- Email **erik@erik.xyz** with the subject line `SECURITY: erikwang2013/encryption`.
- Or use GitHub's [private vulnerability reporting](https://github.com/erikwang2013/encryption/security/advisories/new).

Please include: affected version(s), what you observed, a minimal reproduction if you have one, and your assessment of impact. If you need an encrypted channel, say so in a first plain message.

You will get an acknowledgement, an assessment, and — if the report is confirmed — credit in the release notes unless you prefer otherwise.

## Supported versions

Fixes are released on the latest 1.x line. Older tags are not maintained; upgrade to the newest release.

## Scope

In scope:

- Cryptographic weaknesses in the shipped implementations (AES-256-GCM, XChaCha20-Poly1305, AES-256-CBC + HMAC, SM2, SM3, SM4-CBC, ZUC, HKDF, PBKDF2)
- Flaws in payload handling: IV/nonce reuse, unauthenticated decryption, padding-oracle exposure, MAC verification, payload framing (`v1 | IV | tag/MAC | ciphertext`)
- Key management defects in `EncryptionManagerFactory` (subkey derivation, key-length validation)
- Timing side channels in verification paths, and any way to make a failed decryption look successful

Out of scope:

- Weak keys, hard-coded secrets, or compromised environments in *your* application
- Choosing an unsuitable algorithm for your threat model (see the "Security notes" section of the README)
- Defects in the `pohoc/crypto-sm` dependency — report those upstream; if it affects this library, tell us too and we will track it here
- Missing features (e.g. no sign/verify API) — that is a feature request, use the issue tracker

## What this library does not protect against

Transport security (use TLS), application-level authorisation, and key storage are outside its remit. Losing the master key means losing the data — there is no recovery path, by design.
