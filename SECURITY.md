# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | yes |
| < 1.0 (`dev-main`) | best effort |

## Reporting a vulnerability

Do not open a public issue for a security problem.

Report it privately through
[GitHub Security Advisories](https://github.com/php-spellcheck/spellcheck-core/security/advisories/new),
or by email to raffaele.carelle@gmail.com.

Please include:

- the affected version and PHP version,
- a description of the problem and its impact,
- a minimal reproducer if you have one.

You will get an acknowledgement within 7 days and a status update at least
every 14 days until the issue is resolved. Fixes are released as a patch
version and credited in the advisory unless you ask otherwise.

## Scope notes

This library spawns external processes (`hunspell`, `aspell`) and reads
dictionary files and source files from disk. Reports about command construction,
path handling, cache poisoning through the PSR-6 layer, or untrusted dictionary
content are in scope. Reports about vulnerabilities in the spell checking
binaries themselves belong upstream.
