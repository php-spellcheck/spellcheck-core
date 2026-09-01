<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

/**
 * Describes where a fragment comes from. Used by reporters and by the
 * fingerprint, which is why it must stay stable across runs.
 *
 * @psalm-immutable
 */
final class FragmentContext
{
    public const SOURCE_TRANSLATION = 'translation';
    public const SOURCE_PHP = 'php';

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $sourceType,
        public readonly array $attributes = [],
    ) {
    }

    public static function translation(string $locale, string $domain, string $key): self
    {
        return new self(self::SOURCE_TRANSLATION, [
            'locale' => $locale,
            'domain' => $domain,
            'key' => $key,
        ]);
    }

    public static function php(string $kind, string $identifier, string $path = ''): self
    {
        return new self(self::SOURCE_PHP, [
            'kind' => $kind,
            'identifier' => $identifier,
            'path' => $path,
        ]);
    }

    public function with(string $name, string $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($this->sourceType, $attributes);
    }

    public function get(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Stable seed for Fingerprint::of(). Deliberately excludes line and column
     * so that moving code around does not invalidate the baseline.
     */
    public function fingerprintSeed(): string
    {
        if (self::SOURCE_TRANSLATION === $this->sourceType) {
            return implode('|', [
                'translation',
                $this->get('locale') ?? '',
                $this->get('domain') ?? '',
                $this->get('key') ?? '',
            ]);
        }

        if (self::SOURCE_PHP === $this->sourceType) {
            return implode('|', [
                'php',
                $this->get('path') ?? '',
                $this->get('kind') ?? '',
                $this->get('identifier') ?? '',
            ]);
        }

        $attributes = $this->attributes;
        ksort($attributes);

        $parts = [$this->sourceType];
        foreach ($attributes as $name => $value) {
            $parts[] = $name.'='.$value;
        }

        return implode('|', $parts);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['source' => $this->sourceType] + $this->attributes;
    }
}
