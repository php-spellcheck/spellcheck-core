# PHP Spellcheck Core — spell checker for PHP code and translation files

<p align="center">
  <img src="https://raw.githubusercontent.com/php-spellcheck/spellcheck-core/main/.github/assets/social-preview.png" alt="PHP Spellcheck Core — spell check PHP code and translation catalogues" width="880">
</p>

[![CI](https://github.com/php-spellcheck/spellcheck-core/actions/workflows/ci.yaml/badge.svg)](https://github.com/php-spellcheck/spellcheck-core/actions/workflows/ci.yaml)
[![Latest version](https://img.shields.io/packagist/v/php-spellcheck/spellcheck-core.svg)](https://packagist.org/packages/php-spellcheck/spellcheck-core)
[![Downloads](https://img.shields.io/packagist/dt/php-spellcheck/spellcheck-core.svg)](https://packagist.org/packages/php-spellcheck/spellcheck-core)
[![PHP version](https://img.shields.io/packagist/dependency-v/php-spellcheck/spellcheck-core/php.svg)](https://packagist.org/packages/php-spellcheck/spellcheck-core)
[![License](https://img.shields.io/packagist/l/php-spellcheck/spellcheck-core.svg)](LICENSE)

**Framework-agnostic PHP spell checking engine for source code and translation
catalogues.** It finds typos in PHP identifiers, docblocks, comments and in
Symfony translation catalogues, with Hunspell, Aspell, pspell or a pure PHP
backend, character-accurate line and column positions, ICU MessageFormat
support, a baseline for legacy projects and PSR-6 caching.

Use it to fail CI on typos, to lint a translation catalogue in the language it
is actually written in, or to build your own spell checking tool on top of the
pipeline.

For the Symfony integration (console commands, bundle configuration,
`Translator` support) use
[`php-spellcheck/spellcheck-symfony-bundle`](https://github.com/php-spellcheck/spellcheck-symfony-bundle).

## Features

- **Spell check PHP source code** — class, method, property, variable,
  constant and function names are split on camelCase, snake_case,
  SCREAMING_SNAKE and acronyms before being checked.
- **Spell check translation catalogues per locale** — each message is checked
  in the language of its own catalogue, so `messages.it.yaml` is checked in
  Italian and `messages.de.yaml` in German. Keys are resolved back to file and
  line in YAML, XLIFF and PHP array files.
- **ICU MessageFormat aware** — a built-in recursive descent parser walks
  `plural`, `select` and `selectordinal` branches. No `ext-intl` required.
- **Placeholders are never reported** — `%count%`, `{{ var }}`, `{name}`,
  `:attribute`, `sprintf` directives, HTML tags, Markdown syntax, URLs and
  docblock tags are stripped before the words reach the speller.
- **Character-accurate positions** — every reported misspelling points at the
  line and column in the *original* file, not in the cleaned-up text.
- **Five backends** — Hunspell and Aspell over the Ispell pipe protocol,
  `ext-pspell`, a dependency-free pure PHP word list, or an automatic chain.
- **Baseline for legacy projects** — suppress the existing typos with a
  line-shift resistant fingerprint and fail only on the new ones.
- **Seven report formats** — table, JSON, GitHub Actions annotations,
  Checkstyle, JUnit, GitLab Code Quality and CSV.
- **Custom and built-in dictionaries** — technical, PHP and Symfony word lists
  ship with the package; add your own per project or per locale.
- **Flat memory usage** — the whole pipeline is lazy, so a repository with
  thousands of files costs the same as one file.

## Requirements

- PHP ≥ 8.1 with `ext-mbstring` (tested up to PHP 8.5)
- `nikic/php-parser` 4 or 5
- No mandatory `ext-intl` (the ICU parser is built in)
- No mandatory system binary (the pure PHP `wordlist` backend always works)

## Table of contents

- [Why another spell checker](#why)
- [Related packages](#related-packages)
- [Install](#install)
- [Quick start](#quick-start)
- [Architecture](#architecture)
- [Extending](#extending)
- [Tests](#tests)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)
- [License](#license)

## Why

`cspell` does not know that `messages.it.yaml` must be checked in Italian, that
`%count%` is a placeholder or that `{n, plural, one {...}}` has textual
branches. `tigitz/php-spellchecker` is an excellent backend abstraction but
knows nothing about the domain. This library is the missing middle layer: it
turns catalogues and PHP identifiers into checkable text, accurately, and hands
the words to whichever backend you have.

| | php-spellcheck/spellcheck-core | cspell | tigitz/php-spellchecker |
|---|---|---|---|
| Per-locale translation catalogues | yes | no | no |
| ICU MessageFormat branches | yes | no | no |
| Symfony/Twig/`sprintf` placeholder awareness | yes | partial | no |
| PHP identifier splitting from the AST | yes | no | no |
| Positions mapped back to the original text | yes | n/a | no |
| Baseline for legacy projects | yes | no | no |
| Runs without Node.js | yes | no | yes |

## Related packages

| Package | Purpose |
|---|---|
| [`php-spellcheck/spellcheck-core`](https://github.com/php-spellcheck/spellcheck-core) | This package: the framework-agnostic engine. |
| [`php-spellcheck/spellcheck-symfony-bundle`](https://github.com/php-spellcheck/spellcheck-symfony-bundle) | Symfony bundle: console commands, configuration, `Translator` integration. |

## Install

```bash
composer require --dev php-spellcheck/spellcheck-core
```

## Quick start

```php
use PHPSpellcheck\Core\Checker\MisspellingFactory;
use PHPSpellcheck\Core\Checker\RunConfiguration;
use PHPSpellcheck\Core\Checker\RunStatisticsCollector;
use PHPSpellcheck\Core\Checker\SpellcheckRunner;
use PHPSpellcheck\Core\Diagnostics\DiagnosticCollector;
use PHPSpellcheck\Core\Dictionary\BuiltinDictionaries;
use PHPSpellcheck\Core\Dictionary\DictionaryLoader;
use PHPSpellcheck\Core\Dictionary\LocaleDictionaryMap;
use PHPSpellcheck\Core\Filter\DeduplicationFilter;
use PHPSpellcheck\Core\Filter\DictionaryFilter;
use PHPSpellcheck\Core\Filter\FilterChain;
use PHPSpellcheck\Core\Php\IdentifierKind;
use PHPSpellcheck\Core\Processor\HtmlProcessor;
use PHPSpellcheck\Core\Processor\PlaceholderProcessor;
use PHPSpellcheck\Core\Processor\ProcessorChain;
use PHPSpellcheck\Core\Report\BufferedWriter;
use PHPSpellcheck\Core\Report\TableReporter;
use PHPSpellcheck\Core\Source\PhpFileSource;
use PHPSpellcheck\Core\Speller\HunspellSpeller;
use PHPSpellcheck\Core\Tokenizer\IdentifierSplitter;
use PHPSpellcheck\Core\Tokenizer\ProseTokenizer;
use PHPSpellcheck\Core\Tokenizer\TokenizerRegistry;

$dictionary = (new DictionaryLoader())->loadAll(
    BuiltinDictionaries::paths(['technical', 'php', 'symfony']),
);

$diagnostics = new DiagnosticCollector();
$statistics = new RunStatisticsCollector();

$runner = new SpellcheckRunner(
    new ProcessorChain([new PlaceholderProcessor(), new HtmlProcessor()]),
    new TokenizerRegistry([new IdentifierSplitter(4), new ProseTokenizer(4)]),
    new HunspellSpeller(),
    new FilterChain([new DictionaryFilter($dictionary), new DeduplicationFilter()]),
    new MisspellingFactory(),
    $diagnostics,
    $statistics,
);

$source = new PhpFileSource(
    paths: [__DIR__.'/src'],
    exclude: ['vendor'],
    kinds: IdentifierKind::DEFAULTS,
    language: 'en_US',
    projectDir: __DIR__,
    diagnostics: $diagnostics,
    statistics: $statistics,
);

$result = $runner->run([$source], new RunConfiguration(new LocaleDictionaryMap()));

$writer = new BufferedWriter();
(new TableReporter())->report($result, $writer);

echo $writer->getBuffer();
```

## Architecture

```
Source ──► ProcessorChain ──► Tokenizer ──► Speller ──► FilterChain ──► RunResult
                                              ▲
                                     CachingSpeller (PSR-6)
```

| Layer | Contract | Built-in implementations |
|---|---|---|
| Source | `SourceInterface` | `PhpFileSource`, `ArrayFragmentSource`, `ChainSource` |
| Processor | `TextProcessorInterface` | placeholders, sprintf, HTML, markdown, URLs, ICU, legacy plurals, docblocks, apostrophes |
| Tokenizer | `TokenizerInterface` | `IdentifierSplitter`, `ProseTokenizer` |
| Speller | `SpellerInterface` | `HunspellSpeller`, `AspellSpeller`, `PspellSpeller`, `WordListSpeller`, `ChainSpeller`, `CachingSpeller`, `NullSpeller` |
| Filter | `MisspellingFilterInterface` | dictionary, pattern, deduplication, baseline |
| Reporter | `ReporterInterface` | table, json, github, checkstyle, junit, gitlab, csv |

Everything is a generator: memory stays flat regardless of how many files or
messages are processed.

### Accurate positions

Every processor that removes text records the correspondence in an `OffsetMap`,
and maps compose associatively. That is what makes the reported column point at
the position in the *original* message:

```php
// 'Ciao %name%, hai %count% messagi' -> the column of "messagi" is 26,
// not the offset it would have in the cleaned-up string.
```

### Ispell pipe protocol

`PipeSpeller` keeps one process alive per language for the whole run. Words are
sent one per line prefixed with `^`, and the terminating blank line is the
synchronisation point. Terse mode (`!`) suppresses the `*` lines and cuts the
I/O by roughly 90%; if your binary does not emit the blank line in terse mode,
construct the backend with `terseMode: false`.

### Baseline

The fingerprint is `sha256(schema | contextSeed | lowercase word | type)`
truncated to 16 hex characters. It deliberately excludes line and column, so
moving code around does not invalidate the baseline. Renaming a file does, and
that is documented behaviour.

## Extending

Implement the relevant interface and pass it to the chain. In a Symfony
application the bundle autoconfigures the six extension points; outside of it,
compose the objects yourself as in the quick start above.

## Tests

```bash
composer install
vendor/bin/phpunit                      # unit tests, no binaries needed
vendor/bin/phpunit --group integration  # requires hunspell and aspell
php tests/smoke.php                     # dependency free sanity check
```

`tests/smoke.php` runs a subset of the suite with a hand written autoloader and
no composer at all: useful to verify a checkout in a bare container.

## Contributing

Bug reports, feature requests and pull requests are welcome. Read
[CONTRIBUTING.md](CONTRIBUTING.md) first: the offset and determinism rules are
not negotiable.

## Security

Report vulnerabilities as described in [SECURITY.md](SECURITY.md). Do not open
a public issue for them.

## License

Released under the [MIT License](LICENSE).
