<?php

declare(strict_types=1);

namespace App\WeatherProvider;

/**
 * Typed reads of decoded API JSON. PHP's own casts, but only on values that
 * can be cast: a missing field (null) is 0 / '', a nested object or list is
 * not silently turned into a number.
 *
 * @internal
 */
final class Json
{
    public static function float(mixed $value): float
    {
        return \is_scalar($value) || null === $value ? (float) $value : 0.0;
    }

    public static function int(mixed $value): int
    {
        return \is_scalar($value) || null === $value ? (int) $value : 0;
    }

    public static function string(mixed $value): string
    {
        return \is_scalar($value) || null === $value ? (string) $value : '';
    }

    /**
     * Timestamp or local time, as the provider reports it.
     */
    public static function intOrString(mixed $value): int|string
    {
        return \is_int($value) || \is_string($value) ? $value : self::string($value);
    }
}
