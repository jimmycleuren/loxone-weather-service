<?php

declare(strict_types=1);

namespace App\Tests\Location;

use App\Location\Location;
use App\Location\LocationProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class LocationProviderTest extends TestCase
{
    public function testStoresALocationPerUser(): void
    {
        $locations = new LocationProvider(new NullLogger(), new ArrayAdapter());

        $this->assertNull($locations->getLocation('test'));
        $this->assertSame([], $locations->getUsers());

        $locations->setLocation('test', new Location('60.0000', '6.0000', '200'));
        $locations->setLocation('other', new Location('-33.9', '18.4', '0'));

        $this->assertEquals(new Location('60.0000', '6.0000', '200'), $locations->getLocation('test'));
        $this->assertEquals(new Location('-33.9', '18.4', '0'), $locations->getLocation('other'));
        $this->assertSame(['test', 'other'], $locations->getUsers());
    }

    public function testNumericUserNamesStayStrings(): void
    {
        $locations = new LocationProvider(new NullLogger(), new ArrayAdapter());
        $locations->setLocation('504F94A00001', new Location('50', '5', '100'));
        $locations->setLocation('12345', new Location('50', '5', '100'));

        $this->assertSame(['504F94A00001', '12345'], $locations->getUsers());
        $this->assertNotNull($locations->getLocation('12345'));
    }

    /**
     * The storage format is the one of earlier releases, so locations saved
     * before an upgrade keep working.
     */
    public function testReadsLocationsStoredByOlderReleases(): void
    {
        $store = new ArrayAdapter();
        $store->save($store->getItem('users')->set([
            'old' => ['lat' => '51.5678', 'lon' => '4.1234', 'asl' => '42'],
            'ints' => ['lat' => 50, 'lon' => 5, 'asl' => 100],
            // releases without validation stored /forecast/ requests without coord
            'broken' => ['lat' => null, 'lon' => '', 'asl' => null],
        ]));
        $locations = new LocationProvider(new NullLogger(), $store);

        $this->assertEquals(new Location('51.5678', '4.1234', '42'), $locations->getLocation('old'));
        $this->assertEquals(new Location('50', '5', '100'), $locations->getLocation('ints'));
        $this->assertNull($locations->getLocation('broken'));
    }

    public function testWritesTheFormatOfEarlierReleases(): void
    {
        $store = new ArrayAdapter();
        new LocationProvider(new NullLogger(), $store)->setLocation('u', new Location('51.5678', '4.1234', '42'));

        $this->assertSame(['u' => ['lat' => '51.5678', 'lon' => '4.1234', 'asl' => '42']], $store->getItem('users')->get());
    }
}
