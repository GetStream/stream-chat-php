<?php

namespace GetStream\Unit;

use DateTime;
use Firebase\JWT\JWT;
use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function setUp():void
    {
        $this->client = new Client('key', 'secret');
    }

    public function testClientSetProtocol()
    {
        $client = new Client('key', 'secret');
        $client->setProtocol('asdfg');
        $url = $client->buildRequestUrl('x');
        $this->assertSame('asdfg://chat-proxy-us-east.stream-io-api.com/x', $url);
    }

    public function testClientHostnames()
    {
        $client = new Client('key', 'secret');
        $client->setLocation('qa');
        $url = $client->buildRequestUrl('x');
        $this->assertSame('https://chat-proxy-qa.stream-io-api.com/x', $url);

        $client = new Client('key', 'secret', $api_version = '1234', $location = 'asdfg');
        $url = $client->buildRequestUrl('y');
        $this->assertSame('https://chat-proxy-asdfg.stream-io-api.com/y', $url);

        $client = new Client('key', 'secret');
        $client->setLocation('us-east');
        $url = $client->buildRequestUrl('z');
        $this->assertSame('https://chat-proxy-us-east.stream-io-api.com/z', $url);
    }

    public function testEnvironmentVariable()
    {
        // Arrange
        $previous = getenv('STREAM_BASE_URL');
        putenv('STREAM_BASE_URL=test.stream-api.com/api');
        $client = new Client('key', 'secret');

        // Act
        $baseUrl = $client->getBaseUrl();

        // Assert
        $this->assertSame('test.stream-api.com/api', $baseUrl);

        // Teardown
        if ($previous === false) {
            // Remove the environment variable.
            putenv('STREAM_BASE_URL');
        } else {
            putenv('STREAM_BASE_URL='.$previous);
        }
    }

    public function testCreateToken()
    {
        $token = $this->client->createToken("tommaso");
        $payload = (array)JWT::decode($token, 'secret', ['HS256']);
        $this->assertTrue(in_array("tommaso", $payload));
        $this->assertSame("tommaso", $payload['user_id']);
        $expires = (new DateTime())->getTimestamp() + 3600;
        $token = $this->client->createToken("tommaso", $expires);
        $payload = (array)JWT::decode($token, 'secret', ['HS256']);
        $this->assertTrue(array_key_exists("exp", $payload));
        $this->assertSame($payload['exp'], $expires);
    }

    public function testCreateTokenExpiration()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $expires = new DateTime();
        $token = $this->client->createToken("tommaso", $expires);
    }
}
