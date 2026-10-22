<?php

declare(strict_types=1);

namespace App\WeatherProvider;

/**
 * Value of WEATHER_PROVIDER.
 */
enum WeatherSource: string
{
    /** Weatherbit if WEATHERBIT_KEY is set, otherwise OpenWeatherMap if OPENWEATHERMAP_KEY is set. */
    case Auto = 'auto';
    case Weatherbit = 'weatherbit';
    case OpenWeatherMap = 'openweathermap';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? throw new \InvalidArgumentException(\sprintf('Invalid WEATHER_PROVIDER "%s", expected one of: %s.', $value, implode(', ', array_map(static fn (self $s): string => $s->value, self::cases()))));
    }
}
