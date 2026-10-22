<?php

namespace App\Tests\WeatherProvider;

use App\Forecast\WeatherCode;
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
        $dir = __DIR__.'/../fixtures/openweathermap';

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

    private function fixture(): object
    {
        return json_decode(file_get_contents(__DIR__.'/../fixtures/openweathermap/onecall.json'));
    }

    public function testRequestsUseKeyCoordinatesAndMetricUnits(): void
    {
        $requested = [];
        $owm = new OpenWeatherMap('secret', self::client($requested));
        $owm->fetchForecast(50, 5, 100);

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
        $n = new OpenWeatherMap('key', self::client())->fetchForecast(50, 5, 100);

        $this->assertSame('Gedinne', $n->city);
        $this->assertSame('BE', $n->country);
        $this->assertEquals(50, $n->lat);
        $this->assertEquals(5, $n->lon);
        $this->assertSame('100', $n->asl);
        $this->assertSame('Europe/Brussels', $n->timezone);
        $this->assertEquals(2, $n->utcOffsetHours);
        $this->assertSame($this->fixture()->current->sunrise, $n->sunrise);
        $this->assertSame($this->fixture()->current->sunset, $n->sunset);
    }

    public function testForecastRowsAreCurrentPlusHourlyPlusDaily(): void
    {
        $fixture = $this->fixture();
        $forecasts = new OpenWeatherMap('key', self::client())->fetchForecast(50, 5, 100)->rows;

        $this->assertCount(1 + count($fixture->hourly) + count($fixture->daily), $forecasts);
        $this->assertSame($fixture->current->dt, $forecasts[0]->timestamp);
        $this->assertSame($fixture->hourly[0]->dt, $forecasts[1]->timestamp);
        $this->assertSame(end($fixture->daily)->dt, end($forecasts)->timestamp);
    }

    public function testHourlyRowMapping(): void
    {
        $fixture = $this->fixture();
        $hour = $fixture->hourly[0];
        $row = new OpenWeatherMap('key', self::client())->fetchForecast(50, 5, 100)->rows[1];

        $this->assertEquals($hour->temp, $row->temperature);
        $this->assertEquals($hour->feels_like, $row->apparentTemperature);
        $this->assertEquals($hour->wind_deg, $row->windDirection);
        $this->assertEquals($hour->wind_speed, $row->windSpeed);
        $this->assertEquals($hour->wind_gust, $row->windGustSpeed);
        $this->assertEquals($hour->clouds, $row->cloudsLow);
        $this->assertEquals($hour->clouds, $row->cloudsMid);
        $this->assertEquals($hour->clouds, $row->cloudsHigh);
        $this->assertEquals($hour->pressure, $row->pressure);
        $this->assertEquals($hour->humidity, $row->humidity);
        $this->assertEquals($hour->uvi, $row->solarRadiation);
        $this->assertEquals($hour->pop, $row->precipitationProbability);
        $this->assertSame('CLEAR', $row->icon?->value); // weather id 800
    }

    public function testHourlyRainUsesOneHourValue(): void
    {
        $fixture = $this->fixture();
        $forecasts = new OpenWeatherMap('key', self::client())->fetchForecast(50, 5, 100)->rows;

        $checked = 0;
        foreach ($fixture->hourly as $i => $hour) {
            $expected = isset($hour->rain) ? $hour->rain->{'1h'} : 0;
            $this->assertEquals($expected, $forecasts[$i + 1]->precipitationIntensity, "hourly[$i]");
            $checked += isset($hour->rain) ? 1 : 0;
        }
        $this->assertGreaterThan(0, $checked, 'fixture should contain hourly rain');
    }

    public function testDailyRowsUseDayValuesAndFlatRain(): void
    {
        $fixture = $this->fixture();
        $forecasts = new OpenWeatherMap('key', self::client())->fetchForecast(50, 5, 100)->rows;
        $offset = 1 + count($fixture->hourly);

        foreach ($fixture->daily as $i => $day) {
            $row = $forecasts[$offset + $i];
            $this->assertEquals($day->temp->day, $row->temperature, "daily[$i]");
            $this->assertEquals($day->feels_like->day, $row->apparentTemperature, "daily[$i]");
            $this->assertEquals($day->rain ?? 0, $row->precipitationIntensity, "daily[$i]");
        }
    }

    public function testMissingOptionalFieldsFallBack(): void
    {
        $onecall = $this->fixture();
        $onecall->hourly = [(object) [
            'dt' => 1, 'temp' => 1, 'feels_like' => 1, 'pressure' => 1000, 'humidity' => 50,
            'uvi' => 0, 'clouds' => 40, 'wind_speed' => 7, 'wind_deg' => 90,
            'weather' => [(object) ['id' => 601]],
            // no wind_gust, pop, rain, snow
        ]];
        $onecall->daily = [];
        $client = new MockHttpClient(fn (string $m, string $url) => new MockResponse(
            str_contains($url, 'onecall') ? json_encode($onecall) : file_get_contents(__DIR__.'/../fixtures/openweathermap/weather.json')
        ));

        $row = new OpenWeatherMap('key', $client)->fetchForecast(50, 5, 100)->rows[1];

        $this->assertEquals(7, $row->windGustSpeed);
        $this->assertSame(0.0, $row->precipitationProbability);
        $this->assertSame(0.0, $row->precipitationIntensity);
        $this->assertSame(0.0, $row->snow);
        $this->assertSame('SNOW_MEDIUM', $row->icon?->value);
    }

    public function testHourlySnowUsesOneHourValue(): void
    {
        $onecall = $this->fixture();
        $hour = clone $onecall->hourly[0];
        $hour->snow = (object) ['1h' => 0.5];
        $hour->rain = (object) ['3h' => 9]; // no 1h value: 0, not a crash
        $onecall->hourly = [$hour];
        $onecall->daily = [];
        $client = new MockHttpClient(fn (string $m, string $url) => new MockResponse(
            str_contains($url, 'onecall') ? json_encode($onecall) : file_get_contents(__DIR__.'/../fixtures/openweathermap/weather.json')
        ));

        $row = new OpenWeatherMap('key', $client)->fetchForecast(50, 5, 100)->rows[1];

        $this->assertSame(0.5, $row->snow);
        $this->assertSame(0.0, $row->precipitationIntensity);
    }

    #[DataProvider('codeProvider')]
    public function testWeatherCodeMapping(int $code, ?string $expected): void
    {
        $this->assertSame($expected, WeatherCode::toIcon($code)?->value);
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
