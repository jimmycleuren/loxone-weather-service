<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The service must boot with only the variables a user actually sets.
 */
class ConfigurationTest extends KernelTestCase
{
    private const array VARIABLES = ['WEATHER_PROVIDER', 'WEATHERBIT_KEY', 'OPENWEATHERMAP_KEY', 'REDIS_URL'];

    /** @var array<string, array{mixed, mixed, string|false}> */
    private array $backup = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->backup[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->backup as $name => [$server, $env, $getenv]) {
            if (null !== $server) {
                $_SERVER[$name] = $server;
            }
            if (null !== $env) {
                $_ENV[$name] = $env;
            }
            if (false !== $getenv) {
                putenv("$name=$getenv");
            }
        }
    }

    /**
     * #368: removing WEATHERBIT_KEY to use OpenWeatherMap gave
     * "Environment variable not found: WEATHERBIT_KEY".
     */
    public function testBootsWithoutAnyWeatherVariables(): void
    {
        self::bootKernel();

        $provider = self::getContainer()->get(WeatherProvider::class);

        $this->assertInstanceOf(WeatherProvider::class, $provider);
    }
}
