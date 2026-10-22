<?php

declare(strict_types=1);

namespace App\Tests\WeatherProvider;

use App\Forecast\Forecast;
use App\Location\Location;
use App\WeatherProvider\Weatherbit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WeatherbitTest extends TestCase
{
    public function testUpdate(): void
    {
        $body = json_encode([
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
                        'code' => 900,
                    ],
                ],
            ],
        ]);
        $client = new MockHttpClient(fn () => new MockResponse($body));

        $weatherbit = new Weatherbit('test', $client);

        $data = $weatherbit->fetchForecast(new Location('0', '0', '0'));
        $this->assertInstanceOf(Forecast::class, $data);
        $this->assertSame(2, $client->getRequestsCount());

        $n = $data;
        $this->assertSame('test', $n->city);
        $this->assertSame('BE', $n->country);
        $this->assertSame('CET', $n->timezone);
        $this->assertCount(2, $n->rows); // current + 1 forecast row (same mock response)
        $row = $n->rows[0];
        $this->assertSame(123456789, $row->timestamp);
        $this->assertEquals(30, $row->temperature);
        $this->assertEquals(0, $row->windGustSpeed); // missing -> 0
        $this->assertEquals(0, $row->precipitationProbability); // no pop, no precip -> 0
        $this->assertSame('RAIN_MEDIUM', $row->icon?->value); // code 900
    }

    public function testDailyForecastFallbacksAndTimezone(): void
    {
        $current = ['data' => [[
            'city_name' => 'Genk', 'lat' => 50.9, 'lon' => 5.5, 'country_code' => 'BE', 'timezone' => 'Europe/Brussels',
            'sunrise' => '06:30', 'sunset' => '18:10', 'ts' => 1000, 'temp' => 12, 'app_temp' => 11,
            'wind_dir' => 180, 'wind_spd' => 3, 'wind_gust_spd' => 8, 'clouds' => 50, 'precip' => 0.4, 'snow' => 0,
            'pres' => 1012, 'rh' => 70, 'solar_rad' => 120, 'weather' => ['code' => 500],
        ]]];
        $daily = ['data' => [[
            'ts' => 2000, 'max_temp' => 15, 'app_max_temp' => 14, 'wind_dir' => 200, 'wind_spd' => 4,
            'clouds' => 20, 'clouds_low' => 10, 'clouds_mid' => 20, 'clouds_hi' => 30, 'precip' => 2, 'snow' => 1,
            'pop' => 60, 'pres' => 1008, 'rh' => 80, 'max_dhi' => 300, 'weather' => ['code' => 602],
            'timestamp_local' => '2026-10-21T02:00:00', 'timestamp_utc' => '2026-10-21T00:00:00',
        ]]];
        $client = new MockHttpClient(fn (string $m, string $url) => new MockResponse(json_encode(
            str_contains($url, '/current') ? $current : $daily
        )));

        $n = new Weatherbit('key', $client)->fetchForecast(new Location('50.9', '5.5', '60'));

        $this->assertSame('Genk', $n->city);
        $this->assertSame('60', $n->asl);
        $this->assertEquals(2, $n->utcOffsetHours);
        $current = $n->rows[0];
        $this->assertEquals(100, $current->precipitationProbability); // precip > 0, no pop
        $this->assertEquals(8, $current->windGustSpeed);
        $this->assertSame('RAIN_LIGHT', $current->icon?->value);
        $day = $n->rows[1];
        $this->assertEquals(15, $day->temperature);
        $this->assertEquals(14, $day->apparentTemperature);
        $this->assertEquals(10, $day->cloudsLow);
        $this->assertEquals(30, $day->cloudsHigh);
        $this->assertEquals(60, $day->precipitationProbability);
        $this->assertEquals(300, $day->solarRadiation);
        $this->assertSame('SNOW_HEAVY', $day->icon?->value);
    }

    public function testWeatherCodeAsStringIsMapped(): void
    {
        $row = [
            'city_name' => 'x', 'lat' => 0, 'lon' => 0, 'country_code' => 'BE', 'timezone' => 'CET', 'sunrise' => '0', 'sunset' => '0',
            'ts' => 1, 'temp' => 1, 'app_temp' => 1, 'wind_dir' => 0, 'wind_spd' => 0, 'clouds' => 0, 'precip' => 0, 'snow' => 0,
            'pres' => 0, 'rh' => 0, 'solar_rad' => 0, 'weather' => ['code' => '803'],
        ];
        $client = new MockHttpClient(fn () => new MockResponse(json_encode(['data' => [$row]])));

        $forecasts = new Weatherbit('key', $client)->fetchForecast(new Location('0', '0', '0'))->rows;

        $this->assertSame('CLOUDS_HEAVY', $forecasts[0]->icon?->value);
    }
}
