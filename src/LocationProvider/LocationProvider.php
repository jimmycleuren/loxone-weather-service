<?php

namespace App\LocationProvider;

use Psr\Log\LoggerInterface;

class LocationProvider
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getCoordinates()
    {
        $lat = $_SERVER['LAT'];
        $lon = $_SERVER['LON'];
        $asl = $_SERVER['ASL'];
        if (file_exists("/tmp/coords.json")) {
            $this->logger->info("Using saved coordinates");
            $data = json_decode(file_get_contents("/tmp/coords.json"));
            $lat = $data->lat;
            $lon = $data->lon;
            $asl = $data->asl;
        }

        return [$lat, $lon, $asl];
    }

    public function setCoordinates($lat, $lon, $asl)
    {
        $data = ['lat' => $lat, 'lon' => $lon, 'asl' => $asl];

        file_put_contents("/tmp/coords.json", json_encode($data));
    }
}