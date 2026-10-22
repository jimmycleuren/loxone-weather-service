<?php

declare(strict_types=1);

namespace App\Location;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * The last location each Miniserver (user) reported.
 *
 * Stored under 'users' as [user => ['lat' => ..., 'lon' => ..., 'asl' => ...]],
 * the format of earlier releases, so stored locations survive upgrades.
 */
class LocationProvider
{
    private const string KEY = 'users';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $store,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getUsers(): array
    {
        // numeric user names come back as int array keys
        return array_map(strval(...), array_keys($this->all()));
    }

    /**
     * Null when the user never reported a location, or when the stored one is
     * not valid (e.g. saved by a release that did not validate input).
     */
    public function getLocation(string $user): ?Location
    {
        $stored = $this->all()[$user] ?? null;
        if (!\is_array($stored)) {
            return null;
        }

        try {
            return new Location(
                $this->string($stored['lat'] ?? null),
                $this->string($stored['lon'] ?? null),
                $this->string($stored['asl'] ?? null),
            );
        } catch (InvalidLocation $e) {
            $this->logger->warning("Ignoring stored location for $user: {$e->getMessage()}");

            return null;
        }
    }

    public function setLocation(string $user, Location $location): void
    {
        $this->logger->info("Saving location for $user: lat=$location->latitude, lon=$location->longitude, asl=$location->altitude");

        $item = $this->store->getItem(self::KEY);
        $data = $this->all();
        $data[$user] = ['lat' => $location->latitude, 'lon' => $location->longitude, 'asl' => $location->altitude];

        $this->store->save($item->set($data));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function all(): array
    {
        $item = $this->store->getItem(self::KEY);
        $data = $item->isHit() ? $item->get() : [];

        return \is_array($data) ? $data : [];
    }

    private function string(mixed $value): string
    {
        return \is_int($value) || \is_float($value) || \is_string($value) ? (string) $value : '';
    }
}
