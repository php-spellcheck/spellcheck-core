# Contributing

## Getting started

```bash
git clone https://github.com/php-spellcheck/spellcheck-core.git && cd spellcheck-core
make build install
make test
```

Everything runs in the Docker image, which ships hunspell, aspell, ext-pspell
and the it/en/de/fr dictionaries. Working outside Docker is possible, but the
integration tests will skip themselves if the binaries are missing.

## Before opening a pull request

```bash
make cs-fix
make stan
make test-all
```

## Rules that are not negotiable

- **php-parser 4 and 5 must both work.** Version specific code goes through
  `ParserFactoryCompat` and `NodeClasses`.
- **Offsets are characters, never bytes.** `PREG_OFFSET_CAPTURE` returns bytes:
  convert with `Utf8::byteToCharOffset()`. A processor that loses offsets is a
  bug, even if the words are right.
- **No exception for an expected condition.** An unparsable file, a missing
  dictionary or a malformed ICU message produces a `Diagnostic`. Exceptions are
  for programming errors and unrecoverable backend failures.
- **Determinism.** Two identical runs must produce byte identical output, the
  baseline included.
- **The pipeline stays lazy.** Sources and tokenizers are generators; nothing
  accumulates every fragment in memory.

## Adding a processor

1. Implement `TextProcessorInterface` in `src/Processor`.
2. Pick a priority and document why it sits where it sits relative to its
   neighbours; the ICU processor must see braces before the placeholder one eats
   them.
3. Use `OffsetMapBuilder`: `keep()` what survives, `emit(' ')` in place of what
   is removed, so words never get glued together.
4. Write a test asserting **both** the resulting text and the translated offset.
5. Outside Symfony, pass it to the `ProcessorChain`. In the bundle
   ([`php-spellcheck/spellcheck-symfony-bundle`](https://github.com/php-spellcheck/spellcheck-symfony-bundle))
   it is autoconfigured through the `php_spellcheck.processor` tag.

Careful with regex delimiters: PHP looks for the closing delimiter before it
knows about the `x` modifier, so a `/` or a `#` inside an extended-mode comment
silently truncates the pattern. Use `~` and put the explanation in the docblock.

## Adding a backend

1. Implement `SpellerInterface`, or extend `PipeSpeller` if the tool speaks the
   Ispell `-a` protocol.
2. Add it to `ChainSpeller`, and to the `backend` enum in the bundle
   `Configuration` if it should be selectable from the bundle.
3. Write a unit test against a fake process (see
   `tests/Fixtures/fake-speller.php`) and an integration test marked
   `@group integration` for the real binary.
4. A backend that sends text over the network must not be selectable by `auto`
   and must emit a diagnostic on every run.

## Baseline and fingerprint

`Fingerprint::SCHEMA_VERSION` and `BaselineStorage::SCHEMA` may only change in a
major release, and the changelog must tell users to regenerate. Silently
changing either turns every suppressed issue into a false negative.

## Releasing

1. Bump `PHPSpellcheck\Core\Version` and move the `[Unreleased]` changelog
   entries under the new version.
2. Tag and push:

```bash
make release VERSION=1.0.0
```

Packagist watches the repository through a webhook and picks up the new tag,
so a version only becomes installable once it is tagged.
