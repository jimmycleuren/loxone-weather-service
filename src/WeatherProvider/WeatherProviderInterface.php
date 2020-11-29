<?php

namespace App\WeatherProvider;

interface WeatherProviderInterface {

    public function __construct($key);

    public function getCurrentWeather($lat, $lon);

    public function getForecast($lat, $lon);

    public function getNormalizedData($lat, $lon, $asl);
}