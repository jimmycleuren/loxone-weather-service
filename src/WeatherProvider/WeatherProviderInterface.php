<?php

declare(strict_types=1);

namespace App\WeatherProvider;

use App\Forecast\Forecast;
use App\Location\Location;

interface WeatherProviderInterface
{
    public function fetchForecast(Location $location): Forecast;
}
