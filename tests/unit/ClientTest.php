<?php

namespace GetStream\Unit;

use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testClientSetProtocol()
    {
        $client = new Client('key', 'secret');
        $client->setProtocol('asdfg');
        $url = $client->buildRequestUrl('x');
        $this->assertSame('asdfg://chat-us-east-1.stream-io-api.com/x', $url);
    }

    public function testClientHostnames()
    {
        $client = new Client('key', 'secret');
        $client->setLocation('qa');
        $url = $client->buildRequestUrl('x');
        $this->assertSame('https://chat-us-east-1.stream-io-api.com/x', $url);

        $client = new Client('key', 'secret', $api_version = '1234', $location = 'asdfg');
        $url = $client->buildRequestUrl('y');
        $this->assertSame('https://chat-us-east-1.stream-io-api.com/y', $url);

        $client = new Client('key', 'secret');
        $client->setLocation('us-east');
        $url = $client->buildRequestUrl('z');
        $this->assertSame('https://chat-us-east-1.stream-io-api.com/z', $url);
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

}
