<?php

declare(strict_types=0);

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

    public function testClientHostnameWhenNoEnvVarAvailable()
    {
        $client = new Client('key', 'secret');
        $url = $client->buildRequestUrl('x');
        $this->assertSame('https://chat.stream-io-api.com/x', $url);
    }

    public function testBaseUrlEnvironmentVariables()
    {
        // Arrange
        $original = getenv('STREAM_BASE_URL');
        putenv('STREAM_BASE_URL=test.stream-api.com/api');
        $client = new Client('key', 'secret');

        // Act
        $baseUrl = $client->getBaseUrl();

        // Assert
        $this->assertSame('test.stream-api.com/api', $baseUrl);

        // Teardown
        if ($original === false) {
            // Remove the environment variable.
            putenv('STREAM_BASE_URL');
        } else {
            putenv('STREAM_BASE_URL='.$original);
        }
    }

    public function testCreateTokenWithNoExpiration()
    {
        $token = $this->client->createToken("tommaso");

        $payload = (array)JWT::decode($token, 'secret', ['HS256']);
        $this->assertTrue(in_array("tommaso", $payload));
        $this->assertSame("tommaso", $payload['user_id']);
    }

    public function testCreateTokenWithExpiration()
    {
        $expires = (new DateTime())->getTimestamp() + 3600;

        $token = $this->client->createToken("tommaso", $expires);

        $payload = (array)JWT::decode($token, 'secret', ['HS256']);
        $this->assertTrue(array_key_exists("exp", $payload));
        $this->assertSame($payload['exp'], $expires);
    }
}
