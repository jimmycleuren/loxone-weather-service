<?php

namespace App\LocationProvider;

use Psr\Log\LoggerInterface;

class LocationProvider
{
    private $logger;
    private $dataFile = "/tmp/locations.json";

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getUsers()
    {
        if (file_exists($this->dataFile)) {
            $this->logger->info("Reading location file");

            $data = json_decode(file_get_contents($this->dataFile), true);

            return array_keys($data);
        }

        return [];
    }

    public function getCoordinates($user)
    {
        if (file_exists($this->dataFile)) {
            $this->logger->info("Reading location file");

            $data = json_decode(file_get_contents($this->dataFile));

            if (isset($data->$user)) {
                return [$data->$user->lat, $data->$user->lon, $data->$user->asl];
            }
        }

        return [$_SERVER['LAT'], $_SERVER['LON'], $_SERVER['ASL']];
    }

    public function setCoordinates($user, $lat, $lon, $asl)
    {
        $this->logger->info("Saving location for $user: lat=$lat, lon=$lon, asl=$asl");
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
        } else {
            $data = [];
        }
        $data[$user] = ['lat' => $lat, 'lon' => $lon, 'asl' => $asl];

        file_put_contents($this->dataFile, json_encode($data));
    }
}