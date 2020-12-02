<?php

namespace App\Command;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\Weatherbit;
use App\WeatherProvider\WeatherProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateWeatherCommand extends Command
{
    protected static $defaultName = 'app:update-weather';

    private $weatherProvider;
    private $logger;

    public function __construct(WeatherProvider $weatherProvider, LoggerInterface $logger, string $name = null)
    {
        $this->weatherProvider = $weatherProvider;
        $this->logger = $logger;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Update the weather forecast from the configured providers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->weatherProvider->updateCache();

            $io->success('Weather successfully retrieved');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
