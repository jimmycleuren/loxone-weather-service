<?php

namespace App\Tests\Doubles\WeatherProvider;

use App\WeatherProvider\WeatherProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Weatherbit extends \App\WeatherProvider\Weatherbit
{
    public function __construct(string $key, HttpClientInterface $client)
    {

    }

    public function getCurrentWeather($lat, $lon)
    {

    }

    public function getForecast($lat, $lon)
    {

    }

    public function getNormalizedData($lat, $lon, $asl)
    {
        return [
            'normalized' => [
                'city' => "Hasselt",
                'lat' => 1000,
                'lon' => 1000,
                'asl' => 1000,
                'country' => "BE",
                'timezone' => "UTC+1",
                'timezonediff' => 3600,
                'sunrise' => "08:00",
                'sunset' => "18:00",
                'forecasts' => [
                    [
                        'timestamp' => 1000000,
                        'temperature' => 23,
                        'apparent_temperature' => 23,
                        'wind_direction' => 80,
                        'wind_speed' => 3,
                        'wind_gust_speed' => 0,
                        'clouds_low' => 100,
                        'clouds_mid' => 100,
                        'clouds_high' => 100,
                        'precipation_intensity' => 0,
                        'precipation_probability' => 0,
                        'snow' => 0,
                        'pressure' => 1300,
                        'humidity' => 50,
                        'solar_radiation' => 5,
                        'icon' => 10
                    ]
                ]
            ],
            'current' => null,
            'forecast' => null
        ];
    }
}