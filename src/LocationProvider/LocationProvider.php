<?php

namespace App\LocationProvider;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class LocationProvider
{
    /**
     * @param array{string, string, string} $defaultLocation lat, lon, asl for users without a stored location
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $store,
        private readonly array $defaultLocation = ['', '', ''],
    ) {
    }

    public function getUsers()
    {
        $item = $this->store->getItem('users');

        if (!$item->isHit()) {
            return [];
        }

        return array_keys($item->get());
    }

    public function getCoordinates($user)
    {
        $item = $this->store->getItem('users');

        if ($item->isHit()) {
            $data = $item->get();

            if (isset($data[$user])) {
                return [$data[$user]['lat'], $data[$user]['lon'], $data[$user]['asl']];
            }
        }

        return $this->defaultLocation;
    }

    public function setCoordinates($user, $lat, $lon, $asl)
    {
        $this->logger->info("Saving location for $user: lat=$lat, lon=$lon, asl=$asl");

        $item = $this->store->getItem('users');

        if ($item->isHit()) {
            $data = $item->get();
        } else {
            $data = [];
        }

        $data[$user] = ['lat' => $lat, 'lon' => $lon, 'asl' => $asl];

        $item->set($data);

        $this->store->save($item);
    }
}
