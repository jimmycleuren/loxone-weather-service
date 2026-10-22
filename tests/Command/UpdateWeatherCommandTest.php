<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateWeatherCommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:update-weather');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    private function testerWith(WeatherProvider $weatherProvider): CommandTester
    {
        $kernel = static::bootKernel();
        // the test container may replace private services
        static::getContainer()->set(WeatherProvider::class, $weatherProvider);

        return new CommandTester(new Application($kernel)->find('app:update-weather'));
    }

    public function testFailure(): void
    {
        $weatherProvider = $this->createStub(WeatherProvider::class);
        $weatherProvider->method('updateCache')->willThrowException(new \RuntimeException());

        $tester = $this->testerWith($weatherProvider);
        $tester->execute([]);

        $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
    }

    public function testEveryRefreshesRepeatedly(): void
    {
        $weatherProvider = $this->createMock(WeatherProvider::class);
        $weatherProvider->expects($this->exactly(3))->method('updateCache');

        $tester = $this->testerWith($weatherProvider);
        $tester->execute(['--every' => '0', '--iterations' => '3']);

        $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testEveryKeepsRunningAfterAFailedRefresh(): void
    {
        $calls = 0;
        $weatherProvider = $this->createMock(WeatherProvider::class);
        $weatherProvider->expects($this->exactly(2))->method('updateCache')->willReturnCallback(function () use (&$calls): void {
            if (1 === ++$calls) {
                throw new \RuntimeException('provider down');
            }
        });

        $tester = $this->testerWith($weatherProvider);
        $tester->execute(['--every' => '0', '--iterations' => '2']);

        $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('provider down', $tester->getDisplay());
    }

    public function testEveryRejectsNonNumericInterval(): void
    {
        $weatherProvider = $this->createMock(WeatherProvider::class);
        $weatherProvider->expects($this->never())->method('updateCache');

        $tester = $this->testerWith($weatherProvider);
        $tester->execute(['--every' => 'often']);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
    }
}
