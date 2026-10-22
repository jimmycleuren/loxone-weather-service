<?php

declare(strict_types=1);

namespace App\Forecast;

/**
 * Provider-neutral weather condition.
 */
enum WeatherIcon: string
{
    case Clear = 'CLEAR';
    case CloudsLight = 'CLOUDS_LIGHT';
    case CloudsMedium = 'CLOUDS_MEDIUM';
    case CloudsHeavy = 'CLOUDS_HEAVY';
    case Cloudy = 'CLOUDY';
    case Fog = 'FOG';
    case RainLight = 'RAIN_LIGHT';
    case RainMedium = 'RAIN_MEDIUM';
    case RainHeavy = 'RAIN_HEAVY';
    case StormMedium = 'STORM_MEDIUM';
    case StormHeavy = 'STORM_HEAVY';
    case SnowLight = 'SNOW_LIGHT';
    case SnowMedium = 'SNOW_MEDIUM';
    case SnowHeavy = 'SNOW_HEAVY';
}
