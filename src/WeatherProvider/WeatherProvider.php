<?php

namespace App\WeatherProvider;

use App\LocationProvider\LocationProvider;
use Psr\Log\LoggerInterface;

class WeatherProvider {

    private $locationProvider;
    private $weatherbit;
    private $openweathermap;
    private $logger;

    public function __construct(LocationProvider $locationProvider, Weatherbit $weatherbit, OpenWeatherMap $openWeatherMap, LoggerInterface $logger)
    {
        $this->locationProvider = $locationProvider;
        $this->weatherbit = $weatherbit;
        $this->openweathermap = $openWeatherMap;
        $this->logger = $logger;
    }

    public function updateCache()
    {
        $data = [];

        $this->logger->info("Updating cache");
        foreach ($this->locationProvider->getUsers() as $user) {

            list($lat, $lon, $asl) = $this->locationProvider->getCoordinates($user);

            if (isset($_SERVER['WEATHERBIT_KEY']) && $_SERVER['WEATHERBIT_KEY']) {
                $this->logger->info("Updating cache for $user");
                $data[$user] = $this->weatherbit->getNormalizedData($lat, $lon, $asl);
            } elseif (isset($_SERVER['OPENWEATHERMAP_KEY']) && $_SERVER['OPENWEATHERMAP_KEY']) {
                $this->logger->info("Updating cache for $user");
                $data[$user] = $this->openweathermap->getNormalizedData($lat, $lon, $asl);
            }
        }

        file_put_contents("/tmp/weather.json", json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getCSV($user)
    {
        if (!file_exists("/tmp/weather.json")) {
            return null;
        }

        $data = json_decode(file_get_contents('/tmp/weather.json'));

        if (!isset($data->$user)) {
            return null;
        }

        $data = $data->$user->normalized;

        $header = [
            '',
            $data->city,
            $data->lon,
            $data->lat,
            $data->asl,
            $data->country,
            $data->timezone,
            'UTC'.($data->timezonediff > 0 ? '+' : '').$data->timezonediff,
            $data->sunrise,
            $data->sunset,
            ''
        ];

        $forecasts = [];
        foreach ($data->forecasts as $forecast) {
            $forecasts[] = implode(";", [
                date("d.m.Y", $forecast->timestamp),
                date("l", $forecast->timestamp),
                date("H", $forecast->timestamp),
                sprintf("%5.1f", $forecast->temperature),
                sprintf("%5.1f", $forecast->apparent_temperature),
                sprintf("%3.0f", $forecast->wind_speed),
                sprintf("%3.0f", $forecast->wind_direction),
                sprintf("%3.0f", $forecast->wind_gust_speed),
                sprintf("%3.0f", $forecast->clouds_low),
                sprintf("%3.0f", $forecast->clouds_mid),
                sprintf("%3.0f", $forecast->clouds_high),
                sprintf("%5.1f", $forecast->precipation_intensity),
                sprintf("%3.0f", $forecast->precipation_probability),
                sprintf("%3.1f", $forecast->snow),
                sprintf("%4.0f", $forecast->pressure),
                sprintf("%3.0f", $forecast->humidity),
                sprintf("%6.0f", 0.0), //cape
                sprintf("%d", $this->getIcon($forecast->icon)), //icon
                sprintf("%4.0f", $forecast->solar_radiation),
            ]);
        }

        return
            "<mb_metadata>\n".
            "id;name;longitude;latitude;height (m.asl.);country;timezone;utc-timedifference;sunrise;sunset;\n".
            "local date;weekday;local time;temperature(C);feeledTemperature(C);windspeed(km/h);winddirection(degr);wind gust(km/h);low clouds(%);medium clouds(%);high clouds(%);precipitation(mm);probability of Precip(%);snowFraction;sea level pressure(hPa);relative humidity(%);CAPE;picto-code;radiation (W/m2)\n".
            "</mb_metadata>\n".
            "<valid_until>2100-01-01</valid_until>\n".
            "<station>\n".
            implode(";", $header)."\n".
            implode("\n", $forecasts)."\n".
            "</station>\n"
            ;
    }

    private function getIcon($text)
    {
        switch ($text) {
            case 'CLEAR': return 1;
            case 'CLOUDS_LIGHT': return 2;
            case 'CLOUDS_MEDIUM': return 7;
            case 'CLOUDS_HEAVY': return 19;
            case 'CLOUDY': return 22;

            case 'FOG': return 16;

            case 'RAIN_LIGHT': return 31;
            case 'RAIN_MEDIUM': return 23;
            case 'RAIN_HEAVY': return 25;

            case 'STORM_MEDIUM': return 28;
            case 'STORM_HEAVY': return 27;

            case 'SNOW_LIGHT': return 32;
            case 'SNOW_MEDIUM': return 24;
            case 'SNOW_HEAVY': return 26;
        }
    }
}