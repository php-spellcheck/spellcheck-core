<?php

declare(strict_types=1);

/**
 * Fake Ispell-protocol backend, used by PipeSpellerTest so that the unit tests
 * do not require hunspell to be installed.
 *
 * Speaks exactly the subset of the protocol the production code relies on:
 * banner, control lines, "&" and "#" answers, terminating blank line.
 */
$misspelled = [
    'mispell' => ['misspell', 'misspelt'],
    'messagi' => ['messaggi'],
    'nosuggestion' => [],
];

fwrite(\STDOUT, "@(#) International Ispell Version 3.2.06 (but really FakeSpell 1.0)\n");

$die = getenv('FAKE_SPELLER_DIE_AFTER');
$handled = 0;

while (false !== ($line = fgets(\STDIN))) {
    $line = rtrim($line, "\r\n");

    if ('' === $line) {
        continue;
    }

    // Control lines produce no answer at all.
    if (in_array($line[0], ['!', '%', '*', '@', '#', '+', '-', '~'], true)) {
        continue;
    }

    $word = ltrim($line, '^');
    ++$handled;

    if (false !== $die && $handled > (int) $die) {
        exit(9);
    }

    if (array_key_exists($word, $misspelled)) {
        $suggestions = $misspelled[$word];

        if ([] === $suggestions) {
            fwrite(\STDOUT, sprintf("# %s 0\n", $word));
        } else {
            fwrite(\STDOUT, sprintf("& %s %d 0: %s\n", $word, count($suggestions), implode(', ', $suggestions)));
        }
    }

    // Terse mode still emits the terminating blank line.
    fwrite(\STDOUT, "\n");
}
