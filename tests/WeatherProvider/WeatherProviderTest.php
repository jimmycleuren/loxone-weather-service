<?php

namespace App\Tests\LocationProvider;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WeatherProviderTest extends TestCase
{
    public function testGetCsv()
    {
        @unlink('/tmp/weather.json');

        $locationProvider = new LocationProvider(new NullLogger());
        $weatherProvider = new WeatherProvider($locationProvider);

        $this->assertEquals(null, $weatherProvider->getCSV());

        file_put_contents('/tmp/weather.json', '{"normalized":{"city":"Rosendal","lat":60,"lon":6,"asl":"200","country":"NO","timezone":"Europe\/Oslo","timezonediff":1,"sunrise":"08:11","sunset":"14:40","forecasts":[{"timestamp":1606759500,"temperature":5.6,"apparent_temperature":2.5,"wind_direction":97,"wind_speed":4.1,"wind_gust_speed":0,"clouds_low":88,"clouds_mid":88,"clouds_high":88,"precipation_intensity":0,"precipation_probability":0,"snow":0,"pressure":1005.9,"humidity":89,"solar_radiation":0,"icon":"CLOUDY"}]}}');

        $this->assertStringContainsString('station', $weatherProvider->getCSV());
    }
}
