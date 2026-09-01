<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Speller;

use Symfony\Component\Process\Process;

/**
 * aspell accepts a single language per process, so the base class restarts the
 * process whenever the language changes.
 */
final class AspellSpeller extends PipeSpeller
{
    public function getName(): string
    {
        return 'aspell';
    }

    protected function buildCommand(string $language): array
    {
        $command = [$this->binary, '-a', '--lang='.$language, '--encoding=utf-8'];

        if (null !== $this->personalDictionary) {
            $command[] = '--personal='.$this->personalDictionary;
        }

        return $command;
    }

    protected function detectLanguages(): array
    {
        $process = new Process([$this->binary, 'dicts']);
        $process->setTimeout(10.0);

        try {
            $process->run();
        } catch (\Throwable) {
            return [];
        }

        $languages = [];

        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
            $line = trim($line);

            if ('' !== $line) {
                $languages[$line] = true;
            }
        }

        return array_keys($languages);
    }
}
