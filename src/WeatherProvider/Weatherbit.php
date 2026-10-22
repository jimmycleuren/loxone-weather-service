<?php

namespace App\WeatherProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Weatherbit implements WeatherProviderInterface
{
    public function __construct(private readonly string $key, private readonly HttpClientInterface $client)
    {
    }

    public function getCurrentWeather($lat, $lon)
    {
        $response = $this->client->request('GET', "https://api.weatherbit.io/v2.0/current?key=$this->key&lat=$lat&lon=$lon");

        return json_decode($response->getContent());
    }

    public function getForecast($lat, $lon)
    {
        $response = $this->client->request('GET', "https://api.weatherbit.io/v2.0/forecast/daily?key=$this->key&lat=$lat&lon=$lon");

        return json_decode($response->getContent())->data;
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
                'icon' => $this->codeToText($row->weather->code),
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
                'forecasts' => $normalizedforecast,
            ],
            'current' => $current,
            'forecast' => $forecast,
        ];
    }

    private function codeToText(int|string $code): ?string
    {
        // The APIs send numeric codes; cast so match (strict) behaves like the old loose switch.
        return match ((int) $code) {
            200, 201, 230, 231 => 'STORM_MEDIUM',
            202, 232, 233 => 'STORM_HEAVY',
            300, 301, 500, 520 => 'RAIN_LIGHT',
            302, 501, 511, 521, 900 => 'RAIN_MEDIUM',
            502, 522 => 'RAIN_HEAVY',
            600, 610, 611 => 'SNOW_LIGHT',
            601, 612, 621 => 'SNOW_MEDIUM',
            602, 622, 623 => 'SNOW_HEAVY',
            700, 711, 721, 731, 741, 751 => 'FOG',
            800 => 'CLEAR',
            801 => 'CLOUDS_LIGHT',
            802 => 'CLOUDS_MEDIUM',
            803 => 'CLOUDS_HEAVY',
            804 => 'CLOUDY',
            default => null,
        };
    }
}
