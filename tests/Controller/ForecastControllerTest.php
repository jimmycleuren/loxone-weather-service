<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Location\Location;
use App\Location\LocationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ForecastControllerTest extends WebTestCase
{
    public function testForecast()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=1');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testFormat2ReturnsSameAsFormat1()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=2');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testUnknownFormat()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=3');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testForecastBodyIsCleanMiniserverCsv()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=1&user=body-test');
        $body = $client->getResponse()->getContent();

        $this->assertStringStartsWith("<mb_metadata>\n", $body);
        $this->assertStringContainsString("<station>\n", $body);
        $this->assertStringEndsWith("</station>\n", $body);
        // PHP notices/deprecations rendered into the body break the Miniserver's parser
        $this->assertStringNotContainsString('<b>', $body);
        $this->assertStringNotContainsString('Deprecated', $body);
        $this->assertStringNotContainsString('Warning', $body);
    }

    public function testRequestStoresLocationPerUser()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=4.1234,51.5678&asl=42&format=1&user=loc-test');

        $locations = static::getContainer()->get(LocationProvider::class);
        $this->assertEquals(new Location('51.5678', '4.1234', '42'), $locations->getLocation('loc-test'));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidRequestProvider(): array
    {
        return [
            'no coord (used to store an empty location)' => ['/forecast/?asl=100&format=1', 400],
            'coord without comma' => ['/forecast/?coord=5.0000&asl=100&format=1', 400],
            'latitude out of range' => ['/forecast/?coord=5.0000,91.0000&asl=100&format=1', 400],
            'longitude out of range' => ['/forecast/?coord=181.0000,50.0000&asl=100&format=1', 400],
            'not a number' => ['/forecast/?coord=abc,50.0000&asl=100&format=1', 400],
            // coord ends up in the provider URL: no extra query parameters
            'query injection' => ['/forecast/?coord=5%26appid%3Dx,50.0000&asl=100&format=1', 400],
            'altitude not a number' => ['/forecast/?coord=5.0000,50.0000&asl=high&format=1', 400],
            'array parameter' => ['/forecast/?coord[]=5&asl=100&format=1', 400],
            'user too long' => ['/forecast/?coord=5.0000,50.0000&asl=100&format=1&user='.str_repeat('x', 129), 400],
            'no format' => ['/forecast/?coord=5.0000,50.0000&asl=100', 404],
        ];
    }

    #[DataProvider('invalidRequestProvider')]
    public function testInvalidRequestsAreRejectedWithoutSideEffects(string $url, int $status): void
    {
        $client = static::createClient();
        $locations = static::getContainer()->get(LocationProvider::class);
        $before = $locations->getUsers();

        $client->request('GET', $url);

        $this->assertSame($status, $client->getResponse()->getStatusCode());
        $this->assertSame($before, $locations->getUsers());
    }

    public function testUnknownFormatDoesNotStoreTheLocation(): void
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=3&user=format-3');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $this->assertNull(static::getContainer()->get(LocationProvider::class)->getLocation('format-3'));
    }

    public function testAltitudeIsOptional(): void
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&format=1&user=no-asl');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals(new Location('50.0000', '5.0000', '0'), static::getContainer()->get(LocationProvider::class)->getLocation('no-asl'));
    }

    public function testPostIsNotAllowed(): void
    {
        $client = static::createClient();

        $client->request('POST', '/forecast/?coord=5.0000,50.0000&asl=100&format=1');

        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }
}
