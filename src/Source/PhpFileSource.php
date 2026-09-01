<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Source;

use Acme\Spellcheck\Checker\RunStatisticsCollector;
use Acme\Spellcheck\Diagnostics\DiagnosticCollector;
use Acme\Spellcheck\Model\DiagnosticCode;
use Acme\Spellcheck\Model\FragmentContext;
use Acme\Spellcheck\Model\Location;
use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Php\IdentifierCollectingVisitor;
use Acme\Spellcheck\Php\IdentifierKind;
use Acme\Spellcheck\Php\InlineSuppression;
use Acme\Spellcheck\Php\ParserFactoryCompat;
use Acme\Spellcheck\Support\Utf8;
use PhpParser\Error as PhpParserError;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Emits one fragment per identifier, docblock or comment of every PHP file
 * under the configured paths.
 */
final class PhpFileSource implements SourceInterface
{
    private ?Parser $parser = null;

    /**
     * @param list<string>         $paths
     * @param list<string>         $exclude
     * @param list<IdentifierKind> $kinds
     */
    public function __construct(
        private readonly array $paths,
        private readonly array $exclude = [],
        private readonly array $kinds = IdentifierKind::DEFAULTS,
        private readonly string $language = 'en_US',
        private readonly string $maxFileSize = '2M',
        private readonly string $suppressionPrefix = '@spellcheck',
        private readonly ?string $projectDir = null,
        private readonly ?DiagnosticCollector $diagnostics = null,
        private readonly ?RunStatisticsCollector $statistics = null,
    ) {
    }

    public function getName(): string
    {
        return 'php';
    }

    /**
     * @param list<string> $paths
     */
    public function withPaths(array $paths): self
    {
        return new self(
            $paths,
            $this->exclude,
            $this->kinds,
            $this->language,
            $this->maxFileSize,
            $this->suppressionPrefix,
            $this->projectDir,
            $this->diagnostics,
            $this->statistics,
        );
    }

    public function fragments(): iterable
    {
        foreach ($this->createFinder() as $file) {
            yield from $this->processFile($file);
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function createFinder(): iterable
    {
        $existing = array_values(array_filter($this->paths, static fn (string $path): bool => is_dir($path) || is_file($path)));

        foreach ($this->paths as $path) {
            if (!is_dir($path) && !is_file($path)) {
                $this->diagnostics?->add(
                    DiagnosticCode::SKIPPED_FILE,
                    sprintf('The configured path "%s" does not exist.', $path),
                );
            }
        }

        if ([] === $existing) {
            return [];
        }

        $directories = array_values(array_filter($existing, 'is_dir'));
        $files = array_values(array_filter($existing, 'is_file'));

        $result = [];

        if ([] !== $directories) {
            $finder = (new Finder())
                ->files()
                ->in($directories)
                ->name('*.php')
                ->size('< '.$this->maxFileSize)
                ->sortByName();

            foreach ($this->exclude as $pattern) {
                $finder->notPath($pattern);
            }

            $result[] = $finder;
        }

        foreach ($files as $file) {
            $result[] = [new SplFileInfo($file, \dirname($file), basename($file))];
        }

        foreach ($result as $set) {
            yield from $set;
        }
    }

    /**
     * @return iterable<TextFragment>
     */
    private function processFile(SplFileInfo $file): iterable
    {
        $this->statistics?->file();

        $code = $file->getContents();
        $relativePath = $this->relativePath($file);

        if (!Utf8::isValidUtf8($code)) {
            $converted = @mb_convert_encoding($code, 'UTF-8', 'ISO-8859-1, Windows-1252');

            if (!\is_string($converted) || !Utf8::isValidUtf8($converted)) {
                $this->diagnostics?->add(
                    DiagnosticCode::ENCODING,
                    sprintf('The file "%s" is not valid UTF-8 and could not be converted.', $relativePath),
                    Location::file($relativePath),
                );

                return;
            }

            $code = $converted;
        }

        $suppression = InlineSuppression::scan($code, $this->suppressionPrefix);

        if ($suppression->coversWholeFile()) {
            return;
        }

        try {
            $ast = $this->getParser()->parse($code) ?? [];
        } catch (PhpParserError $e) {
            $this->diagnostics?->add(
                DiagnosticCode::PARSE_ERROR,
                sprintf('%s: %s', $relativePath, $e->getMessage()),
                Location::file($relativePath, $e->getStartLine() > 0 ? $e->getStartLine() : null),
            );

            return;
        }

        $visitor = new IdentifierCollectingVisitor($this->kinds);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        /** @var array<string, true> $seenComments */
        $seenComments = [];

        foreach ($visitor->getCollected() as $identifier) {
            if ($suppression->isLineIgnored($identifier->line)) {
                $this->statistics?->suppressedInline();

                continue;
            }

            if ($identifier->kind->isComment()) {
                $key = $identifier->line.':'.crc32($identifier->value);

                if (isset($seenComments[$key])) {
                    continue;
                }

                $seenComments[$key] = true;
            }

            yield new TextFragment(
                $identifier->value,
                $this->language,
                FragmentContext::php($identifier->kind->value, $identifier->value, $relativePath),
                Location::file($relativePath, $identifier->line),
                null,
                $identifier->kind->tokenizerMode(),
            );
        }
    }

    private function relativePath(SplFileInfo $file): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        if (null !== $this->projectDir && str_starts_with($path, $this->projectDir)) {
            return ltrim(substr($path, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
        }

        return $file->getRelativePathname() ?: $path;
    }

    private function getParser(): Parser
    {
        return $this->parser ??= ParserFactoryCompat::create();
    }
}
