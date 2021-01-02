<?php

namespace App\Tests\Command;

use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateWeatherCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:update-weather');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName()
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testFailure()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $kernel->boot();
        $weatherProvider = $this->prophesize(WeatherProvider::class);
        $weatherProvider->updateCache()->willThrow(new \RuntimeException());
        $kernel->getContainer()->set('App\WeatherProvider\WeatherProvider', $weatherProvider->reveal());

        $command = $application->find('app:update-weather');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName()
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
    }
}
