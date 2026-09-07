<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

use PHPSpellcheck\Core\Model\OffsetMapBuilder;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;

/**
 * Turns a docblock or comment into plain prose: strips comment markers, inline
 * tags, annotations and the machine readable part of the standard tags.
 */
final class DocBlockProcessor implements TextProcessorInterface
{
    /** Tags whose description is worth checking, once type and variable are dropped. */
    private const TAGS_WITH_DESCRIPTION = ['param', 'return', 'var', 'throws', 'property', 'property-read', 'property-write', 'method', 'deprecated', 'todo', 'note', 'internal'];

    public function __construct(
        private readonly bool $strictTags = true,
    ) {
    }

    public static function getDefaultPriority(): int
    {
        return 200;
    }

    public function supports(TextFragment $fragment): bool
    {
        return TokenizerMode::DOCBLOCK === $fragment->tokenizer && !$fragment->isBlank();
    }

    public function process(TextFragment $fragment): TextFragment|array
    {
        $builder = new OffsetMapBuilder();
        $offset = 0;

        foreach (preg_split('/(?<=\n)/', $fragment->text) ?: [] as $line) {
            $length = mb_strlen($line);
            [$kept, $keptOffset] = $this->processLine($line, $offset);

            if ('' !== $kept) {
                $builder->keep($kept, $keptOffset);
            }

            $builder->emit("\n");
            $offset += $length;
        }

        return $fragment->withText($builder->getText(), $builder->getMap());
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function processLine(string $line, int $lineOffset): array
    {
        $content = rtrim($line, "\r\n");
        $shift = 0;

        // Strip comment markers.
        if (1 === preg_match('#^(\s*(?:/\*\*|/\*|\*/|\*|//|\#))#', $content, $m)) {
            $shift = mb_strlen($m[1]);
            $content = mb_substr($content, $shift);
        }

        $content = preg_replace('#\*/\s*$#', '', $content) ?? $content;

        $trimmed = ltrim($content);
        $shift += mb_strlen($content) - mb_strlen($trimmed);
        $content = $trimmed;

        if ('' === $content) {
            return ['', $lineOffset];
        }

        // Inline tags carry no prose.
        $content = preg_replace('/\{@[^}]*\}/', ' ', $content) ?? $content;

        if ('@' === ($content[0] ?? '')) {
            if (!$this->strictTags) {
                return [$content, $lineOffset + $shift];
            }

            if (1 !== preg_match('/^@([a-zA-Z\-]+)(.*)$/s', $content, $m)) {
                return ['', $lineOffset];
            }

            $tag = strtolower($m[1]);

            if (!\in_array($tag, self::TAGS_WITH_DESCRIPTION, true)) {
                // Annotations (@ORM\Column, @Assert\NotBlank) and machine tags.
                return ['', $lineOffset];
            }

            $rest = $m[2];
            $consumed = mb_strlen($content) - mb_strlen($rest);

            // Drop the type expression and the variable name.
            if (1 === preg_match('/^(\s*(?:[^\s$]+\s+)?(?:\$[\w]+\s*)?)/u', $rest, $tm)) {
                $rest = mb_substr($rest, mb_strlen($tm[1]));
                $consumed += mb_strlen($tm[1]);
            }

            return [$rest, $lineOffset + $shift + $consumed];
        }

        return [$content, $lineOffset + $shift];
    }
}
