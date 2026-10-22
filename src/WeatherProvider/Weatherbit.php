<?php

declare(strict_types=1);

namespace App\WeatherProvider;

use App\Forecast\Forecast;
use App\Forecast\ForecastRow;
use App\Forecast\WeatherCode;
use App\Location\Location;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Weatherbit implements WeatherProviderInterface
{
    public function __construct(private readonly string $key, private readonly HttpClientInterface $client)
    {
    }

    public function getCurrentWeather(string $lat, string $lon): mixed
    {
        $response = $this->client->request('GET', "https://api.weatherbit.io/v2.0/current?key=$this->key&lat=$lat&lon=$lon");

        return json_decode($response->getContent());
    }

    public function getForecast(string $lat, string $lon): mixed
    {
        $response = $this->client->request('GET', "https://api.weatherbit.io/v2.0/forecast/daily?key=$this->key&lat=$lat&lon=$lon");

        return json_decode($response->getContent())->data;
    }

    public function fetchForecast(Location $location): Forecast
    {
        $current = $this->getCurrentWeather($location->latitude, $location->longitude);
        $forecast = $this->getForecast($location->latitude, $location->longitude);

        $rows = [];
        $utcOffsetHours = 0;

        // current conditions first, then the daily forecast (max_* fields)
        foreach (array_merge($current->data, $forecast) as $row) {
            $rows[] = new ForecastRow(
                timestamp: Json::int($row->ts),
                temperature: Json::float($row->temp ?? $row->max_temp),
                apparentTemperature: Json::float($row->app_temp ?? $row->app_max_temp),
                windSpeed: Json::float($row->wind_spd),
                windDirection: Json::float($row->wind_dir),
                windGustSpeed: Json::float($row->wind_gust_spd ?? 0),
                cloudsLow: Json::float($row->clouds_low ?? $row->clouds),
                cloudsMid: Json::float($row->clouds_mid ?? $row->clouds),
                cloudsHigh: Json::float($row->clouds_hi ?? $row->clouds),
                precipitationIntensity: Json::float($row->precip),
                precipitationProbability: Json::float($row->pop ?? ($row->precip ? 100 : 0)),
                snow: Json::float($row->snow),
                pressure: Json::float($row->pres),
                humidity: Json::float($row->rh),
                solarRadiation: Json::float($row->solar_rad ?? $row->max_dhi),
                icon: WeatherCode::toIcon(Json::int($row->weather->code)),
            );
            if (isset($row->timestamp_local) && isset($row->timestamp_utc)) {
                $utcOffsetHours = (strtotime(Json::string($row->timestamp_local)) - strtotime(Json::string($row->timestamp_utc))) / 3600;
            }
        }

        $station = $current->data[0];

        return new Forecast(
            city: Json::string($station->city_name),
            lat: Json::float($station->lat),
            lon: Json::float($station->lon),
            asl: $location->altitude,
            country: Json::string($station->country_code),
            timezone: Json::string($station->timezone),
            utcOffsetHours: $utcOffsetHours,
            sunrise: Json::intOrString($station->sunrise),
            sunset: Json::intOrString($station->sunset),
            rows: $rows,
        );
    }
}
