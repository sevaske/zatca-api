<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response\Concerns;

/**
 * Tolerant readers for decoded API payloads.
 *
 * @internal
 */
trait ReadsData
{
    /**
     * @param array<array-key, mixed> $data
     */
    private static function readString(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readNullableString(array $data, string $key): ?string
    {
        $value = self::readString($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function readArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * Reads a list of objects; ZATCA sometimes returns a single object instead of a list, or null.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    private static function readObjectList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value) || $value === []) {
            return [];
        }

        // a single object instead of a list
        if (array_keys($value) !== range(0, count($value) - 1)) {
            return [$value];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
