<?php

namespace GetStream\Integration;

use DateTime;
use DateTimeZone;
use Firebase\JWT\JWT;
use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;


class IntegrationTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        $this->client = new Client(
            getenv('STREAM_API_KEY'),
            getenv('STREAM_API_SECRET'),
            'v1.0',
            getenv('STREAM_REGION')
        );
        $this->client->setLocation('qa');
        $this->client->timeout = 10000;
    }

    /**
     * @expectedException \GetStream\StreamChat\StreamException
     */
    public function testAuth()
    {
        $this->client = new Client("bad", "guy");
        $this->client->getAppSettings();
    }

}
