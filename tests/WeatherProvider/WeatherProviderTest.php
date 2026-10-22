<?php

declare(strict_types=1);

namespace App\Tests\WeatherProvider;

use App\Forecast\Forecast;
use App\Location\Location;
use App\Location\LocationProvider;
use App\Loxone\LoxoneCsvRenderer;
use App\WeatherProvider\OpenWeatherMap;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class WeatherProviderTest extends TestCase
{
    public function testGetCsv(): void
    {
        $store = new ArrayAdapter();
        $locationProvider = new LocationProvider(new NullLogger(), $store);
        $locationProvider->setLocation('test', new Location('0', '0', '0'));

        $weatherbit = $this->createMock(Weatherbit::class);
        $openweathermap = $this->createStub(OpenWeatherMap::class);
        $weatherbit->expects($this->once())->method('fetchForecast')->with(new Location('0', '0', '0'))->willReturn(new Forecast(
            city: 'Genk',
            lat: 0.0,
            lon: 0.0,
            asl: '0',
            country: 'BE',
            timezone: 'GMT+2',
            utcOffsetHours: 2.0,
            sunrise: '0800',
            sunset: '1700',
            rows: [],
        ));

        $weatherProvider = new WeatherProvider($locationProvider, $weatherbit, $openweathermap, new NullLogger(), $store, new LoxoneCsvRenderer(), 'auto', 'key', 'key');

        $this->assertEquals(null, $weatherProvider->getCSV('test'));

        $weatherProvider->updateCache();

        $this->assertStringContainsString('station', $weatherProvider->getCSV('test'));
    }

    /**
     * @return array<string, array{string, string, string, ?string}> WEATHER_PROVIDER, WEATHERBIT_KEY, OPENWEATHERMAP_KEY, expected provider
     */
    public static function sourceProvider(): array
    {
        return [
            'auto prefers weatherbit' => ['auto', 'wb', 'owm', 'weatherbit'],
            'auto falls back to openweathermap' => ['auto', '', 'owm', 'openweathermap'],
            'auto without keys' => ['auto', '', '', null],
            'explicit openweathermap with both keys (#368)' => ['openweathermap', 'wb', 'owm', 'openweathermap'],
            'explicit weatherbit' => ['weatherbit', 'wb', 'owm', 'weatherbit'],
            'explicit weatherbit without its key' => ['weatherbit', '', 'owm', null],
            'explicit openweathermap without its key' => ['openweathermap', 'wb', '', null],
            'case and whitespace are ignored' => [' OpenWeatherMap ', 'wb', 'owm', 'openweathermap'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sourceProvider')]
    public function testProviderSelection(string $source, string $weatherbitKey, string $owmKey, ?string $expected): void
    {
        $store = new ArrayAdapter();
        $locations = new LocationProvider(new NullLogger(), $store);
        $locations->setLocation('u', new Location('1', '2', '3'));

        $normalized = new Forecast(city: 'x', lat: 0.0, lon: 0.0, asl: '0', country: 'BE', timezone: 'UTC', utcOffsetHours: 0.0, sunrise: 0, sunset: 0, rows: []);
        $weatherbit = $this->createMock(Weatherbit::class);
        $weatherbit->expects('weatherbit' === $expected ? $this->once() : $this->never())->method('fetchForecast')->willReturn($normalized);
        $owm = $this->createMock(OpenWeatherMap::class);
        $owm->expects('openweathermap' === $expected ? $this->once() : $this->never())->method('fetchForecast')->willReturn($normalized);

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ('error' === $level) {
                    $this->errors[] = (string) $message;
                }
            }
        };

        $provider = new WeatherProvider($locations, $weatherbit, $owm, $logger, $store, new LoxoneCsvRenderer(), $source, $weatherbitKey, $owmKey);
        $provider->updateCache();

        if (null === $expected) {
            $this->assertNull($provider->getCSV('u'));
            $this->assertCount(1, $logger->errors);
        } else {
            $this->assertNotNull($provider->getCSV('u'));
            $this->assertSame([], $logger->errors);
        }
    }

    public function testInvalidSourceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid WEATHER_PROVIDER "darksky"');

        new WeatherProvider(
            new LocationProvider(new NullLogger(), new ArrayAdapter()),
            $this->createStub(Weatherbit::class),
            $this->createStub(OpenWeatherMap::class),
            new NullLogger(),
            new ArrayAdapter(),
            new LoxoneCsvRenderer(),
            'darksky',
        );
    }

    /**
     * Releases up to v0.2.x stored arrays under 'weather'. After an upgrade
     * that entry is a miss (not a crash) and the next refresh replaces it.
     */
    public function testWeatherStoredByOlderReleasesIsAMiss(): void
    {
        $store = new ArrayAdapter();
        $store->save($store->getItem('weather')->set(['u' => ['normalized' => ['city' => 'old', 'forecasts' => []], 'current' => null, 'forecast' => null]]));
        $locations = new LocationProvider(new NullLogger(), $store);
        $locations->setLocation('u', new Location('1', '2', '3'));

        $weatherbit = $this->createStub(Weatherbit::class);
        $weatherbit->method('fetchForecast')->willReturn(new Forecast(city: 'new', lat: 0.0, lon: 0.0, asl: '3', country: 'BE', timezone: 'UTC', utcOffsetHours: 0.0, sunrise: 0, sunset: 0, rows: []));
        $provider = new WeatherProvider($locations, $weatherbit, $this->createStub(OpenWeatherMap::class), new NullLogger(), $store, new LoxoneCsvRenderer(), 'auto', 'key', '');

        $this->assertNull($provider->getCSV('u'));

        $provider->updateCache();

        $this->assertStringContainsString(';new;', (string) $provider->getCSV('u'));
    }
}
