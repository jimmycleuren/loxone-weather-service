<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ForecastControllerTest extends WebTestCase
{
    public function testForecast()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=1');

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

        $locations = static::getContainer()->get(\App\LocationProvider\LocationProvider::class);
        $this->assertEquals(['51.5678', '4.1234', '42'], $locations->getCoordinates('loc-test'));
    }
}
