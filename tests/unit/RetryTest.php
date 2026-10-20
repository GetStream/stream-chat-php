<?php

declare(strict_types=0);

namespace GetStream\Unit;

use GetStream\StreamChat\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use PHPUnit\Framework\TestCase;

class RetryTest extends TestCase
{
    /** @var string */
    private static $pemFile;

    /** @var resource|null */
    private $server;

    /** @var string */
    private $logFile;

    /** @var string|false */
    private $originalUrl;

    /** @var array the cURL errno of every attempt, 0 when it succeeded */
    private $attemptErrnos = [];

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => '127.0.0.1'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);
        self::$pemFile = tempnam(sys_get_temp_dir(), 'stream-tls-');
        file_put_contents(self::$pemFile, $certPem . $keyPem);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$pemFile);
    }

    protected function setUp(): void
    {
        $this->originalUrl = getenv('STREAM_CHAT_URL');
        $this->logFile = tempnam(sys_get_temp_dir(), 'stream-tls-log-');
    }

    protected function tearDown(): void
    {
        if ($this->server !== null) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        @unlink($this->logFile);
        if ($this->originalUrl === false) {
            putenv('STREAM_CHAT_URL');
        } else {
            putenv('STREAM_CHAT_URL=' . $this->originalUrl);
        }
    }

    /**
     * Starts the TLS server with one action per accepted connection and
     * returns a Stream client that talks to it with the default HTTP client.
     * @param string[] $actions
     */
    private function clientForServer(array $actions): Client
    {
        $cmd = [PHP_BINARY, __DIR__ . '/fixtures/tls_server.php', self::$pemFile, $this->logFile, implode(',', $actions)];
        $this->server = proc_open($cmd, [1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        $port = (int) trim(fgets($pipes[1]));
        $this->assertGreaterThan(0, $port, 'TLS server did not start');

        putenv('STREAM_CHAT_URL=https://127.0.0.1:' . $port);
        $client = new Client('key', 'secret');
        $client->setGuzzleDefaultOption('verify', false);
        $client->setGuzzleDefaultOption('on_stats', function (TransferStats $stats) {
            $this->attemptErrnos[] = $stats->getHandlerErrorData();
        });
        return $client;
    }

    /** @return array[] the requests the TLS server received, one per connection */
    private function serverLog(): array
    {
        $lines = array_filter(explode("\n", file_get_contents($this->logFile)));
        return array_map(function ($line) {
            return json_decode($line, true);
        }, array_values($lines));
    }

    public function testGetIsRetriedWhenConnectionDropsAfterRequestWasSent()
    {
        $client = $this->clientForServer(['drop', 'ok']);

        $response = $client->unreadCounts('june');

        $this->assertSame(200, $response->getStatusCode());
        $log = $this->serverLog();
        $this->assertCount(2, $log);
        $this->assertSame(['drop', 'ok'], array_column($log, 'action'));
        $this->assertSame(['GET', 'GET'], array_column($log, 'method'));
        $this->assertSame([56, 0], $this->attemptErrnos);
    }

    public function testPostIsNotRetriedWhenConnectionDropsAfterRequestWasSent()
    {
        $client = $this->clientForServer(['drop', 'ok']);

        try {
            $client->upsertUser(['id' => 'june']);
            $this->fail('expected the dropped connection to fail the request');
        } catch (RequestException $e) {
            // The same failure the customer reported: cURL error 56.
            $this->assertSame(56, $e->getHandlerContext()['errno'] ?? null, $e->getMessage());
        }

        $log = $this->serverLog();
        $this->assertCount(1, $log);
        $this->assertSame('POST', $log[0]['method']);
        $this->assertSame([56], $this->attemptErrnos);
    }

    public function testPostIsRetriedWhenTlsHandshakeFails()
    {
        $client = $this->clientForServer(['tlsfail', 'ok']);

        $response = $client->upsertUser(['id' => 'june', 'name' => 'June']);

        $this->assertSame(200, $response->getStatusCode());
        $log = $this->serverLog();
        $this->assertSame(['tlsfail', 'ok'], array_column($log, 'action'));
        $this->assertSame([35, 0], $this->attemptErrnos);
        $this->assertSame('POST', $log[1]['method']);
        $this->assertSame(
            ['users' => ['june' => ['id' => 'june', 'name' => 'June']]],
            json_decode($log[1]['body'], true)
        );
    }

    public function testServerErrorIsNotRetried()
    {
        $client = $this->clientForServer(['500', 'ok']);

        try {
            $client->unreadCounts('june');
            $this->fail('expected the 500 response to fail the request');
        } catch (ServerException $e) {
            $this->assertSame(500, $e->getResponse()->getStatusCode());
        }

        $this->assertCount(1, $this->serverLog());
    }

    public function testGetGivesUpAfterThreeAttempts()
    {
        $client = $this->clientForServer(['drop', 'drop', 'drop', 'ok']);

        try {
            $client->unreadCounts('june');
            $this->fail('expected the request to fail after three attempts');
        } catch (RequestException $e) {
            $this->assertSame(56, $e->getHandlerContext()['errno'] ?? null, $e->getMessage());
        }

        $this->assertSame(['drop', 'drop', 'drop'], array_column($this->serverLog(), 'action'));
        $this->assertSame([56, 56, 56], $this->attemptErrnos);
    }

    /**
     * Runs one request through a stack with only the retry middleware and returns
     * how many attempts reached the handler.
     */
    private function attempts(string $method, TransferException $error): int
    {
        $mock = new MockHandler([$error, $error, $error, new Response(200)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Client::retryMiddleware());
        $client = new GuzzleClient(['handler' => $stack]);

        try {
            $client->request($method, 'https://example.com/');
        } catch (TransferException $e) {
            // counted below
        }

        return 4 - $mock->count();
    }

    /** @param array<string, int> $context */
    private static function connectError(array $context): ConnectException
    {
        return new ConnectException('error', new Request('GET', 'https://example.com/'), null, $context);
    }

    /** @param array<string, int> $context */
    private static function requestError(array $context): RequestException
    {
        return new RequestException('error', new Request('GET', 'https://example.com/'), null, null, $context);
    }

    public function testConnectPhaseErrorsAreRetriedForAnyMethod()
    {
        foreach ([6, 7, 35] as $errno) {
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->assertSame(3, $this->attempts($method, self::connectError(['errno' => $errno])), "$method errno $errno");
            }
        }
    }

    public function testErrorsAfterSendAreRetriedOnlyForSafeMethods()
    {
        foreach ([52, 55, 56] as $errno) {
            foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
                $this->assertSame(3, $this->attempts($method, self::requestError(['errno' => $errno])), "$method errno $errno");
            }
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->assertSame(1, $this->attempts($method, self::requestError(['errno' => $errno])), "$method errno $errno");
            }
        }
    }

    public function testTimeoutsAndUnknownErrorsAreNotRetried()
    {
        $this->assertSame(1, $this->attempts('GET', self::connectError(['errno' => 28])));
        $this->assertSame(1, $this->attempts('GET', self::requestError(['errno' => 28])));
        $this->assertSame(1, $this->attempts('GET', self::requestError([])));
        $this->assertSame(1, $this->attempts('GET', self::connectError([])));
    }

    public function testHttpErrorResponsesAreNotRetried()
    {
        foreach ([429, 500, 502, 503] as $status) {
            $mock = new MockHandler([new Response($status), new Response(200)]);
            $stack = HandlerStack::create($mock);
            $stack->push(Client::retryMiddleware());
            $client = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);

            $this->assertSame($status, $client->request('GET', 'https://example.com/')->getStatusCode());
            $this->assertSame(1, $mock->count(), "status $status");
        }
    }
}
