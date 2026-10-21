<?php

namespace App\Tests\Command;

use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Prophecy\PhpUnit\ProphecyTrait;

class UpdateWeatherCommandTest extends KernelTestCase
{
    use ProphecyTrait;

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

    private function testerWith($weatherProvider): CommandTester
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $kernel->boot();
        $kernel->getContainer()->set('App\WeatherProvider\WeatherProvider', $weatherProvider);

        return new CommandTester($application->find('app:update-weather'));
    }

    public function testEveryRefreshesRepeatedly()
    {
        $weatherProvider = $this->prophesize(WeatherProvider::class);
        $weatherProvider->updateCache()->shouldBeCalledTimes(3);

        $tester = $this->testerWith($weatherProvider->reveal());
        $tester->execute(['--every' => '0', '--iterations' => '3']);

        $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testEveryKeepsRunningAfterAFailedRefresh()
    {
        $calls = 0;
        $weatherProvider = $this->prophesize(WeatherProvider::class);
        $weatherProvider->updateCache()->will(function () use (&$calls) {
            if (1 === ++$calls) {
                throw new \RuntimeException('provider down');
            }
        })->shouldBeCalledTimes(2);

        $tester = $this->testerWith($weatherProvider->reveal());
        $tester->execute(['--every' => '0', '--iterations' => '2']);

        $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('provider down', $tester->getDisplay());
    }

    public function testEveryRejectsNonNumericInterval()
    {
        $weatherProvider = $this->prophesize(WeatherProvider::class);
        $weatherProvider->updateCache()->shouldNotBeCalled();

        $tester = $this->testerWith($weatherProvider->reveal());
        $tester->execute(['--every' => 'often']);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
    }
}
