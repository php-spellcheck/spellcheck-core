<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Speller;

use Symfony\Component\Process\Process;

final class HunspellSpeller extends PipeSpeller
{
    /**
     * @param array<string, list<string>> $extraDictionaries locale => additional system dictionaries
     */
    public function __construct(
        string $binary = 'hunspell',
        private readonly array $extraDictionaries = [],
        float $readTimeout = 10.0,
        bool $terseMode = true,
        ?string $personalDictionary = null,
    ) {
        parent::__construct($binary, $readTimeout, $terseMode, $personalDictionary);
    }

    public function getName(): string
    {
        return 'hunspell';
    }

    protected function buildCommand(string $language): array
    {
        $dictionaries = array_merge([$language], $this->extraDictionaries[$language] ?? []);

        $command = [$this->binary, '-a', '-i', 'utf-8', '-d', implode(',', $dictionaries)];

        if (null !== $this->personalDictionary) {
            $command[] = '-p';
            $command[] = $this->personalDictionary;
        }

        return $command;
    }

    /**
     * hunspell -D lists the dictionaries on STDERR and then waits for input on
     * STDIN: the process must be given an empty input and a timeout, otherwise
     * it hangs forever.
     */
    protected function detectLanguages(): array
    {
        $process = new Process([$this->binary, '-D']);
        $process->setInput('');
        $process->setTimeout(10.0);

        try {
            $process->run();
        } catch (\Throwable) {
            return [];
        }

        $output = $process->getErrorOutput()."\n".$process->getOutput();
        $languages = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line || str_contains($line, ':') || str_starts_with($line, 'AVAILABLE')) {
                continue;
            }

            $basename = basename($line);

            if (1 === preg_match('/^([a-z]{2,3}(?:_[A-Za-z]{2,4})?)$/', $basename, $m)) {
                $languages[$m[1]] = true;
            }
        }

        return array_keys($languages);
    }
}
