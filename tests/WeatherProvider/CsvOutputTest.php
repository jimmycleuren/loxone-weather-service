<?php

namespace App\Tests\WeatherProvider;

use App\Forecast\Forecast;
use App\Forecast\ForecastRow;
use App\Forecast\WeatherIcon;
use App\LocationProvider\LocationProvider;
use App\Loxone\LoxoneCsvRenderer;
use App\WeatherProvider\OpenWeatherMap;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Locks down the exact CSV the Loxone Miniserver receives.
 *
 * The golden file was generated from a real OpenWeatherMap response and
 * verified byte-for-byte against the production service's output for the
 * same data. Regenerate deliberately with UPDATE_GOLDEN=1 and review the diff.
 */
class CsvOutputTest extends TestCase
{
    private const string GOLDEN = __DIR__.'/../fixtures/openweathermap/expected.csv';
    private const string GOLDEN_WEATHERBIT = __DIR__.'/../fixtures/weatherbit/expected.csv';

    private string $tzBackup;
    private ArrayAdapter $store;

    protected function setUp(): void
    {
        $this->tzBackup = date_default_timezone_get();
        date_default_timezone_set('Europe/Brussels');
        $this->store = new ArrayAdapter();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tzBackup);
    }

    private function locations(): LocationProvider
    {
        return new LocationProvider(new NullLogger(), $this->store);
    }

    private function provider(LocationProvider $locations, ?Weatherbit $weatherbit = null, ?OpenWeatherMap $owm = null, string $weatherbitKey = 'key', string $owmKey = 'key'): WeatherProvider
    {
        return new WeatherProvider(
            $locations,
            $weatherbit ?? $this->createStub(Weatherbit::class),
            $owm ?? new OpenWeatherMap('key', OpenWeatherMapTest::client()),
            new NullLogger(),
            $this->store,
            new LoxoneCsvRenderer(),
            'auto',
            $weatherbitKey,
            $owmKey,
        );
    }

    /**
     * @param list<ForecastRow>    $rows
     * @param array<string, mixed> $overrides named Forecast arguments
     */
    private function forecast(array $rows, array $overrides = []): Forecast
    {
        return new Forecast(...array_merge([
            'city' => 'Testville', 'lat' => 50.0, 'lon' => 5.0, 'asl' => '100', 'country' => 'BE',
            'timezone' => 'Europe/Brussels', 'utcOffsetHours' => 2.0, 'sunrise' => 1, 'sunset' => 2,
            'rows' => $rows,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides named ForecastRow arguments
     */
    private function row(array $overrides = []): ForecastRow
    {
        return new ForecastRow(...array_merge([
            'timestamp' => 1792612800, 'temperature' => 9.35, 'apparentTemperature' => 6.39,
            'windSpeed' => 6.07, 'windDirection' => 227.0, 'windGustSpeed' => 12.03,
            'cloudsLow' => 1.0, 'cloudsMid' => 1.0, 'cloudsHigh' => 1.0,
            'precipitationIntensity' => 0.0, 'precipitationProbability' => 0.0, 'snow' => 0.0,
            'pressure' => 1011.0, 'humidity' => 81.0, 'solarRadiation' => 0.0, 'icon' => WeatherIcon::Clear,
        ], $overrides));
    }

    private function csvRows(string $csv): array
    {
        preg_match('#<station>\n(.*)\n</station>#s', $csv, $m);

        return array_map(fn ($l) => explode(';', $l), explode("\n", $m[1]));
    }

    public function testOpenWeatherMapCsvMatchesGoldenFile(): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('golden', 50, 5, '100');
        $provider = $this->provider($locations, weatherbitKey: '');
        $provider->updateCache();
        $csv = $provider->getCSV('golden');

        if (getenv('UPDATE_GOLDEN')) {
            file_put_contents(self::GOLDEN, $csv);
        }

        $this->assertSame(file_get_contents(self::GOLDEN), $csv);
    }

    /**
     * Same lock for Weatherbit: daily rows, string weather codes, pop/precip
     * fallbacks, an unmapped code (701) and a DST change in the forecast.
     */
    public function testWeatherbitCsvMatchesGoldenFile(): void
    {
        $fixtures = __DIR__.'/../fixtures/weatherbit/';
        $client = new MockHttpClient(fn (string $method, string $url) => new MockResponse(
            (string) file_get_contents($fixtures.(str_contains($url, '/current') ? 'current.json' : 'daily.json'))
        ));

        $locations = $this->locations();
        $locations->setCoordinates('golden', 50, 5, '100');
        $provider = $this->provider($locations, new Weatherbit('key', $client), owmKey: '');
        $provider->updateCache();
        $csv = $provider->getCSV('golden');

        if (getenv('UPDATE_GOLDEN')) {
            file_put_contents(self::GOLDEN_WEATHERBIT, $csv);
        }

        $this->assertSame(file_get_contents(self::GOLDEN_WEATHERBIT), $csv);
    }

    public function testCsvStructure(): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('golden', 50, 5, '100');
        $provider = $this->provider($locations, weatherbitKey: '');
        $provider->updateCache();
        $csv = $provider->getCSV('golden');

        $this->assertStringStartsWith("<mb_metadata>\n", $csv);
        $this->assertStringEndsWith("</station>\n", $csv);
        $this->assertStringContainsString("<valid_until>2100-01-01</valid_until>\n", $csv);

        $rows = $this->csvRows($csv);
        $header = array_shift($rows);
        $this->assertCount(11, $header);
        $this->assertSame(['', 'Gedinne', '5', '50', '100', 'BE', 'Europe/Brussels', 'UTC+2'], array_slice($header, 0, 8));

        $this->assertCount(57, $rows);
        foreach ($rows as $i => $row) {
            $this->assertCount(19, $row, "row $i");
            $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{4}$/', $row[0], "row $i date");
            $this->assertMatchesRegularExpression('/^\d{2}$/', $row[2], "row $i hour");
            $this->assertNotSame('0', $row[17], "row $i: unmapped weather icon");
        }
    }

    public function testUsersAreKeptApart(): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('north', 60, 6, 10);
        $locations->setCoordinates('south', 40, 4, 20);

        $weatherbit = $this->createStub(Weatherbit::class);
        $weatherbit->method('fetchForecast')->willReturnMap([
            [60, 6, 10, $this->forecast([$this->row()], ['city' => 'North'])],
            [40, 4, 20, $this->forecast([$this->row()], ['city' => 'South'])],
        ]);

        $provider = $this->provider($locations, $weatherbit);
        $provider->updateCache();

        $this->assertSame('North', $this->csvRows($provider->getCSV('north'))[0][1]);
        $this->assertSame('South', $this->csvRows($provider->getCSV('south'))[0][1]);
        $this->assertNull($provider->getCSV('unknown'));
    }

    public function testWeatherbitIsPreferredOverOpenWeatherMap(): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('u', 50, 5, 100);

        $weatherbit = $this->createMock(Weatherbit::class);
        $weatherbit->expects($this->once())->method('fetchForecast')->willReturn($this->forecast([]));
        $owm = $this->createMock(OpenWeatherMap::class);
        $owm->expects($this->never())->method('fetchForecast');

        $this->provider($locations, $weatherbit, $owm)->updateCache();
    }

    public function testNoProviderKeyStoresNoData(): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('u', 50, 5, 100);
        $provider = $this->provider($locations, null, new OpenWeatherMap('key', new MockHttpClient()), '', '');
        $provider->updateCache();

        $this->assertNull($provider->getCSV('u'));
    }

    #[DataProvider('timezoneProvider')]
    public function testUtcOffsetFormatting($diff, string $expected): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->createStub(Weatherbit::class);
        $weatherbit->method('fetchForecast')->willReturn($this->forecast([$this->row()], ['utcOffsetHours' => (float) $diff]));
        $provider = $this->provider($locations, $weatherbit);
        $provider->updateCache();

        $this->assertSame($expected, $this->csvRows($provider->getCSV('u'))[0][7]);
    }

    public static function timezoneProvider(): array
    {
        return [[2, 'UTC+2'], [1, 'UTC+1'], [0, 'UTC0'], [-5, 'UTC-5'], [5.5, 'UTC+5.5']];
    }

    #[DataProvider('snowProvider')]
    public function testSnowValue(float $snow, string $expected): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->createStub(Weatherbit::class);
        $weatherbit->method('fetchForecast')->willReturn($this->forecast([$this->row(['snow' => $snow])]));
        $provider = $this->provider($locations, $weatherbit);
        $provider->updateCache();

        $this->assertSame($expected, $this->csvRows($provider->getCSV('u'))[1][13]);
    }

    public static function snowProvider(): array
    {
        return [
            'none' => [0.0, '0.0'],
            'one decimal, rounded half to even' => [1.25, '1.2'],
            'half' => [0.5, '0.5'],
        ];
    }

    #[DataProvider('iconProvider')]
    public function testPictoCodes(string $icon, string $expected): void
    {
        $locations = $this->locations();
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->createStub(Weatherbit::class);
        $weatherbit->method('fetchForecast')->willReturn($this->forecast([$this->row(['icon' => WeatherIcon::from($icon)])]));
        $provider = $this->provider($locations, $weatherbit);
        $provider->updateCache();

        $this->assertSame($expected, $this->csvRows($provider->getCSV('u'))[1][17]);
    }

    public static function iconProvider(): array
    {
        return [
            ['CLEAR', '1'], ['CLOUDS_LIGHT', '2'], ['CLOUDS_MEDIUM', '7'], ['CLOUDS_HEAVY', '19'], ['CLOUDY', '22'],
            ['FOG', '16'], ['RAIN_LIGHT', '31'], ['RAIN_MEDIUM', '23'], ['RAIN_HEAVY', '25'],
            ['STORM_MEDIUM', '28'], ['STORM_HEAVY', '27'],
            ['SNOW_LIGHT', '32'], ['SNOW_MEDIUM', '24'], ['SNOW_HEAVY', '26'],
        ];
    }
}
