<?php

namespace App\Tests\LocationProvider;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\OpenWeatherMap;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Prophecy\PhpUnit\ProphecyTrait;

class WeatherProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testGetCsv()
    {
        $client = RedisAdapter::createConnection($_SERVER['REDIS_URL']);
        $this->cache = new RedisAdapter($client);
        $this->cache->clear();

        $locationProvider = new LocationProvider(new NullLogger());
        $locationProvider->setCoordinates("test", 0, 0, 0);

        $weatherbit = $this->prophesize(Weatherbit::class);
        $openweathermap = $this->prophesize(OpenWeatherMap::class);
        $weatherbit->getNormalizedData(0, 0, 0)->willReturn([
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
        ])->shouldBeCalledTimes(1);

        $weatherProvider = new WeatherProvider($locationProvider, $weatherbit->reveal(), $openweathermap->reveal(), new NullLogger());

        $this->assertEquals(null, $weatherProvider->getCSV("test"));

        $weatherProvider->updateCache();

        //file_put_contents('/tmp/weather.json', '{"test":{"normalized":{"city":"Rosendal","lat":60,"lon":6,"asl":"200","country":"NO","timezone":"Europe\/Oslo","timezonediff":1,"sunrise":"08:11","sunset":"14:40","forecasts":[{"timestamp":1606759500,"temperature":5.6,"apparent_temperature":2.5,"wind_direction":97,"wind_speed":4.1,"wind_gust_speed":0,"clouds_low":88,"clouds_mid":88,"clouds_high":88,"precipation_intensity":0,"precipation_probability":0,"snow":0,"pressure":1005.9,"humidity":89,"solar_radiation":0,"icon":"SNOW_HEAVY"}]}}}');

        $this->assertStringContainsString('station', $weatherProvider->getCSV("test"));
    }
}
