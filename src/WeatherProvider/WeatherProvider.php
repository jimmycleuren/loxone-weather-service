<?php

namespace App\WeatherProvider;

use App\LocationProvider\LocationProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class WeatherProvider
{
    private readonly WeatherSource $source;

    public function __construct(
        private readonly LocationProvider $locationProvider,
        private readonly Weatherbit $weatherbit,
        private readonly OpenWeatherMap $openweathermap,
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $store,
        string $source = 'auto',
        private readonly string $weatherbitKey = '',
        private readonly string $openWeatherMapKey = '',
    ) {
        $this->source = WeatherSource::fromConfig($source);
    }

    public function updateCache()
    {
        $data = [];
        $provider = $this->selectProvider();

        $this->logger->info('Updating cache');
        if ($provider instanceof WeatherProviderInterface) {
            foreach ($this->locationProvider->getUsers() as $user) {
                [$lat, $lon, $asl] = $this->locationProvider->getCoordinates($user);

                $this->logger->info("Updating cache for $user");
                $data[$user] = $provider->getNormalizedData($lat, $lon, $asl);
            }
        }

        $item = $this->store->getItem('weather');
        $item->expiresAfter(3600 * 24);
        $item->set($data);
        $this->store->save($item);
    }

    public function getCSV($user)
    {
        $item = $this->store->getItem('weather');

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        if (!isset($data[$user])) {
            return null;
        }

        $data = $data[$user]['normalized'];

        $header = [
            '',
            $data['city'],
            $data['lon'],
            $data['lat'],
            $data['asl'],
            $data['country'],
            $data['timezone'],
            'UTC'.($data['timezonediff'] > 0 ? '+' : '').$data['timezonediff'],
            $data['sunrise'],
            $data['sunset'],
            '',
        ];

        $forecasts = [];
        foreach ($data['forecasts'] as $forecast) {
            $forecasts[] = implode(';', [
                date('d.m.Y', $forecast['timestamp']),
                date('l', $forecast['timestamp']),
                date('H', $forecast['timestamp']),
                sprintf('%5.1f', $forecast['temperature']),
                sprintf('%5.1f', $forecast['apparent_temperature']),
                sprintf('%3.0f', $forecast['wind_speed']),
                sprintf('%3.0f', $forecast['wind_direction']),
                sprintf('%3.0f', $forecast['wind_gust_speed']),
                sprintf('%3.0f', $forecast['clouds_low']),
                sprintf('%3.0f', $forecast['clouds_mid']),
                sprintf('%3.0f', $forecast['clouds_high']),
                sprintf('%5.1f', $forecast['precipation_intensity']),
                sprintf('%3.0f', $forecast['precipation_probability']),
                sprintf('%3.1f', $forecast['snow']->{'1h'} ?? $forecast['snow']),
                sprintf('%4.0f', $forecast['pressure']),
                sprintf('%3.0f', $forecast['humidity']),
                sprintf('%6.0f', 0.0), // cape
                sprintf('%d', $this->getIcon($forecast['icon'])), // icon
                sprintf('%4.0f', $forecast['solar_radiation']),
            ]);
        }

        return
            "<mb_metadata>\n".
            "id;name;longitude;latitude;height (m.asl.);country;timezone;utc-timedifference;sunrise;sunset;\n".
            "local date;weekday;local time;temperature(C);feeledTemperature(C);windspeed(km/h);winddirection(degr);wind gust(km/h);low clouds(%);medium clouds(%);high clouds(%);precipitation(mm);probability of Precip(%);snowFraction;sea level pressure(hPa);relative humidity(%);CAPE;picto-code;radiation (W/m2)\n".
            "</mb_metadata>\n".
            "<valid_until>2100-01-01</valid_until>\n".
            "<station>\n".
            implode(';', $header)."\n".
            implode("\n", $forecasts)."\n".
            "</station>\n"
        ;
    }

    private function selectProvider(): ?WeatherProviderInterface
    {
        $weatherbit = '' !== $this->weatherbitKey;
        $openWeatherMap = '' !== $this->openWeatherMapKey;

        $provider = match ($this->source) {
            WeatherSource::Weatherbit => $weatherbit ? $this->weatherbit : null,
            WeatherSource::OpenWeatherMap => $openWeatherMap ? $this->openweathermap : null,
            WeatherSource::Auto => $weatherbit ? $this->weatherbit : ($openWeatherMap ? $this->openweathermap : null),
        };

        if (null === $provider) {
            $this->logger->error(match ($this->source) {
                WeatherSource::Weatherbit => 'WEATHER_PROVIDER is weatherbit, but WEATHERBIT_KEY is not set',
                WeatherSource::OpenWeatherMap => 'WEATHER_PROVIDER is openweathermap, but OPENWEATHERMAP_KEY is not set',
                WeatherSource::Auto => 'No weather provider configured: set WEATHERBIT_KEY or OPENWEATHERMAP_KEY',
            });
        }

        return $provider;
    }

    private function getIcon($text)
    {
        return match ($text) {
            'CLEAR' => 1,
            'CLOUDS_LIGHT' => 2,
            'CLOUDS_MEDIUM' => 7,
            'CLOUDS_HEAVY' => 19,
            'CLOUDY' => 22,
            'FOG' => 16,
            'RAIN_LIGHT' => 31,
            'RAIN_MEDIUM' => 23,
            'RAIN_HEAVY' => 25,
            'STORM_MEDIUM' => 28,
            'STORM_HEAVY' => 27,
            'SNOW_LIGHT' => 32,
            'SNOW_MEDIUM' => 24,
            'SNOW_HEAVY' => 26,
            default => null,
        };
    }
}
