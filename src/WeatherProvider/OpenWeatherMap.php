<?php

namespace App\WeatherProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenWeatherMap implements WeatherProviderInterface {

    private $key;
    private $client;

    public function __construct(string $key, HttpClientInterface $client)
    {
        $this->key = $key;
        $this->client = $client;
    }

    public function getCurrentWeather($lat, $lon)
    {

    }

    public function getForecast($lat, $lon)
    {
        $response = $this->client->request("GET", "https://api.openweathermap.org/data/2.5/onecall?appid=$this->key&lat=$lat&lon=$lon&units=metric");

        return json_decode($response->getContent());
    }

    public function getNormalizedData($lat, $lon, $asl)
    {
        $forecast = $this->getForecast($lat, $lon);

        $normalizedforecast = [];

        foreach (array_merge([$forecast->current], $forecast->hourly, $forecast->daily) as $row) {
            $normalizedforecast[] = [
                'timestamp' => $row->dt,
                'temperature' => $row->temp,
                'apparent_temperature' => $row->feels_like,
                'wind_direction' => $row->wind_deg,
                'wind_speed' => $row->wind_speed,
                'wind_gust_speed' => $row->wind_gust ?? $row->wind_speed,
                'clouds_low' => $row->clouds_low ?? $row->clouds,
                'clouds_mid' => $row->clouds_mid ?? $row->clouds,
                'clouds_high' => $row->clouds_hi ?? $row->clouds,
                'precipation_intensity' => $row->rain ?? 0,
                'precipation_probability' => $row->pop ?? 0,
                'snow' => $row->snow ?? 0,
                'pressure' => $row->pressure,
                'humidity' => $row->humidity,
                'solar_radiation' => $row->uvi,
                'icon' => $this->codeToText($row->weather[0]->id)
            ];
        }

        return [
            'normalized' => [
                //'city' => $forecast->data[0]->city_name,
                'lat' => $forecast->lat,
                'lon' => $forecast->lon,
                'asl' => $asl,
                //'country' => $current->data[0]->country_code,
                'timezone' => $forecast->timezone,
                'timezonediff' => $forecast->timezone_offset / 3600,
                'sunrise' => $forecast->current->sunrise,
                'sunset' => $forecast->current->sunset,
                'forecasts' => $normalizedforecast
            ],
            'current' => null,
            'forecast' => $forecast
        ];
    }

    private function codeToText($code)
    {
        switch($code) {
            case 200: return 'STORM_MEDIUM';
            case 201: return 'STORM_MEDIUM';
            case 202: return 'STORM_HEAVY';

            case 230: return 'STORM_MEDIUM';
            case 231: return 'STORM_MEDIUM';
            case 232: return 'STORM_HEAVY';
            case 233: return 'STORM_HEAVY';

            case 300: return 'RAIN_LIGHT';
            case 301: return 'RAIN_LIGHT';
            case 302: return 'RAIN_MEDIUM';

            case 500: return 'RAIN_LIGHT';
            case 501: return 'RAIN_MEDIUM';
            case 502: return 'RAIN_HEAVY';

            case 511: return 'RAIN_MEDIUM';

            case 520: return 'RAIN_LIGHT';
            case 521: return 'RAIN_MEDIUM';
            case 522: return 'RAIN_HEAVY';

            case 600: return 'SNOW_LIGHT';
            case 601: return 'SNOW_MEDIUM';
            case 602: return 'SNOW_HEAVY';

            case 610: return 'SNOW_LIGHT';
            case 611: return 'SNOW_LIGHT';
            case 612: return 'SNOW_MEDIUM';

            case 621: return 'SNOW_MEDIUM';
            case 622: return 'SNOW_HEAVY';
            case 623: return 'SNOW_HEAVY';

            case 700: return 'FOG';
            case 711: return 'FOG';
            case 721: return 'FOG';
            case 731: return 'FOG';
            case 741: return 'FOG';
            case 751: return 'FOG';

            case 800: return 'CLEAR';
            case 801: return 'CLOUDS_LIGHT';
            case 802: return 'CLOUDS_MEDIUM';
            case 803: return 'CLOUDS_HEAVY';
            case 804: return 'CLOUDY';

            case 900: return 'RAIN_MEDIUM';
        }
    }
}