<?php

declare(strict_types=0);

namespace GetStream\Unit;

use GetStream\StreamChat\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class QueryChannelsOptionsTest extends TestCase
{
    private function queryBody(?array $options): array
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"channels": []}')]));
        $stack->push(Middleware::history($history));
        $client = new Client("key", "secret");
        $client->setHttpClient(new GuzzleClient(["handler" => $stack, "base_uri" => "https://chat.stream-io-api.com"]));

        $client->queryChannels(["type" => "messaging"], null, $options);

        return json_decode((string) $history[0]["request"]->getBody(), true);
    }

    public function testDefaultsWhenNoOptions()
    {
        $body = $this->queryBody(null);
        $this->assertTrue($body["state"]);
        $this->assertFalse($body["watch"]);
        $this->assertFalse($body["presence"]);
    }

    public function testExplicitStateFalseIsRespected()
    {
        $body = $this->queryBody(["state" => false]);
        $this->assertFalse($body["state"]);
        $this->assertFalse($body["watch"]);
        $this->assertFalse($body["presence"]);
    }

    public function testExplicitWatchAndPresenceTrueAreRespected()
    {
        $body = $this->queryBody(["watch" => true, "presence" => true]);
        $this->assertTrue($body["state"]);
        $this->assertTrue($body["watch"]);
        $this->assertTrue($body["presence"]);
    }
}
