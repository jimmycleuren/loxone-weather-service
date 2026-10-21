<?php

namespace App\Command;

use App\WeatherProvider\WeatherProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:update-weather', description: 'Update the weather forecast from the configured providers')]
class UpdateWeatherCommand extends Command
{
    private $weatherProvider;
    private $logger;

    public function __construct(WeatherProvider $weatherProvider, LoggerInterface $logger, ?string $name = null)
    {
        $this->weatherProvider = $weatherProvider;
        $this->logger = $logger;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('every', null, InputOption::VALUE_REQUIRED, 'Keep running and refresh every N seconds (used by the Docker image instead of cron, so the container environment is inherited)')
            ->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Stop after N refreshes (with --every; default: run forever)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $every = $input->getOption('every');
        if (null === $every) {
            return $this->update($io);
        }
        if (!ctype_digit((string) $every)) {
            $io->error('--every must be a number of seconds');

            return Command::INVALID;
        }

        $iterations = $input->getOption('iterations');
        $remaining = null === $iterations ? null : (int) $iterations;
        $status = Command::SUCCESS;

        while (null === $remaining || $remaining-- > 0) {
            // A failed refresh must not stop the loop: the next one may succeed.
            $status = $this->update($io);
            if (0 !== $remaining) {
                $this->sleep((int) $every);
            }
        }

        return $status;
    }

    private function update(SymfonyStyle $io): int
    {
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

    protected function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
