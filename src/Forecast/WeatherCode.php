<?php

declare(strict_types=1);

namespace App\Forecast;

/**
 * Maps the numeric condition codes of Weatherbit and OpenWeatherMap. Both use
 * the same (OpenWeatherMap-derived) code ranges, so one table serves both.
 */
final class WeatherCode
{
    public static function toIcon(int|string $code): ?WeatherIcon
    {
        // The APIs send numeric codes, sometimes as strings.
        return match ((int) $code) {
            200, 201, 230, 231 => WeatherIcon::StormMedium,
            202, 232, 233 => WeatherIcon::StormHeavy,
            300, 301, 500, 520 => WeatherIcon::RainLight,
            302, 501, 511, 521, 900 => WeatherIcon::RainMedium,
            502, 522 => WeatherIcon::RainHeavy,
            600, 610, 611 => WeatherIcon::SnowLight,
            601, 612, 621 => WeatherIcon::SnowMedium,
            602, 622, 623 => WeatherIcon::SnowHeavy,
            700, 711, 721, 731, 741, 751 => WeatherIcon::Fog,
            800 => WeatherIcon::Clear,
            801 => WeatherIcon::CloudsLight,
            802 => WeatherIcon::CloudsMedium,
            803 => WeatherIcon::CloudsHeavy,
            804 => WeatherIcon::Cloudy,
            default => null,
        };
    }
}
