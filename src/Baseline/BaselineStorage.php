<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Baseline;

use PHPSpellcheck\Core\Exception\BaselineSchemaException;
use PHPSpellcheck\Core\Version;

final class BaselineStorage
{
    public const SCHEMA = 1;

    public function exists(string $path): bool
    {
        return is_file($path);
    }

    public function load(string $path): Baseline
    {
        if (!is_file($path)) {
            return Baseline::empty();
        }

        $raw = file_get_contents($path);

        if (false === $raw) {
            throw new BaselineSchemaException(\sprintf('Unable to read the baseline file "%s".', $path));
        }

        $data = json_decode($raw, true);

        if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($data)) {
            throw new BaselineSchemaException(\sprintf('The baseline file "%s" is not valid JSON.', $path));
        }

        $schema = $data['schema'] ?? null;

        if (self::SCHEMA !== $schema) {
            throw new BaselineSchemaException(\sprintf('The baseline file "%s" uses schema %s but %d is expected. Regenerate it with "spellcheck:baseline".', $path, var_export($schema, true), self::SCHEMA));
        }

        if (($data['fingerprint_schema'] ?? null) !== Fingerprint::SCHEMA_VERSION) {
            throw new BaselineSchemaException(\sprintf('The baseline file "%s" was generated with fingerprint schema %s but %s is in use. Regenerate it with "spellcheck:baseline".', $path, var_export($data['fingerprint_schema'] ?? null, true), Fingerprint::SCHEMA_VERSION));
        }

        $entries = $data['entries'] ?? [];

        if (!\is_array($entries)) {
            throw new BaselineSchemaException(\sprintf('The "entries" key of "%s" must be an object.', $path));
        }

        $validated = [];

        /** @var mixed $entry */
        foreach ($entries as $fingerprint => $entry) {
            if (!\is_string($fingerprint) || !\is_array($entry)) {
                throw new BaselineSchemaException(\sprintf('Malformed baseline entry in "%s".', $path));
            }

            $validated[$fingerprint] = [
                'word' => \is_string($entry['word'] ?? null) ? $entry['word'] : '',
                'type' => \is_string($entry['type'] ?? null) ? $entry['type'] : 'spelling',
                'language' => \is_string($entry['language'] ?? null) ? $entry['language'] : '',
                'context' => \is_array($entry['context'] ?? null) ? $entry['context'] : [],
                'seen_at' => \is_string($entry['seen_at'] ?? null) ? $entry['seen_at'] : '',
            ];
        }

        return Baseline::fromEntries(
            $validated,
            \is_string($data['config_hash'] ?? null) ? $data['config_hash'] : '',
        );
    }

    /**
     * Writes the baseline sorted by fingerprint. "generated_at" is only
     * refreshed when the entries actually change, so that regenerating an
     * unchanged baseline produces a byte identical file.
     */
    public function save(string $path, Baseline $baseline): void
    {
        $entries = $baseline->entries();
        ksort($entries);

        $generatedAt = (new \DateTimeImmutable())->format(\DATE_ATOM);

        if (is_file($path)) {
            try {
                $existing = $this->load($path);

                if ($this->sameEntries($existing->entries(), $entries)) {
                    /** @var array<string, mixed> $previous */
                    $previous = json_decode((string) file_get_contents($path), true) ?: [];

                    if (\is_string($previous['generated_at'] ?? null)) {
                        $generatedAt = $previous['generated_at'];
                    }
                }
            } catch (BaselineSchemaException) {
                // A corrupted or outdated baseline is simply replaced.
            }
        }

        $payload = [
            'schema' => self::SCHEMA,
            'generated_at' => $generatedAt,
            'tool_version' => Version::string(),
            'fingerprint_schema' => Fingerprint::SCHEMA_VERSION,
            'config_hash' => $baseline->configHash(),
            'entries' => $entries,
        ];

        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            throw new BaselineSchemaException('Unable to encode the baseline as JSON.');
        }

        $directory = \dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new BaselineSchemaException(\sprintf('Unable to create the directory "%s".', $directory));
        }

        file_put_contents($path, $json."\n");
    }

    /**
     * @param array<string, array<string, mixed>> $a
     * @param array<string, array<string, mixed>> $b
     */
    private function sameEntries(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);

        return array_keys($a) === array_keys($b);
    }
}
