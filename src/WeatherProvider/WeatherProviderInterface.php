<?php

declare(strict_types=1);

namespace App\WeatherProvider;

use App\Forecast\Forecast;

interface WeatherProviderInterface
{
    public function fetchForecast(float|int|string $lat, float|int|string $lon, int|string $asl): Forecast;
}
