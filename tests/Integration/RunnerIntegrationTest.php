<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Capture\CaptureStore;
use Stromcom\HttpSmoke\Definition\Suite;
use Stromcom\HttpSmoke\Execution\CaseTranslator;
use Stromcom\HttpSmoke\Execution\Runner;
use Stromcom\HttpSmoke\Http\Curl\CurlMultiClient;
use Stromcom\HttpSmoke\Variable\Source\ArraySource;
use Stromcom\HttpSmoke\Variable\VariableResolver;

final class RunnerIntegrationTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        $stateFile = sys_get_temp_dir() . '/smoke_fail_once_state';
        if (is_file($stateFile)) {
            @unlink($stateFile);
        }

        self::$port = self::findFreePort();
        $cmd = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            self::$port,
            escapeshellarg(__DIR__ . '/fixtures/server.php'),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
            2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            self::fail('Failed to start built-in server');
        }
        self::$server = $proc;

        for ($i = 0; $i < 50; $i++) {
            $sock = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if (is_resource($sock)) {
                fclose($sock);
                return;
            }
            usleep(100_000);
        }
        self::fail('Built-in server did not become ready');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testEndToEnd(): void
    {
        $suite = new Suite();
        $suite->group('it', maxFailures: 5)
            ->baseUrl('{BASE}')

            ->get('/ping')
                ->expectStatus(200)
                ->expectJsonPath('status', 'ok')
                ->expectJsonHasKeys(['pong'])

            ->get('/items/42/')
                ->expectStatus(200)
                ->expectJsonPath('data.id', '42')

            ->get('/missing')
                ->expectStatus(404);

        $report = $this->buildRunner()->run($suite->getGroups(), $suite->getCasesByGroup());

        self::assertSame(3, $report->getTotalCount());
        self::assertSame(3, $report->getPassedCount(), implode("\n", array_map(
            static fn($r): string => $r->case->describe() . ': ' . implode('; ', $r->failures),
            $report->getAllResults(),
        )));
        self::assertSame(0, $report->getFailedCount());
        self::assertTrue($report->isSuccessful());
    }

    public function testSessionChainSharesCookies(): void
    {
        $suite = new Suite();
        $suite->group('sess')
            ->baseUrl('{BASE}')
            ->session('flow')
                ->post('/create', [])
                    ->expectStatus(201)
                    ->captureJsonPath('newId', 'data.id')
                ->get('/items/{@newId}/')
                    ->expectStatus(200)
                    ->expectJsonPath('data.id', 'new-id-42');

        $report = $this->buildRunner()->run($suite->getGroups(), $suite->getCasesByGroup());

        self::assertTrue($report->isSuccessful(), implode("\n", array_map(
            static fn($r): string => implode('; ', $r->failures),
            $report->getAllResults(),
        )));
    }

    public function testRetryOn5xxRecovers(): void
    {
        $stateFile = sys_get_temp_dir() . '/smoke_fail_once_state';
        @unlink($stateFile);

        $suite = new Suite();
        $suite->group('retry')
            ->baseUrl('{BASE}')
            ->get('/fail-once')
                ->retryOn5xx(1)
                ->expectStatus(200);

        $report = $this->buildRunner()->run($suite->getGroups(), $suite->getCasesByGroup());

        self::assertTrue($report->isSuccessful());
        self::assertSame(2, $report->getAllResults()[0]->attempts);
    }

    private function buildRunner(): Runner
    {
        $resolver = new VariableResolver();
        $resolver->addSource(new ArraySource(['BASE' => 'http://127.0.0.1:' . self::$port]));
        $store = new CaptureStore();

        return new Runner(
            new CurlMultiClient(concurrency: 5),
            new CaseTranslator($resolver, $store),
            $store,
        );
    }

    private static function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($sock)) {
            self::fail('Could not bind socket');
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        if ($name === false) {
            self::fail('Could not get socket name');
        }
        $parts = explode(':', $name);

        return (int) end($parts);
    }
}
