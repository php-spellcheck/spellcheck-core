<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Processor;

use Acme\Spellcheck\Diagnostics\DiagnosticCollector;
use Acme\Spellcheck\Exception\IcuSyntaxException;
use Acme\Spellcheck\Icu\IcuMessageParser;
use Acme\Spellcheck\Model\DiagnosticCode;
use Acme\Spellcheck\Model\TextFragment;

/**
 * Expands an ICU message into one fragment per textual branch.
 */
final class IcuMessageProcessor implements TextProcessorInterface
{
    public const MODE_AUTO = 'auto';
    public const MODE_ALWAYS = 'always';
    public const MODE_DOMAIN_SUFFIX = 'domain_suffix';

    public function __construct(
        private readonly IcuMessageParser $parser = new IcuMessageParser(),
        private readonly string $mode = self::MODE_AUTO,
        private readonly ?DiagnosticCollector $diagnostics = null,
    ) {
    }

    public static function getDefaultPriority(): int
    {
        return 800;
    }

    public function supports(TextFragment $fragment): bool
    {
        if ($fragment->isBlank() || !str_contains($fragment->text, '{')) {
            return false;
        }

        return match ($this->mode) {
            self::MODE_ALWAYS => true,
            self::MODE_DOMAIN_SUFFIX => '1' === $fragment->context->get('icu'),
            default => '1' === $fragment->context->get('icu') || $this->parser->looksLikeIcu($fragment->text),
        };
    }

    public function process(TextFragment $fragment): TextFragment|array
    {
        try {
            $spans = $this->parser->parse($fragment->text);
        } catch (IcuSyntaxException $e) {
            $this->diagnostics?->add(
                DiagnosticCode::ICU_SYNTAX,
                sprintf('%s (%s)', $e->getMessage(), $fragment->context->fingerprintSeed()),
                $fragment->location,
            );

            return [];
        }

        $offsets = $fragment->getOffsets();
        $fragments = [];

        foreach ($spans as $span) {
            if ('' === trim($span->text)) {
                continue;
            }

            $fragments[] = $fragment->derive($span->text, $offsets->translate($span->offset));
        }

        return $fragments;
    }
}
