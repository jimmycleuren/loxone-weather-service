<?php

declare(strict_types=1);

namespace App\Tests\Storage;

use App\Storage\StoreFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Filesystem\Filesystem;

class StoreFactoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/lws-store-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testFilesIsTheDefault(): void
    {
        $store = StoreFactory::create('', $this->directory);

        $this->assertInstanceOf(FilesystemAdapter::class, $store);
    }

    public function testFileStorePersistsAcrossInstances(): void
    {
        $writer = StoreFactory::create('', $this->directory);
        $writer->save($writer->getItem('users')->set(['u' => ['lat' => 1, 'lon' => 2, 'asl' => 3]]));

        // e.g. the refresh job writes, php-fpm reads
        $reader = StoreFactory::create('', $this->directory);

        $this->assertSame(['u' => ['lat' => 1, 'lon' => 2, 'asl' => 3]], $reader->getItem('users')->get());
        $this->assertDirectoryExists($this->directory);
    }

    public function testRedisWhenUrlIsSet(): void
    {
        $url = $this->redisUrl();

        $this->assertInstanceOf(RedisAdapter::class, StoreFactory::create($url, $this->directory));
        $this->assertDirectoryDoesNotExist($this->directory);
    }

    /**
     * Releases up to v0.2.x built `new RedisAdapter($client)` themselves. The
     * stored locations must survive the upgrade, so the keys must not change.
     */
    public function testRedisKeysAreCompatibleWithOlderReleases(): void
    {
        $url = $this->redisUrl();
        $legacy = new RedisAdapter(RedisAdapter::createConnection($url));
        $legacy->clear();
        $legacy->save($legacy->getItem('users')->set(['legacy' => ['lat' => 1, 'lon' => 2, 'asl' => 3]]));

        $store = StoreFactory::create($url, $this->directory);

        $this->assertTrue($store->getItem('users')->isHit());
        $this->assertSame(['legacy' => ['lat' => 1, 'lon' => 2, 'asl' => 3]], $store->getItem('users')->get());
        $legacy->clear();
    }

    private function redisUrl(): string
    {
        $url = $_SERVER['REDIS_URL'] ?? '';
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('REDIS_URL not set (CI runs this on the PHP 8.5 job)');
        }

        return $url;
    }
}
