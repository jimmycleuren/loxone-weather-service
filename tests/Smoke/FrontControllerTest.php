<?php

namespace App\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Requests go through the real front controller (public/index.php + symfony/runtime)
 * on PHP's built-in web server. The kernel-based WebTestCases bypass the runtime, so
 * they cannot see PHP notices/deprecations that end up in the HTTP body, and the
 * Miniserver parses that body.
 */
class FrontControllerTest extends TestCase
{
    /** @var resource|null */
    private $server;
    private array $pipes = [];

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
    }

    private function startServer(string $env): string
    {
        $root = dirname(__DIR__, 2);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $vars = ['APP_ENV' => $env, 'REDIS_URL' => $_SERVER['REDIS_URL']] + getenv();

        $clear = proc_open([PHP_BINARY, $root.'/bin/console', 'cache:clear', '--env='.$env], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, $vars);
        $this->assertSame(0, proc_close($clear), "cache:clear --env=$env failed");

        $this->server = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $root.'/public', $root.'/public/index.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $this->pipes,
            $root,
            $vars
        );

        for ($i = 0; $i < 50; ++$i) {
            if ($conn = @fsockopen('127.0.0.1', (int) explode(':', $address)[1])) {
                fclose($conn);

                return 'http://'.$address;
            }
            usleep(100000);
        }
        $this->fail("built-in server did not start on $address");
    }

    /**
     * @dataProvider envProvider
     */
    public function testHealthBodyIsClean(string $env)
    {
        if ('dev' === $env && Kernel::VERSION_ID < 60400) {
            $this->markTestSkipped('symfony/runtime < 6.4 calls ini_set("assert.warning"), which PHP 8.3 deprecates; debug mode prints that into the body. Re-enabled automatically by the Symfony 6.4 upgrade.');
        }

        $base = $this->startServer($env);

        $this->assertSame('healty', file_get_contents($base.'/health'));
    }

    public static function envProvider(): array
    {
        return ['prod' => ['prod'], 'dev' => ['dev']];
    }
}
