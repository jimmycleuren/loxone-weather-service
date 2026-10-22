<?php

namespace App\WeatherProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenWeatherMap implements WeatherProviderInterface
{
    public function __construct(private readonly string $key, private readonly HttpClientInterface $client)
    {
    }

    public function getCurrentWeather($lat, $lon)
    {
        $response = $this->client->request('GET', "https://api.openweathermap.org/data/2.5/weather?appid=$this->key&lat=$lat&lon=$lon&units=metric");

        return json_decode($response->getContent());
    }

    public function getForecast($lat, $lon)
    {
        $response = $this->client->request('GET', "https://api.openweathermap.org/data/3.0/onecall?appid=$this->key&lat=$lat&lon=$lon&units=metric");

        return json_decode($response->getContent());
    }

    public function getNormalizedData($lat, $lon, $asl)
    {
        $current = $this->getCurrentWeather($lat, $lon);
        $forecast = $this->getForecast($lat, $lon);

        $normalizedforecast = [];

        foreach (array_merge([$forecast->current], $forecast->hourly, $forecast->daily) as $row) {
            $normalizedforecast[] = [
                'timestamp' => $row->dt,
                'temperature' => $row->temp->day ?? $row->temp,
                'apparent_temperature' => $row->feels_like->day ?? $row->feels_like,
                'wind_direction' => $row->wind_deg,
                'wind_speed' => $row->wind_speed,
                'wind_gust_speed' => $row->wind_gust ?? $row->wind_speed,
                'clouds_low' => $row->clouds_low ?? $row->clouds,
                'clouds_mid' => $row->clouds_mid ?? $row->clouds,
                'clouds_high' => $row->clouds_hi ?? $row->clouds,
                'precipation_intensity' => $row->rain->{'1h'} ?? $row->rain ?? 0,
                'precipation_probability' => $row->pop ?? 0,
                'snow' => $row->snow ?? 0,
                'pressure' => $row->pressure,
                'humidity' => $row->humidity,
                'solar_radiation' => $row->uvi,
                'icon' => $this->codeToText($row->weather[0]->id),
            ];
        }

        return [
            'normalized' => [
                // 'city' => $forecast->data[0]->city_name,
                'city' => $current->name,
                'lat' => $forecast->lat,
                'lon' => $forecast->lon,
                'asl' => $asl,
                // 'country' => $current->data[0]->country_code,
                'country' => $current->sys->country,
                'timezone' => $forecast->timezone,
                'timezonediff' => $forecast->timezone_offset / 3600,
                'sunrise' => $forecast->current->sunrise,
                'sunset' => $forecast->current->sunset,
                'forecasts' => $normalizedforecast,
            ],
            'current' => null,
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
