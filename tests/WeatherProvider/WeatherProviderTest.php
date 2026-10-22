<?php

namespace App\Tests\WeatherProvider;

use App\LocationProvider\LocationProvider;
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
        $locationProvider->setCoordinates('test', 0, 0, 0);

        $weatherbit = $this->createMock(Weatherbit::class);
        $openweathermap = $this->createStub(OpenWeatherMap::class);
        $weatherbit->expects($this->once())->method('getNormalizedData')->with(0, 0, 0)->willReturn([
            'normalized' => [
                'city' => 'Genk',
                'lon' => '0',
                'lat' => '0',
                'asl' => '0',
                'country' => 'BE',
                'timezone' => 'GMT+2',
                'timezonediff' => '2',
                'sunrise' => '0800',
                'sunset' => '1700',
                'forecasts' => [],
            ],
        ]);

        $weatherProvider = new WeatherProvider($locationProvider, $weatherbit, $openweathermap, new NullLogger(), $store, 'auto', 'key', 'key');

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
        $locations->setCoordinates('u', 1, 2, 3);

        $normalized = ['normalized' => ['city' => 'x', 'lon' => 0, 'lat' => 0, 'asl' => 0, 'country' => 'BE', 'timezone' => 'UTC', 'timezonediff' => 0, 'sunrise' => 0, 'sunset' => 0, 'forecasts' => []]];
        $weatherbit = $this->createMock(Weatherbit::class);
        $weatherbit->expects('weatherbit' === $expected ? $this->once() : $this->never())->method('getNormalizedData')->willReturn($normalized);
        $owm = $this->createMock(OpenWeatherMap::class);
        $owm->expects('openweathermap' === $expected ? $this->once() : $this->never())->method('getNormalizedData')->willReturn($normalized);

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

        $provider = new WeatherProvider($locations, $weatherbit, $owm, $logger, $store, $source, $weatherbitKey, $owmKey);
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
            'darksky',
        );
    }
}
