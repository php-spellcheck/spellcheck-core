# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-07

### Added

- `SpellcheckRunner`: generator based pipeline, batching per language, explicit
  integer word ids to correlate results with fragments.
- `OffsetMap` / `OffsetMapBuilder`: character accurate mapping from processed
  text back to the original, with associative composition.
- `IcuMessageParser`: recursive descent ICU MessageFormat parser without
  ext-intl, covering plural, select, selectordinal, opaque styles and ICU 4.8+
  quoting.
- Tokenizers: `IdentifierSplitter` (camelCase, snake_case, SCREAMING_SNAKE,
  acronyms, glued digits) and `ProseTokenizer` (apostrophes, hyphens, accents).
- Processors: placeholders, sprintf, HTML, markdown, URLs, legacy pipe plurals,
  ICU expansion, docblock tags, apostrophe normalisation.
- Backends: hunspell and aspell over the Ispell pipe protocol, ext-pspell,
  pure PHP word list, automatic chain, PSR-6 caching decorator.
- Baseline with a line-shift resistant fingerprint, prune and merge.
- Reporters: table, json, github, checkstyle, junit, gitlab, csv.
- Inline suppression markers for PHP files.
- Builtin dictionaries: technical, php, symfony.

### Notes

- `Fingerprint::SCHEMA_VERSION` is `v1` and `BaselineStorage::SCHEMA` is `1`.
  Changing either requires a major release and a baseline regeneration.

[Unreleased]: https://github.com/php-spellcheck/spellcheck-core/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/php-spellcheck/spellcheck-core/releases/tag/v1.0.0
