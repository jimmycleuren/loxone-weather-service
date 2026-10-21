<?php

namespace App\Tests\WeatherProvider;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\OpenWeatherMap;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Locks down the exact CSV the Loxone Miniserver receives.
 *
 * The golden file was generated from a real OpenWeatherMap response and
 * verified byte-for-byte against the production service's output for the
 * same data. Regenerate deliberately with UPDATE_GOLDEN=1 and review the diff.
 */
class CsvOutputTest extends TestCase
{
    use ProphecyTrait;

    private const GOLDEN = __DIR__.'/../fixtures/openweathermap/expected.csv';

    private array $serverBackup;
    private string $tzBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->tzBackup = date_default_timezone_get();
        date_default_timezone_set('Europe/Brussels');
        (new RedisAdapter(RedisAdapter::createConnection($_SERVER['REDIS_URL'])))->clear();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        date_default_timezone_set($this->tzBackup);
    }

    private function provider(LocationProvider $locations, ?Weatherbit $weatherbit = null, ?OpenWeatherMap $owm = null): WeatherProvider
    {
        return new WeatherProvider(
            $locations,
            $weatherbit ?? $this->prophesize(Weatherbit::class)->reveal(),
            $owm ?? new OpenWeatherMap('key', OpenWeatherMapTest::client()),
            new NullLogger()
        );
    }

    private static function normalized(array $forecasts, array $overrides = []): array
    {
        return ['normalized' => array_merge([
            'city' => 'Testville', 'lat' => 50, 'lon' => 5, 'asl' => 100, 'country' => 'BE',
            'timezone' => 'Europe/Brussels', 'timezonediff' => 2, 'sunrise' => 1, 'sunset' => 2,
            'forecasts' => $forecasts,
        ], $overrides)];
    }

    private static function forecastRow(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => 1792612800, 'temperature' => 9.35, 'apparent_temperature' => 6.39,
            'wind_speed' => 6.07, 'wind_direction' => 227, 'wind_gust_speed' => 12.03,
            'clouds_low' => 1, 'clouds_mid' => 1, 'clouds_high' => 1,
            'precipation_intensity' => 0, 'precipation_probability' => 0, 'snow' => 0,
            'pressure' => 1011, 'humidity' => 81, 'solar_radiation' => 0, 'icon' => 'CLEAR',
        ], $overrides);
    }

    private static function csvRows(string $csv): array
    {
        preg_match('#<station>\n(.*)\n</station>#s', $csv, $m);

        return array_map(fn ($l) => explode(';', $l), explode("\n", $m[1]));
    }

    public function testOpenWeatherMapCsvMatchesGoldenFile()
    {
        $_SERVER['WEATHERBIT_KEY'] = '';
        $_SERVER['OPENWEATHERMAP_KEY'] = 'key';

        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('golden', 50, 5, '100');
        $provider = $this->provider($locations);
        $provider->updateCache();
        $csv = $provider->getCSV('golden');

        if (getenv('UPDATE_GOLDEN')) {
            file_put_contents(self::GOLDEN, $csv);
        }

        $this->assertSame(file_get_contents(self::GOLDEN), $csv);
    }

    public function testCsvStructure()
    {
        $_SERVER['WEATHERBIT_KEY'] = '';
        $_SERVER['OPENWEATHERMAP_KEY'] = 'key';

        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('golden', 50, 5, '100');
        $provider = $this->provider($locations);
        $provider->updateCache();
        $csv = $provider->getCSV('golden');

        $this->assertStringStartsWith("<mb_metadata>\n", $csv);
        $this->assertStringEndsWith("</station>\n", $csv);
        $this->assertStringContainsString("<valid_until>2100-01-01</valid_until>\n", $csv);

        $rows = self::csvRows($csv);
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

    public function testUsersAreKeptApart()
    {
        $_SERVER['WEATHERBIT_KEY'] = 'key';

        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('north', 60, 6, 10);
        $locations->setCoordinates('south', 40, 4, 20);

        $weatherbit = $this->prophesize(Weatherbit::class);
        $weatherbit->getNormalizedData(60, 6, 10)->willReturn(self::normalized([self::forecastRow()], ['city' => 'North']));
        $weatherbit->getNormalizedData(40, 4, 20)->willReturn(self::normalized([self::forecastRow()], ['city' => 'South']));

        $provider = $this->provider($locations, $weatherbit->reveal());
        $provider->updateCache();

        $this->assertSame('North', self::csvRows($provider->getCSV('north'))[0][1]);
        $this->assertSame('South', self::csvRows($provider->getCSV('south'))[0][1]);
        $this->assertNull($provider->getCSV('unknown'));
    }

    public function testWeatherbitIsPreferredOverOpenWeatherMap()
    {
        $_SERVER['WEATHERBIT_KEY'] = 'key';
        $_SERVER['OPENWEATHERMAP_KEY'] = 'key';

        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('u', 50, 5, 100);

        $weatherbit = $this->prophesize(Weatherbit::class);
        $weatherbit->getNormalizedData(Argument::cetera())->willReturn(self::normalized([]))->shouldBeCalledTimes(1);
        $owm = $this->prophesize(OpenWeatherMap::class);
        $owm->getNormalizedData(Argument::cetera())->shouldNotBeCalled();

        $this->provider($locations, $weatherbit->reveal(), $owm->reveal())->updateCache();
    }

    public function testNoProviderKeyStoresNoData()
    {
        $_SERVER['WEATHERBIT_KEY'] = '';
        $_SERVER['OPENWEATHERMAP_KEY'] = '';

        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('u', 50, 5, 100);
        $provider = $this->provider($locations, null, new OpenWeatherMap('key', new MockHttpClient()));
        $provider->updateCache();

        $this->assertNull($provider->getCSV('u'));
    }

    /**
     * @dataProvider timezoneProvider
     */
    public function testUtcOffsetFormatting($diff, string $expected)
    {
        $_SERVER['WEATHERBIT_KEY'] = 'key';
        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->prophesize(Weatherbit::class);
        $weatherbit->getNormalizedData(Argument::cetera())->willReturn(self::normalized([self::forecastRow()], ['timezonediff' => $diff]));
        $provider = $this->provider($locations, $weatherbit->reveal());
        $provider->updateCache();

        $this->assertSame($expected, self::csvRows($provider->getCSV('u'))[0][7]);
    }

    public static function timezoneProvider(): array
    {
        return [[2, 'UTC+2'], [1, 'UTC+1'], [0, 'UTC0'], [-5, 'UTC-5'], [5.5, 'UTC+5.5']];
    }

    /**
     * @dataProvider snowProvider
     */
    public function testSnowValue($snow, string $expected)
    {
        $_SERVER['WEATHERBIT_KEY'] = 'key';
        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->prophesize(Weatherbit::class);
        $weatherbit->getNormalizedData(Argument::cetera())->willReturn(self::normalized([self::forecastRow(['snow' => $snow])]));
        $provider = $this->provider($locations, $weatherbit->reveal());
        $provider->updateCache();

        $this->assertSame($expected, self::csvRows($provider->getCSV('u'))[1][13]);
    }

    public static function snowProvider(): array
    {
        return [
            'none' => [0, '0.0'],
            'daily float' => [1.25, '1.2'],
            'hourly object (OpenWeatherMap)' => [(object) ['1h' => 0.5], '0.5'],
        ];
    }

    /**
     * @dataProvider iconProvider
     */
    public function testPictoCodes(string $icon, string $expected)
    {
        $_SERVER['WEATHERBIT_KEY'] = 'key';
        $locations = new LocationProvider(new NullLogger());
        $locations->setCoordinates('u', 50, 5, 100);
        $weatherbit = $this->prophesize(Weatherbit::class);
        $weatherbit->getNormalizedData(Argument::cetera())->willReturn(self::normalized([self::forecastRow(['icon' => $icon])]));
        $provider = $this->provider($locations, $weatherbit->reveal());
        $provider->updateCache();

        $this->assertSame($expected, self::csvRows($provider->getCSV('u'))[1][17]);
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
