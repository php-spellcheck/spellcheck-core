<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Icu;

use Acme\Spellcheck\Exception\IcuSyntaxException;

/**
 * Recursive descent parser for ICU MessageFormat.
 *
 * Only the textual branches are returned; argument names, types, styles,
 * selectors and the "#" placeholder are dropped. Offsets are expressed in
 * characters of the original message.
 *
 * Does not require ext-intl.
 */
final class IcuMessageParser
{
    private const BRANCHING_TYPES = ['plural', 'select', 'selectordinal'];
    private const QUOTABLE = ['{', '}', '#', '|'];

    /** @var list<string> */
    private array $chars = [];

    private int $length = 0;

    private int $pos = 0;

    /** @var list<IcuTextSpan> */
    private array $spans = [];

    /**
     * Cheap heuristic used by IcuMessageProcessor in "auto" mode.
     */
    public function looksLikeIcu(string $message): bool
    {
        return 1 === preg_match(
            '/\{\s*[\p{L}\p{N}_]+\s*,\s*(plural|select|selectordinal|number|date|time|ordinal|duration|spellout)\b/u',
            $message,
        );
    }

    /**
     * @return list<IcuTextSpan>
     *
     * @throws IcuSyntaxException
     */
    public function parse(string $message): array
    {
        $this->chars = mb_str_split($message);
        $this->length = \count($this->chars);
        $this->pos = 0;
        $this->spans = [];

        $this->parseMessage(false, false);

        if ($this->pos < $this->length) {
            throw new IcuSyntaxException(sprintf('Unexpected "}" at offset %d.', $this->pos));
        }

        return $this->spans;
    }

    /**
     * Parses a message body.
     *
     * Contract: when $nested is true this method returns with $pos pointing AT
     * the closing brace, which is consumed by the caller (parseBranches).
     */
    private function parseMessage(bool $nested, bool $inPlural): void
    {
        $buffer = '';
        $bufferStart = $this->pos;
        $bufferOpen = false;

        while ($this->pos < $this->length) {
            $char = $this->chars[$this->pos];

            if ("'" === $char) {
                $start = $this->pos;
                $literal = $this->consumeQuote();

                if ('' !== $literal) {
                    if (!$bufferOpen) {
                        $bufferStart = $start;
                        $bufferOpen = true;
                    }
                    $buffer .= $literal;
                }

                continue;
            }

            if ('{' === $char) {
                $this->flush($buffer, $bufferStart);
                $buffer = '';
                $bufferOpen = false;
                $this->parseArgument();

                continue;
            }

            if ('}' === $char) {
                if (!$nested) {
                    throw new IcuSyntaxException(sprintf('Unexpected "}" at offset %d.', $this->pos));
                }

                $this->flush($buffer, $bufferStart);

                return;
            }

            if ('#' === $char && $inPlural) {
                $this->flush($buffer, $bufferStart);
                $buffer = '';
                $bufferOpen = false;
                ++$this->pos;

                continue;
            }

            if (!$bufferOpen) {
                $bufferStart = $this->pos;
                $bufferOpen = true;
            }

            $buffer .= $char;
            ++$this->pos;
        }

        if ($nested) {
            throw new IcuSyntaxException('Unterminated argument: missing "}".');
        }

        $this->flush($buffer, $bufferStart);
    }

    private function flush(string $buffer, int $offset): void
    {
        if ('' !== trim($buffer)) {
            $this->spans[] = new IcuTextSpan($buffer, $offset);
        }
    }

    /**
     * ICU 4.8+ quoting rules:
     *   ''        -> literal apostrophe
     *   '{ ... '  -> quoted section, whose content is TEXT
     *   ' + other -> plain apostrophe
     */
    private function consumeQuote(): string
    {
        ++$this->pos;

        if ($this->pos < $this->length && "'" === $this->chars[$this->pos]) {
            ++$this->pos;

            return "'";
        }

        if ($this->pos >= $this->length || !\in_array($this->chars[$this->pos], self::QUOTABLE, true)) {
            return "'";
        }

        $literal = '';

        while ($this->pos < $this->length) {
            $char = $this->chars[$this->pos];

            if ("'" === $char) {
                if ($this->pos + 1 < $this->length && "'" === $this->chars[$this->pos + 1]) {
                    $literal .= "'";
                    $this->pos += 2;

                    continue;
                }

                ++$this->pos;

                break;
            }

            $literal .= $char;
            ++$this->pos;
        }

        return $literal;
    }

    private function parseArgument(): void
    {
        ++$this->pos; // '{'
        $this->skipWhitespace();
        $this->readUntil([',', '}']); // argument name, discarded
        $this->skipWhitespace();

        if ($this->pos >= $this->length) {
            throw new IcuSyntaxException('Unterminated argument.');
        }

        if ('}' === $this->chars[$this->pos]) {
            ++$this->pos;

            return;
        }

        ++$this->pos; // ','
        $this->skipWhitespace();
        $type = strtolower(trim($this->readUntil([',', '}'])));
        $this->skipWhitespace();

        if ($this->pos >= $this->length) {
            throw new IcuSyntaxException('Unterminated argument type.');
        }

        if ('}' === $this->chars[$this->pos]) {
            ++$this->pos;

            return;
        }

        ++$this->pos; // ','

        if (\in_array($type, self::BRANCHING_TYPES, true)) {
            $this->parseBranches('select' !== $type);

            return;
        }

        $this->skipOpaqueStyle();
    }

    private function parseBranches(bool $inPlural): void
    {
        while ($this->pos < $this->length) {
            $this->skipWhitespace();

            if ($this->pos >= $this->length) {
                break;
            }

            if ('}' === $this->chars[$this->pos]) {
                ++$this->pos;

                return;
            }

            $selector = trim($this->readUntil(['{', '}']));

            if (str_starts_with($selector, 'offset:') && $this->pos < $this->length && '{' !== $this->chars[$this->pos]) {
                continue;
            }

            if ($this->pos >= $this->length || '{' !== $this->chars[$this->pos]) {
                throw new IcuSyntaxException(sprintf('Expected "{" after selector "%s".', $selector));
            }

            ++$this->pos; // '{'
            $this->parseMessage(true, $inPlural);
            ++$this->pos; // the '}' parseMessage stopped at
        }

        throw new IcuSyntaxException('Unterminated plural/select argument.');
    }

    private function skipOpaqueStyle(): void
    {
        $depth = 1;

        while ($this->pos < $this->length) {
            $char = $this->chars[$this->pos];

            if ("'" === $char) {
                $this->consumeQuote();

                continue;
            }

            if ('{' === $char) {
                ++$depth;
            }

            if ('}' === $char) {
                --$depth;

                if (0 === $depth) {
                    ++$this->pos;

                    return;
                }
            }

            ++$this->pos;
        }

        throw new IcuSyntaxException('Unterminated argument style.');
    }

    /**
     * @param list<string> $stops
     */
    private function readUntil(array $stops): string
    {
        $value = '';

        while ($this->pos < $this->length && !\in_array($this->chars[$this->pos], $stops, true)) {
            $value .= $this->chars[$this->pos];
            ++$this->pos;
        }

        return $value;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && 1 === preg_match('/\s/u', $this->chars[$this->pos])) {
            ++$this->pos;
        }
    }
}
