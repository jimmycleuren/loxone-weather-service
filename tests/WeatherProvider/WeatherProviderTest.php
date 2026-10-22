<?php

namespace App\Tests\WeatherProvider;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\OpenWeatherMap;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class WeatherProviderTest extends TestCase
{
    private RedisAdapter $cache;

    public function testGetCsv(): void
    {
        $client = RedisAdapter::createConnection($_SERVER['REDIS_URL']);
        $this->cache = new RedisAdapter($client);
        $this->cache->clear();

        $locationProvider = new LocationProvider(new NullLogger());
        $locationProvider->setCoordinates("test", 0, 0, 0);

        $weatherbit = $this->createMock(Weatherbit::class);
        $openweathermap = $this->createStub(OpenWeatherMap::class);
        $weatherbit->expects($this->once())->method('getNormalizedData')->with(0, 0, 0)->willReturn([
            'normalized' =>
                [
                    'city' => 'Genk',
                    'lon' => '0',
                    'lat' => '0',
                    'asl' => '0',
                    'country' => 'BE',
                    'timezone' => 'GMT+2',
                    'timezonediff' => '2',
                    'sunrise' => '0800',
                    'sunset' => '1700',
                    'forecasts' => []
                ]
        ]);

        $weatherProvider = new WeatherProvider($locationProvider, $weatherbit, $openweathermap, new NullLogger());

        $this->assertEquals(null, $weatherProvider->getCSV("test"));

        $weatherProvider->updateCache();


        $this->assertStringContainsString('station', $weatherProvider->getCSV("test"));
    }
}
