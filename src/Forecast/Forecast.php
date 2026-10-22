<?php

declare(strict_types=1);

namespace App\Forecast;

/**
 * A provider's forecast for one location, normalized.
 */
final readonly class Forecast
{
    /**
     * @param int|string        $sunrise as reported by the provider (timestamp or local time)
     * @param int|string        $sunset  as reported by the provider (timestamp or local time)
     * @param list<ForecastRow> $rows    current conditions first, then hours/days
     */
    public function __construct(
        public string $city,
        public float $lat,
        public float $lon,
        public string $asl,
        public string $country,
        public string $timezone,
        public float $utcOffsetHours,
        public int|string $sunrise,
        public int|string $sunset,
        public array $rows,
    ) {
    }
}
