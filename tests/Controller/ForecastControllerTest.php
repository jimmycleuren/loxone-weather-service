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

    public function testUnknownFormat()
    {
        $client = static::createClient();

        $client->request('GET', '/forecast/?coord=5.0000,50.0000&asl=100&format=2');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }
}
