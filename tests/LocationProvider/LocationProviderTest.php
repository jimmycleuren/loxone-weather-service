<?php

namespace App\Tests\LocationProvider;

use App\LocationProvider\LocationProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class LocationProviderTest extends TestCase
{
    public function testLocation(): void
    {
        $locationProvider = new LocationProvider(new NullLogger(), new ArrayAdapter(), ['50.0000', '5.0000', '100']);

        [$lat, $lon, $asl] = $locationProvider->getCoordinates('test');

        $this->assertEquals('50.0000', $lat);
        $this->assertEquals('5.0000', $lon);
        $this->assertEquals('100', $asl);

        $locationProvider->setCoordinates('test', '60.0000', '6.0000', '200');

        [$lat, $lon, $asl] = $locationProvider->getCoordinates('test');

        $this->assertEquals('60.0000', $lat);
        $this->assertEquals('6.0000', $lon);
        $this->assertEquals('200', $asl);

        $this->assertEquals(['test'], $locationProvider->getUsers());
    }

    public function testWithoutDefaultLocationUnknownUsersGetEmptyCoordinates(): void
    {
        $locationProvider = new LocationProvider(new NullLogger(), new ArrayAdapter());

        $this->assertSame(['', '', ''], $locationProvider->getCoordinates('nobody'));
        $this->assertSame([], $locationProvider->getUsers());
    }
}
