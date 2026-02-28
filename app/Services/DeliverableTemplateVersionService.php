<?php declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class DeliverableTemplateVersionService
{
    private const PLACEHOLDER_KEY_PATTERN = '/^[a-zA-Z0-9_.-]+$/';
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/';
    private const ALLOWED_TYPES = ['string', 'number', 'boolean', 'date', 'datetime', 'html'];
    private const DEFAULT_SCHEMA_VERSION = '1.0.0';

    public function computeChecksum(string $html): string
    {
        return hash('sha256', $html);
    }

    /**
     * @param array<string, mixed>|string|null $spec
     * @return array<string, mixed>
     */
    public function normalizePlaceholdersSpec(array|string|null $spec, string $html): array
    {
        $specWasProvided = $spec !== null;

        if (is_string($spec)) {
            $decoded = json_decode($spec, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('placeholders_spec_json must be a valid JSON object.');
            }
            $spec = $decoded;
        }

        $placeholders = $spec['placeholders'] ?? null;
        if ($spec === null || $placeholders === null) {
            $inferredPlaceholders = $this->inferPlaceholdersFromHtml($html);

            return [
                'schema_version' => self::DEFAULT_SCHEMA_VERSION,
                'placeholders' => $inferredPlaceholders,
                ...$this->buildPlaceholderIntelligence($html),
            ];
        }

        if (!is_array($placeholders)) {
            throw new InvalidArgumentException('placeholders_spec.placeholders must be an array.');
        }

        $normalized = [];
        foreach ($placeholders as $placeholder) {
            if (is_string($placeholder)) {
                $placeholder = ['key' => $placeholder];
            }

            if (!is_array($placeholder)) {
                throw new InvalidArgumentException('Each placeholder must be a string key or object.');
            }

            $key = (string) ($placeholder['key'] ?? '');
            if ($key === '' || preg_match(self::PLACEHOLDER_KEY_PATTERN, $key) !== 1) {
                throw new InvalidArgumentException('Placeholder key is invalid.');
            }

            $type = (string) ($placeholder['type'] ?? 'string');
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                throw new InvalidArgumentException('Placeholder type is invalid.');
            }

            $normalized[$key] = [
                'key' => $key,
                'type' => $type,
                'required' => (bool) ($placeholder['required'] ?? false),
            ];
        }

        ksort($normalized);

        $normalizedPlaceholders = array_values($normalized);
        $knownFieldKeys = $specWasProvided
            ? $this->extractFieldKeysFromPlaceholders($normalizedPlaceholders)
            : null;

        return [
            'schema_version' => (string) ($spec['schema_version'] ?? self::DEFAULT_SCHEMA_VERSION),
            'placeholders' => $normalizedPlaceholders,
            ...$this->buildPlaceholderIntelligence($html, $knownFieldKeys),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inferPlaceholdersFromHtml(string $html): array
    {
        $keys = $this->extractPlaceholderKeys($html);

        return array_map(static fn (string $key): array => [
            'key' => $key,
            'type' => 'string',
            'required' => false,
        ], $keys);
    }

    /**
     * @param list<string>|null $knownFieldKeys
     * @return array{found: array<string, list<string>>, warnings: list<array{type: string, key: string, message: string}>}
     */
    public function buildPlaceholderIntelligence(string $html, ?array $knownFieldKeys = null): array
    {
        $all = $this->extractPlaceholderKeys($html);
        $builtins = [];
        $fields = [];
        $unknown = [];
        $warnings = [];
        $knownFieldLookup = $knownFieldKeys !== null ? array_fill_keys($knownFieldKeys, true) : null;

        foreach ($all as $key) {
            if ($this->isBuiltinPlaceholder($key)) {
                $builtins[] = $key;
                continue;
            }

            if ($this->isFieldPlaceholder($key)) {
                $fields[] = $key;

                if ($knownFieldLookup !== null) {
                    $fieldKey = substr($key, 7);
                    if (!isset($knownFieldLookup[$fieldKey])) {
                        $warnings[] = [
                            'type' => 'unmapped_field',
                            'key' => $key,
                            'message' => sprintf('Field placeholder "%s" is not mapped in the provided placeholder spec.', $key),
                        ];
                    }
                }

                continue;
            }

            $unknown[] = $key;
            $warnings[] = [
                'type' => 'unknown_placeholder',
                'key' => $key,
                'message' => sprintf('Unknown placeholder "%s" was found in the HTML.', $key),
            ];
        }

        return [
            'found' => [
                'all' => $all,
                'builtins' => $builtins,
                'fields' => $fields,
                'unknown' => $unknown,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $html, array $context): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $matches): string => $this->stringifyForHtml($context[$matches[1]] ?? null),
            $html
        );
    }

    public function stringifyForHtml(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($value instanceof \DateTimeInterface) {
            return htmlspecialchars($value->format(\DateTimeInterface::ATOM), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return htmlspecialchars($encoded === false ? '' : $encoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholderKeys(string $html): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $html, $matches);

        $keys = array_values(array_unique($matches[1] ?? []));
        sort($keys);

        return $keys;
    }

    private function isBuiltinPlaceholder(string $key): bool
    {
        return preg_match('/^(project|wi)\.[a-zA-Z0-9_.-]+$/', $key) === 1;
    }

    private function isFieldPlaceholder(string $key): bool
    {
        return preg_match('/^fields\.[a-zA-Z0-9_.-]+$/', $key) === 1;
    }

    /**
     * @param array<int, array<string, mixed>> $placeholders
     * @return list<string>
     */
    private function extractFieldKeysFromPlaceholders(array $placeholders): array
    {
        $fieldKeys = [];

        foreach ($placeholders as $placeholder) {
            $key = (string) ($placeholder['key'] ?? '');
            if (!$this->isFieldPlaceholder($key)) {
                continue;
            }

            $fieldKeys[] = substr($key, 7);
        }

        $fieldKeys = array_values(array_unique($fieldKeys));
        sort($fieldKeys);

        return $fieldKeys;
    }
}
