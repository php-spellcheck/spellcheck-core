## What this changes

<!-- One or two sentences. Link the issue it closes. -->

Closes #

## Why

<!-- The behaviour that was wrong or missing. -->

## Checklist

- [ ] `make cs-fix` is clean
- [ ] `make stan` is clean
- [ ] `make test-all` passes
- [ ] Works on both `nikic/php-parser` 4 and 5
- [ ] Offsets stay character-accurate, and a test asserts the translated offset
- [ ] Two identical runs still produce byte-identical output, baseline included
- [ ] The pipeline stays lazy (no fragment accumulation)
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] No `Fingerprint::SCHEMA_VERSION` or `BaselineStorage::SCHEMA` change (or the changelog says to regenerate)
