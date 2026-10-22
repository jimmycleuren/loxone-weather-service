<?php

declare(strict_types=1);

namespace App\Tests\Doubles\WeatherProvider;

use App\Forecast\Forecast;
use App\Forecast\ForecastRow;

class Weatherbit extends \App\WeatherProvider\Weatherbit
{
    public function __construct()
    {
    }

    public function getCurrentWeather($lat, $lon)
    {
    }

    public function getForecast($lat, $lon)
    {
    }

    public function fetchForecast(float|int|string $lat, float|int|string $lon, int|string $asl): Forecast
    {
        return new Forecast(
            city: 'Hasselt',
            lat: 1000.0,
            lon: 1000.0,
            asl: '1000',
            country: 'BE',
            timezone: 'UTC+1',
            utcOffsetHours: 3600.0,
            sunrise: '08:00',
            sunset: '18:00',
            rows: [
                new ForecastRow(
                    timestamp: 1000000,
                    temperature: 23.0,
                    apparentTemperature: 23.0,
                    windSpeed: 3.0,
                    windDirection: 80.0,
                    windGustSpeed: 0.0,
                    cloudsLow: 100.0,
                    cloudsMid: 100.0,
                    cloudsHigh: 100.0,
                    precipitationIntensity: 0.0,
                    precipitationProbability: 0.0,
                    snow: 0.0,
                    pressure: 1300.0,
                    humidity: 50.0,
                    solarRadiation: 5.0,
                    icon: null,
                ),
            ],
        );
    }
}
