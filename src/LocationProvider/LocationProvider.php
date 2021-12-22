<?php

namespace App\LocationProvider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class LocationProvider
{
    private $logger;
    private $cache;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $client = RedisAdapter::createConnection($_SERVER['REDIS_URL']);
        $this->cache = new RedisAdapter($client);
    }

    public function getUsers()
    {
        $item = $this->cache->getItem('users');

        if (!$item->isHit()) {
            return [];
        }

        return array_keys($item->get());
    }

    public function getCoordinates($user)
    {
        $item = $this->cache->getItem('users');

        if ($item->isHit()) {
            $data = $item->get();

            if (isset($data[$user])) {
                return [$data[$user]['lat'], $data[$user]['lon'], $data[$user]['asl']];
            }
        }

        return [$_SERVER['LAT'], $_SERVER['LON'], $_SERVER['ASL']];
    }

    public function setCoordinates($user, $lat, $lon, $asl)
    {
        $this->logger->info("Saving location for $user: lat=$lat, lon=$lon, asl=$asl");

        $item = $this->cache->getItem('users');

        if ($item->isHit()) {
            $data = $item->get();
        } else {
            $data = [];
        }

        $data[$user] = ['lat' => $lat, 'lon' => $lon, 'asl' => $asl];

        $item->set($data);

        $this->cache->save($item);
    }
}