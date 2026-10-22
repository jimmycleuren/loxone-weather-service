<?php

declare(strict_types=1);

namespace App\Forecast;

/**
 * One forecast row (current conditions, an hour or a day), in metric units.
 */
final readonly class ForecastRow
{
    public function __construct(
        public int $timestamp,
        public float $temperature,
        public float $apparentTemperature,
        public float $windSpeed,
        public float $windDirection,
        public float $windGustSpeed,
        public float $cloudsLow,
        public float $cloudsMid,
        public float $cloudsHigh,
        public float $precipitationIntensity,
        public float $precipitationProbability,
        public float $snow,
        public float $pressure,
        public float $humidity,
        public float $solarRadiation,
        public ?WeatherIcon $icon,
    ) {
    }
}
