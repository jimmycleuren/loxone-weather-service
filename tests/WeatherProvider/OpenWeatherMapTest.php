<?php

namespace App\Tests\WeatherProvider;

use App\WeatherProvider\OpenWeatherMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Characterization tests for the OpenWeatherMap provider, based on a real
 * One Call 3.0 response (tests/fixtures/openweathermap/onecall.json).
 */
class OpenWeatherMapTest extends TestCase
{
    public static function client(array &$requested = []): MockHttpClient
    {
        $dir = dirname(__DIR__).'/fixtures/openweathermap';

        return new MockHttpClient(function (string $method, string $url) use ($dir, &$requested) {
            $requested[] = $url;
            if (str_contains($url, '/data/2.5/weather')) {
                return new MockResponse(file_get_contents($dir.'/weather.json'));
            }
            if (str_contains($url, '/data/3.0/onecall')) {
                return new MockResponse(file_get_contents($dir.'/onecall.json'));
            }

            return new MockResponse('', ['http_code' => 404]);
        });
    }

    private static function fixture(): object
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/fixtures/openweathermap/onecall.json'));
    }

    public function testRequestsUseKeyCoordinatesAndMetricUnits(): void
    {
        $requested = [];
        $owm = new OpenWeatherMap('secret', self::client($requested));
        $owm->getNormalizedData(50, 5, 100);

        $this->assertCount(2, $requested);
        foreach ($requested as $url) {
            $this->assertStringContainsString('appid=secret', $url);
            $this->assertStringContainsString('lat=50', $url);
            $this->assertStringContainsString('lon=5', $url);
            $this->assertStringContainsString('units=metric', $url);
        }
    }

    public function testNormalizedMetadata(): void
    {
        $data = (new OpenWeatherMap('key', self::client()))->getNormalizedData(50, 5, 100);
        $n = $data['normalized'];

        $this->assertSame('Gedinne', $n['city']);
        $this->assertSame('BE', $n['country']);
        $this->assertEquals(50, $n['lat']);
        $this->assertEquals(5, $n['lon']);
        $this->assertSame(100, $n['asl']);
        $this->assertSame('Europe/Brussels', $n['timezone']);
        $this->assertEquals(2, $n['timezonediff']);
        $this->assertSame(self::fixture()->current->sunrise, $n['sunrise']);
        $this->assertSame(self::fixture()->current->sunset, $n['sunset']);
        $this->assertNull($data['current']);
    }

    public function testForecastRowsAreCurrentPlusHourlyPlusDaily(): void
    {
        $fixture = self::fixture();
        $forecasts = (new OpenWeatherMap('key', self::client()))->getNormalizedData(50, 5, 100)['normalized']['forecasts'];

        $this->assertCount(1 + count($fixture->hourly) + count($fixture->daily), $forecasts);
        $this->assertSame($fixture->current->dt, $forecasts[0]['timestamp']);
        $this->assertSame($fixture->hourly[0]->dt, $forecasts[1]['timestamp']);
        $this->assertSame(end($fixture->daily)->dt, end($forecasts)['timestamp']);
    }

    public function testHourlyRowMapping(): void
    {
        $fixture = self::fixture();
        $hour = $fixture->hourly[0];
        $row = (new OpenWeatherMap('key', self::client()))->getNormalizedData(50, 5, 100)['normalized']['forecasts'][1];

        $this->assertEquals($hour->temp, $row['temperature']);
        $this->assertEquals($hour->feels_like, $row['apparent_temperature']);
        $this->assertEquals($hour->wind_deg, $row['wind_direction']);
        $this->assertEquals($hour->wind_speed, $row['wind_speed']);
        $this->assertEquals($hour->wind_gust, $row['wind_gust_speed']);
        $this->assertEquals($hour->clouds, $row['clouds_low']);
        $this->assertEquals($hour->clouds, $row['clouds_mid']);
        $this->assertEquals($hour->clouds, $row['clouds_high']);
        $this->assertEquals($hour->pressure, $row['pressure']);
        $this->assertEquals($hour->humidity, $row['humidity']);
        $this->assertEquals($hour->uvi, $row['solar_radiation']);
        $this->assertEquals($hour->pop, $row['precipation_probability']);
        $this->assertSame('CLEAR', $row['icon']); // weather id 800
    }

    public function testHourlyRainUsesOneHourValue(): void
    {
        $fixture = self::fixture();
        $forecasts = (new OpenWeatherMap('key', self::client()))->getNormalizedData(50, 5, 100)['normalized']['forecasts'];

        $checked = 0;
        foreach ($fixture->hourly as $i => $hour) {
            $expected = isset($hour->rain) ? $hour->rain->{'1h'} : 0;
            $this->assertEquals($expected, $forecasts[$i + 1]['precipation_intensity'], "hourly[$i]");
            $checked += isset($hour->rain) ? 1 : 0;
        }
        $this->assertGreaterThan(0, $checked, 'fixture should contain hourly rain');
    }

    public function testDailyRowsUseDayValuesAndFlatRain(): void
    {
        $fixture = self::fixture();
        $forecasts = (new OpenWeatherMap('key', self::client()))->getNormalizedData(50, 5, 100)['normalized']['forecasts'];
        $offset = 1 + count($fixture->hourly);

        foreach ($fixture->daily as $i => $day) {
            $row = $forecasts[$offset + $i];
            $this->assertEquals($day->temp->day, $row['temperature'], "daily[$i]");
            $this->assertEquals($day->feels_like->day, $row['apparent_temperature'], "daily[$i]");
            $this->assertEquals($day->rain ?? 0, $row['precipation_intensity'], "daily[$i]");
        }
    }

    public function testMissingOptionalFieldsFallBack(): void
    {
        $onecall = self::fixture();
        $onecall->hourly = [(object) [
            'dt' => 1, 'temp' => 1, 'feels_like' => 1, 'pressure' => 1000, 'humidity' => 50,
            'uvi' => 0, 'clouds' => 40, 'wind_speed' => 7, 'wind_deg' => 90,
            'weather' => [(object) ['id' => 601]],
            // no wind_gust, pop, rain, snow
        ]];
        $onecall->daily = [];
        $client = new MockHttpClient(fn (string $m, string $url) => new MockResponse(
            str_contains($url, 'onecall') ? json_encode($onecall) : file_get_contents(dirname(__DIR__).'/fixtures/openweathermap/weather.json')
        ));

        $row = (new OpenWeatherMap('key', $client))->getNormalizedData(50, 5, 100)['normalized']['forecasts'][1];

        $this->assertEquals(7, $row['wind_gust_speed']);
        $this->assertSame(0, $row['precipation_probability']);
        $this->assertSame(0, $row['precipation_intensity']);
        $this->assertSame(0, $row['snow']);
        $this->assertSame('SNOW_MEDIUM', $row['icon']);
    }

    #[DataProvider('codeProvider')]
    public function testWeatherCodeMapping(int $code, ?string $expected): void
    {
        $method = new \ReflectionMethod(OpenWeatherMap::class, 'codeToText');

        $this->assertSame($expected, $method->invoke(new OpenWeatherMap('key', new MockHttpClient()), $code));
    }

    public static function codeProvider(): array
    {
        return [
            [200, 'STORM_MEDIUM'], [202, 'STORM_HEAVY'], [232, 'STORM_HEAVY'],
            [300, 'RAIN_LIGHT'], [302, 'RAIN_MEDIUM'],
            [500, 'RAIN_LIGHT'], [501, 'RAIN_MEDIUM'], [502, 'RAIN_HEAVY'], [511, 'RAIN_MEDIUM'], [522, 'RAIN_HEAVY'],
            [600, 'SNOW_LIGHT'], [601, 'SNOW_MEDIUM'], [602, 'SNOW_HEAVY'], [611, 'SNOW_LIGHT'], [622, 'SNOW_HEAVY'],
            [701, null], [741, 'FOG'],
            [800, 'CLEAR'], [801, 'CLOUDS_LIGHT'], [802, 'CLOUDS_MEDIUM'], [803, 'CLOUDS_HEAVY'], [804, 'CLOUDY'],
            [900, 'RAIN_MEDIUM'], [999, null],
        ];
    }
}
