# php-spellcheck/spellcheck

Spell checking engine for PHP code and translation catalogues.

This is the framework agnostic core. For the Symfony integration (commands,
configuration, Translator support) use
[`php-spellcheck/spellcheck`](https://github.com/php-spellcheck/spellcheck-bundle).

- PHP ≥ 8.1
- No mandatory `ext-intl` (the ICU parser is built in)
- Works without any system binary, through the pure PHP `wordlist` backend
- `nikic/php-parser` 4 or 5

## Why

`cspell` does not know that `messages.it.yaml` must be checked in Italian, that
`%count%` is a placeholder or that `{n, plural, one {...}}` has textual
branches. `tigitz/php-spellchecker` is an excellent backend abstraction but
knows nothing about the domain. This library is the missing middle layer: it
turns catalogues and PHP identifiers into checkable text, accurately, and hands
the words to whichever backend you have.

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

## License

MIT.
