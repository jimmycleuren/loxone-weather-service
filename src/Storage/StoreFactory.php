<?php

declare(strict_types=1);

namespace App\Storage;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Persistent store for user locations and fetched forecasts.
 *
 * Files under var/data by default; Redis when REDIS_URL is set. The empty
 * namespace keeps the keys ('users', 'weather') identical to releases that
 * built their own RedisAdapter, so an upgrade keeps the existing data.
 */
final class StoreFactory
{
    public static function create(string $redisUrl, string $directory): CacheItemPoolInterface
    {
        if ('' !== $redisUrl) {
            return new RedisAdapter(RedisAdapter::createConnection($redisUrl));
        }

        return new FilesystemAdapter('', 0, $directory);
    }
}
