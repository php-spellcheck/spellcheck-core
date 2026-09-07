<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Exception\SpellerProcessException;
use PHPSpellcheck\Core\Model\MisspellingType;
use PHPSpellcheck\Core\Model\Word;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Base class for the backends speaking the Ispell "-a" pipe protocol
 * (hunspell, aspell, ispell).
 *
 * One process per language, kept alive for the whole run. Every word is sent on
 * its own line prefixed with "^" so that a word starting with a control
 * character is not mistaken for a command. The backend answers with zero or
 * more result lines followed by an empty line, which is the only reliable
 * synchronisation point.
 */
abstract class PipeSpeller implements SpellerInterface
{
    private const READ_INTERVAL_US = 200;
    private const MAX_RESTARTS = 1;

    private ?Process $process = null;
    private ?InputStream $input = null;
    private ?string $currentLanguage = null;
    private string $buffer = '';
    private ?string $banner = null;
    private int $restarts = 0;

    /** @var list<string>|null */
    private ?array $languages = null;

    public function __construct(
        protected readonly string $binary,
        protected readonly float $readTimeout = 10.0,
        protected readonly bool $terseMode = true,
        protected readonly ?string $personalDictionary = null,
    ) {
    }

    /**
     * @return list<string>
     */
    abstract protected function buildCommand(string $language): array;

    /**
     * @return list<string>
     */
    abstract protected function detectLanguages(): array;

    protected function normalizeLanguage(string $language): string
    {
        return str_replace('-', '_', $language);
    }

    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        $normalized = $this->normalizeLanguage($language);
        $this->ensureProcess($normalized);

        foreach ($words as $word) {
            $lines = $this->exchange($word->value);
            $result = $this->parseLines($word, $lines, $language, $withSuggestions);

            if (null !== $result) {
                yield $result;
            }
        }
    }

    public function supportsLanguage(string $language): bool
    {
        return \in_array($this->normalizeLanguage($language), $this->getSupportedLanguages(), true);
    }

    public function getSupportedLanguages(): array
    {
        if (null === $this->languages) {
            $this->languages = $this->isAvailable() ? $this->detectLanguages() : [];
        }

        return $this->languages;
    }

    public function isAvailable(): bool
    {
        $probe = new Process([$this->binary, '--version']);
        $probe->setTimeout(5.0);

        try {
            $probe->run();
        } catch (\Throwable) {
            return false;
        }

        return '' !== trim($probe->getOutput().$probe->getErrorOutput());
    }

    public function describe(): string
    {
        $probe = new Process([$this->binary, '--version']);
        $probe->setTimeout(5.0);

        try {
            $probe->run();
        } catch (\Throwable $e) {
            return 'unavailable: '.$e->getMessage();
        }

        $output = trim($probe->getOutput().$probe->getErrorOutput());
        $firstLine = strtok($output, "\n");

        return false === $firstLine ? 'unknown version' : $firstLine;
    }

    public function stop(): void
    {
        try {
            $this->input?->close();
            $this->process?->wait();
        } catch (\Throwable) {
            $this->process?->stop(1.0);
        } finally {
            $this->process = null;
            $this->input = null;
            $this->currentLanguage = null;
            $this->buffer = '';
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    protected function getBanner(): ?string
    {
        return $this->banner;
    }

    private function ensureProcess(string $language): void
    {
        if (null !== $this->process && $this->process->isRunning() && $language === $this->currentLanguage) {
            return;
        }

        $this->stop();

        $command = $this->buildCommand($language);

        $input = new InputStream();
        $process = new Process($command);
        $process->setInput($input);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->start();

        $this->input = $input;
        $this->process = $process;
        $this->currentLanguage = $language;
        $this->buffer = '';
        $this->banner = $this->readSingleLine();

        if (null === $this->banner) {
            $error = $process->getErrorOutput();
            $this->stop();

            throw new SpellerProcessException(sprintf(
                'The "%s" backend did not emit its banner. Command: %s. Stderr: %s',
                $this->getName(),
                implode(' ', $command),
                '' !== $error ? trim($error) : '(empty)',
            ));
        }

        if ($this->terseMode) {
            $input->write("!\n");
        }
    }

    /**
     * @return list<string>
     */
    private function exchange(string $word): array
    {
        try {
            $this->write('^'.$word."\n");

            return $this->readUntilBlankLine();
        } catch (SpellerProcessException $e) {
            if ($this->restarts >= self::MAX_RESTARTS) {
                throw $e;
            }

            ++$this->restarts;
            $language = (string) $this->currentLanguage;
            $this->stop();
            $this->ensureProcess($language);
            $this->write('^'.$word."\n");

            return $this->readUntilBlankLine();
        }
    }

    private function write(string $payload): void
    {
        if (null === $this->input) {
            throw new SpellerProcessException('The speller process is not running.');
        }

        $this->input->write($payload);
    }

    /**
     * @return list<string>
     */
    private function readUntilBlankLine(): array
    {
        if (null === $this->process) {
            throw new SpellerProcessException('The speller process is not running.');
        }

        $deadline = microtime(true) + $this->readTimeout;

        while (true) {
            $this->buffer .= $this->process->getIncrementalOutput();

            $parts = explode("\n", $this->buffer);
            $complete = \count($parts) - 1;

            for ($i = 0; $i < $complete; ++$i) {
                if ('' === rtrim($parts[$i], "\r")) {
                    $lines = \array_slice($parts, 0, $i);
                    $this->buffer = implode("\n", \array_slice($parts, $i + 1));

                    return array_values(array_map(
                        static fn (string $line): string => rtrim($line, "\r"),
                        $lines,
                    ));
                }
            }

            if (!$this->process->isRunning()) {
                $this->buffer .= $this->process->getIncrementalOutput();

                throw new SpellerProcessException(sprintf(
                    'The "%s" process died (exit code %s). Stderr: %s',
                    $this->getName(),
                    var_export($this->process->getExitCode(), true),
                    trim($this->process->getErrorOutput()),
                ));
            }

            if (microtime(true) > $deadline) {
                throw new SpellerProcessException(sprintf(
                    'Timed out after %.1fs waiting for a response from "%s". If the binary does not emit the '
                    .'terminating blank line in terse mode, disable it (backend_options.terse_mode: false).',
                    $this->readTimeout,
                    $this->getName(),
                ));
            }

            usleep(self::READ_INTERVAL_US);
        }
    }

    private function readSingleLine(): ?string
    {
        if (null === $this->process) {
            return null;
        }

        $deadline = microtime(true) + $this->readTimeout;

        while (true) {
            $this->buffer .= $this->process->getIncrementalOutput();
            $newline = strpos($this->buffer, "\n");

            if (false !== $newline) {
                $line = rtrim(substr($this->buffer, 0, $newline), "\r");
                $this->buffer = substr($this->buffer, $newline + 1);

                return $line;
            }

            if (!$this->process->isRunning() || microtime(true) > $deadline) {
                return null;
            }

            usleep(self::READ_INTERVAL_US);
        }
    }

    /**
     * @param list<string> $lines
     */
    private function parseLines(Word $word, array $lines, string $language, bool $withSuggestions): ?SpellerResult
    {
        foreach ($lines as $line) {
            if ('' === $line) {
                continue;
            }

            $marker = $line[0];

            if ('*' === $marker || '+' === $marker || '-' === $marker) {
                return null;
            }

            if ('#' === $marker) {
                return new SpellerResult($word, [], MisspellingType::SPELLING, $language);
            }

            if ('&' === $marker || '?' === $marker) {
                $suggestions = [];

                if ($withSuggestions) {
                    $colon = strpos($line, ':');

                    if (false !== $colon) {
                        $suggestions = array_values(array_filter(array_map(
                            'trim',
                            explode(',', substr($line, $colon + 1)),
                        )));
                    }
                }

                return new SpellerResult($word, $suggestions, MisspellingType::SPELLING, $language);
            }
        }

        // Terse mode: no meaningful line means the word is correct.
        return null;
    }
}
