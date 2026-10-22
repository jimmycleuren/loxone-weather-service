<?php

declare(strict_types=1);

namespace App\Tests\Location;

use App\Location\InvalidLocation;
use App\Location\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocationTest extends TestCase
{
    public function testFromQueryKeepsTheStringsTheMiniserverSends(): void
    {
        $location = Location::fromQuery('5.0000,50.0000', '100');

        $this->assertSame('50.0000', $location->latitude);
        $this->assertSame('5.0000', $location->longitude);
        $this->assertSame('100', $location->altitude);
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function validProvider(): array
    {
        return [
            'negative' => ['-70.5,-33.25', '-10'],
            'bounds' => ['180,90', '8848'],
            'other bounds' => ['-180,-90', '0'],
            'integers' => ['5,50', '100'],
            'missing altitude' => ['5,50', null],
            'empty altitude' => ['5,50', ''],
            'decimal altitude' => ['5,50', '100.5'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValid(mixed $coord, mixed $asl): void
    {
        $this->assertInstanceOf(Location::class, Location::fromQuery($coord, $asl));
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function invalidProvider(): array
    {
        return [
            'missing' => [null, '100'],
            'empty' => ['', '100'],
            'one value' => ['5', '100'],
            'three values' => ['5,50,1', '100'],
            'latitude > 90' => ['5,90.1', '100'],
            'longitude < -180' => ['-180.5,50', '100'],
            'exponent' => ['5e1,50', '100'],
            'hex' => ['0x5,50', '100'],
            'whitespace' => [' 5,50', '100'],
            'trailing dot' => ['5.,50', '100'],
            'query injection' => ['5&appid=x,50', '100'],
            'altitude text' => ['5,50', 'high'],
            'altitude not a string' => ['5,50', 100],
            'coord not a string' => [5, '100'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(mixed $coord, mixed $asl): void
    {
        $this->expectException(InvalidLocation::class);

        Location::fromQuery($coord, $asl);
    }
}
