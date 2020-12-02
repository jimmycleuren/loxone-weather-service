<?php

namespace App\WeatherProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

interface WeatherProviderInterface {

    public function __construct(string $key, HttpClientInterface $client);

    public function getCurrentWeather($lat, $lon);

    public function getForecast($lat, $lon);

    public function getNormalizedData($lat, $lon, $asl);
}