<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealth()
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
