<?php

declare(strict_types=1);

namespace App\WeatherProvider;

use App\Forecast\Forecast;
use App\Forecast\ForecastRow;
use App\Forecast\WeatherCode;
use App\Location\Location;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenWeatherMap implements WeatherProviderInterface
{
    public function __construct(private readonly string $key, private readonly HttpClientInterface $client)
    {
    }

    public function getCurrentWeather(string $lat, string $lon): mixed
    {
        $response = $this->client->request('GET', "https://api.openweathermap.org/data/2.5/weather?appid=$this->key&lat=$lat&lon=$lon&units=metric");

        return json_decode($response->getContent());
    }

    public function getForecast(string $lat, string $lon): mixed
    {
        $response = $this->client->request('GET', "https://api.openweathermap.org/data/3.0/onecall?appid=$this->key&lat=$lat&lon=$lon&units=metric");

        return json_decode($response->getContent());
    }

    public function fetchForecast(Location $location): Forecast
    {
        $current = $this->getCurrentWeather($location->latitude, $location->longitude);
        $forecast = $this->getForecast($location->latitude, $location->longitude);

        $rows = [];
        foreach (array_merge([$forecast->current], $forecast->hourly, $forecast->daily) as $row) {
            $rows[] = new ForecastRow(
                timestamp: Json::int($row->dt),
                // daily rows report temperatures per part of the day
                temperature: Json::float($row->temp->day ?? $row->temp),
                apparentTemperature: Json::float($row->feels_like->day ?? $row->feels_like),
                windSpeed: Json::float($row->wind_speed),
                windDirection: Json::float($row->wind_deg),
                windGustSpeed: Json::float($row->wind_gust ?? $row->wind_speed),
                cloudsLow: Json::float($row->clouds_low ?? $row->clouds),
                cloudsMid: Json::float($row->clouds_mid ?? $row->clouds),
                cloudsHigh: Json::float($row->clouds_hi ?? $row->clouds),
                precipitationIntensity: $this->amount($row->rain ?? 0),
                precipitationProbability: Json::float($row->pop ?? 0),
                snow: $this->amount($row->snow ?? 0),
                pressure: Json::float($row->pressure),
                humidity: Json::float($row->humidity),
                solarRadiation: Json::float($row->uvi),
                icon: WeatherCode::toIcon(Json::int($row->weather[0]->id)),
            );
        }

        return new Forecast(
            city: Json::string($current->name),
            lat: Json::float($forecast->lat),
            lon: Json::float($forecast->lon),
            asl: $location->altitude,
            country: Json::string($current->sys->country),
            timezone: Json::string($forecast->timezone),
            utcOffsetHours: Json::int($forecast->timezone_offset) / 3600,
            sunrise: Json::intOrString($forecast->current->sunrise),
            sunset: Json::intOrString($forecast->current->sunset),
            rows: $rows,
        );
    }

    /**
     * Hourly rows report rain/snow as {"1h": mm}, daily rows as a number.
     */
    private function amount(mixed $value): float
    {
        return Json::float(\is_object($value) ? ($value->{'1h'} ?? 0) : $value);
    }
}
