# AGENTS.md

Guidance for coding agents working in this repository. `CLAUDE.md` covers the swarm/coordination workflow; this file describes the project itself.

## What this is

`erikwang2013/encryption` — a pure PHP cryptography component library, shipped as a Composer package with no framework coupling. It provides **symmetric encryption**, **asymmetric encryption**, **hashing** and **key derivation** (HKDF / PBKDF2) behind one contract system, including the Chinese national algorithms SM2 / SM3 / SM4 / ZUC.

| Item | Value |
|------|-------|
| Language | PHP `^8.0` — no `readonly`, `never`, enums or other 8.1+ syntax |
| Extensions | `ext-openssl` (required), `ext-sodium` (XChaCha20), `ext-gmp` (SM2) |
| Dependencies | runtime `pohoc/crypto-sm`; dev `phpunit ^9.6 \|\| ^10.5` |
| Namespace | `Erikwang2013\Encryption\` → `src/` (psr-4); tests in `tests/` |

## Architecture in one line

Every capability family is **contract interface → registry → facade**: `src/Contract/*Interface.php` → `*Registry` (identifier → implementation, sharing `AbstractRegistry`) → `*Manager` (binds a default identifier). `EncryptionManagerFactory::fromMasterKey()` derives one subkey per algorithm with HMAC-SHA256 and registers every encryptor at once.

More detail: [`README.md`](README.md) and the diagrams in [`docs/`](docs/).

## Commands

| Task | Command |
|------|---------|
| Install | `composer install` |
| Test | `vendor/bin/phpunit` (same as `composer test`) |
| Syntax check | `php -l src/Foo.php` |
| Rebuild a translated diagram | `php scripts/i18n-build-svg.php <lang>` |
| Report translation status only | `php scripts/i18n-build-svg.php <lang> --check` |

Tests must be green before any commit. Tests that need `ext-gmp` / `ext-sodium` skip themselves when the extension is missing — skipped is expected, failed is not.

## Conventions

- Write source comments in the existing Chinese docblock style; keep prose in `README.zh-CN.md` in step with `README.md`.
- Code, class names, method names, algorithm identifiers and file paths are never translated: in `docs/i18n/labels/*.json` they live in the `"keep"` list, prose lives in `"translate"`.
- `docs/i18n/<lang>/*.svg` are **generated** — edit the label dictionary and rebuild, never the SVG.
- Keep every file under 500 lines; new code goes in `src/`, tests in `tests/`, tooling in `scripts/`, documents and images in `docs/`, runnable integrations in `examples/`.
- `examples/plain-php/` is the framework-free integration path — it must stay runnable (`php examples/plain-php/demo.php`) and is covered by `tests/PlainPhpExampleTest.php`.

## Release

1. `vendor/bin/phpunit` green **and** the CI matrix green (`.github/workflows/tests.yml` runs the suite on PHP 8.0–8.4 with `gmp` + `sodium` enabled). The 8.0 job is the important one: it catches PHP 8.1+ syntax, reflection-visibility differences, and anything else that only breaks on the declared minimum. Never use `--no-verify`. This is a Composer library — there is no phar or build artifact, so no packaging step.
2. Commit, push `main`, then create an incremental tag `vX.Y.Z` (patch = bug fix, minor = feature, major = breaking change).
3. `gh release create vX.Y.Z` with a summary of what changed.

Security issues are handled through [`SECURITY.md`](SECURITY.md), not the public issue tracker.
