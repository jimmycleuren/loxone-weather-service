<?php

namespace App\Tests\LocationProvider;

use App\LocationProvider\LocationProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LocationProviderTest extends TestCase
{
    public function testLocation()
    {
        @unlink("/tmp/coords.json");

        $locationProvider = new LocationProvider(new NullLogger());

        list($lat, $lon, $asl) = $locationProvider->getCoordinates();

        $this->assertEquals("50.0000", $lat);
        $this->assertEquals("5.0000", $lon);
        $this->assertEquals("100", $asl);

        $locationProvider->setCoordinates("60.0000", "6.0000", "200");

        list($lat, $lon, $asl) = $locationProvider->getCoordinates();

        $this->assertEquals("60.0000", $lat);
        $this->assertEquals("6.0000", $lon);
        $this->assertEquals("200", $asl);
    }
}
