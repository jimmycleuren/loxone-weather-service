<?php

namespace App\WeatherProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Weatherbit implements WeatherProviderInterface {

    private $key;
    private $client;

    public function __construct(string $key, HttpClientInterface $client)
    {
        $this->key = $key;
        $this->client = $client;
    }

    public function getCurrentWeather($lat, $lon)
    {
        $response = $this->client->request("GET", "https://api.weatherbit.io//v2.0/current?key=$this->key&lat=$lat&lon=$lon");

        return json_decode($response->getContent());
    }

    public function getForecast($lat, $lon)
    {
        $response1 = $this->client->request("GET", "https://api.weatherbit.io//v2.0/forecast/hourly?key=$this->key&lat=$lat&lon=$lon");
        $response2 = $this->client->request("GET", "https://api.weatherbit.io//v2.0/forecast/daily?key=$this->key&lat=$lat&lon=$lon");

        $hourly = json_decode($response1->getContent())->data;
        $daily = json_decode($response2->getContent())->data;

        return array_merge($hourly, $daily);
    }

    public function getNormalizedData($lat, $lon, $asl)
    {
        $current = $this->getCurrentWeather($lat, $lon);
        $forecast = $this->getForecast($lat, $lon);

        $normalizedforecast = [];
        $timezonediff = 0;

        foreach (array_merge($current->data, $forecast) as $row) {
            $normalizedforecast[] = [
                'timestamp' => $row->ts,
                'temperature' => $row->temp ?? $row->max_temp,
                'apparent_temperature' => $row->app_temp ?? $row->app_max_temp,
                'wind_direction' => $row->wind_dir,
                'wind_speed' => $row->wind_spd,
                'wind_gust_speed' => $row->wind_gust_spd ?? 0,
                'clouds_low' => $row->clouds_low ?? $row->clouds,
                'clouds_mid' => $row->clouds_mid ?? $row->clouds,
                'clouds_high' => $row->clouds_hi ?? $row->clouds,
                'precipation_intensity' => $row->precip,
                'precipation_probability' => $row->pop ?? ($row->precip ? 100 : 0),
                'snow' => $row->snow,
                'pressure' => $row->pres,
                'humidity' => $row->rh,
                'solar_radiation' => $row->solar_rad ?? $row->max_dhi,
                'icon' => $this->codeToText($row->weather->code)
            ];
            if (isset($row->timestamp_local) && isset($row->timestamp_utc)) {
                $timezonediff = (strtotime($row->timestamp_local) - strtotime($row->timestamp_utc)) / 3600;
            }
        }

        return [
            'normalized' => [
                'city' => $current->data[0]->city_name,
                'lat' => $current->data[0]->lat,
                'lon' => $current->data[0]->lon,
                'asl' => $asl,
                'country' => $current->data[0]->country_code,
                'timezone' => $current->data[0]->timezone,
                'timezonediff' => $timezonediff,
                'sunrise' => $current->data[0]->sunrise,
                'sunset' => $current->data[0]->sunset,
                'forecasts' => $normalizedforecast
            ],
            'current' => $current,
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