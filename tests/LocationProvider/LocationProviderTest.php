<?php

namespace App\Tests\LocationProvider;

use App\LocationProvider\LocationProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class LocationProviderTest extends TestCase
{
    public function testLocation()
    {
        $client = RedisAdapter::createConnection($_SERVER['REDIS_URL']);
        $this->cache = new RedisAdapter($client);
        $this->cache->clear();

        $locationProvider = new LocationProvider(new NullLogger());

        list($lat, $lon, $asl) = $locationProvider->getCoordinates("test");

        $this->assertEquals("50.0000", $lat);
        $this->assertEquals("5.0000", $lon);
        $this->assertEquals("100", $asl);

        $locationProvider->setCoordinates("test", "60.0000", "6.0000", "200");

        list($lat, $lon, $asl) = $locationProvider->getCoordinates("test");

        $this->assertEquals("60.0000", $lat);
        $this->assertEquals("6.0000", $lon);
        $this->assertEquals("200", $asl);

        $this->assertEquals(["test"], $locationProvider->getUsers());
    }
}
