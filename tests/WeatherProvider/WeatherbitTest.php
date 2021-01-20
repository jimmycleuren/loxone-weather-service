<?php

namespace App\Tests\LocationProvider;

use App\WeatherProvider\Weatherbit;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WeatherbitTest extends TestCase
{
    public function testUpdate()
    {
        $client = $this->prophesize(MockHttpClient::class);
        $response = new MockResponse(json_encode([
            'data' => [
                [
                    'city_name' => 'test',
                    'lat' => 0,
                    'lon' => 0,
                    'country_code' => 'BE',
                    'timezone' => 'CET',
                    'sunrise' => '0',
                    'sunset' => '0',
                    'ts' => 123456789,
                    'temp' => 30,
                    'app_temp' => 30,
                    'wind_dir' => 0,
                    'wind_spd' => 0,
                    'clouds' => 0,
                    'precip' => 0,
                    'snow' => 0,
                    'pres' => 0,
                    'rh' => 0,
                    'solar_rad' => 0,
                    'weather' => [
                        'code' => 900
                    ]
                ]
            ]
        ]));
        $response = MockResponse::fromRequest("GET", "url", [], $response);
        $client->request(Argument::any(), Argument::any())->willReturn($response)->shouldBeCalledTimes(2);

        $weatherbit = new Weatherbit("test", $client->reveal());

        $this->assertIsArray($weatherbit->getNormalizedData(0, 0, 0));
    }
}
