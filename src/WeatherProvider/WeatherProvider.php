<?php

namespace App\WeatherProvider;

use App\Forecast\Forecast;
use App\LocationProvider\LocationProvider;
use App\Loxone\LoxoneCsvRenderer;
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
        private readonly LoxoneCsvRenderer $renderer,
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
                $data[$user] = $provider->fetchForecast($lat, $lon, $asl);
            }
        }

        $item = $this->store->getItem('weather');
        $item->expiresAfter(3600 * 24);
        $item->set($data);
        $this->store->save($item);
    }

    public function getCSV($user): ?string
    {
        $item = $this->store->getItem('weather');

        if (!$item->isHit()) {
            return null;
        }

        // Entries written by older releases (arrays) are a miss: the caller refreshes.
        $forecast = $item->get()[$user] ?? null;

        return $forecast instanceof Forecast ? $this->renderer->render($forecast) : null;
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
}
