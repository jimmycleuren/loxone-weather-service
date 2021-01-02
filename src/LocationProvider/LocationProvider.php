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

            $data = json_decode(file_get_contents($this->dataFile));

            return array_keys($data);
        }

        return [];
    }

    public function getCoordinates($user)
    {
        $lat = $_SERVER['LAT'];
        $lon = $_SERVER['LON'];
        $asl = $_SERVER['ASL'];

        if (file_exists("/tmp/coords.json")) {
            $this->logger->info("Reading location file");

            $data = json_decode(file_get_contents($this->dataFile));


            $lat = $data->lat;
            $lon = $data->lon;
            $asl = $data->asl;
        }

        return [$lat, $lon, $asl];
    }

    public function setCoordinates($user, $lat, $lon, $asl)
    {
        $this->logger->info("Saving location for $user: lat=$lat, lon=$lon, asl=$asl");
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile));
        } else {
            $data = [];
        }
        $data[$user] = ['lat' => $lat, 'lon' => $lon, 'asl' => $asl];

        file_put_contents($this->dataFile, json_encode($data));
    }
}