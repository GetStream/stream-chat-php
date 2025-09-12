<?php

declare(strict_types=0);

namespace GetStream\StreamChat\Tests\Unit;

use GetStream\StreamChat\Channel;
use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamResponse;
use PHPUnit\Framework\TestCase;

class MarkDeliveredTest extends TestCase
{
    private $client;
    private $channel;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->channel = new Channel($this->client, 'messaging', 'test_channel_id');
    }

    public function testMarkDeliveredBasic()
    {
        $expectedResponse = new StreamResponse(['status' => 'success'], $this->createMockResponse());
        
        $this->client->expects($this->once())
            ->method('post')
            ->with(
                'channels/delivered',
                ['user_id' => 'test_user_id']
            )
            ->willReturn($expectedResponse);

        $response = $this->channel->markDelivered('test_user_id');
        
        $this->assertInstanceOf(StreamResponse::class, $response);
        $this->assertEquals(['status' => 'success'], $response->getArrayCopy());
    }

    public function testMarkDeliveredWithData()
    {
        $data = [
            'channel_delivered_message' => [
                'messaging:test_channel_id' => 'test_message_id'
            ],
            'client_id' => 'test_client'
        ];

        $expectedPayload = array_merge($data, ['user_id' => 'test_user_id']);
        $expectedResponse = new StreamResponse(['status' => 'success'], $this->createMockResponse());
        
        $this->client->expects($this->once())
            ->method('post')
            ->with(
                'channels/delivered',
                $expectedPayload
            )
            ->willReturn($expectedResponse);

        $response = $this->channel->markDelivered('test_user_id', $data);
        
        $this->assertInstanceOf(StreamResponse::class, $response);
        $this->assertEquals(['status' => 'success'], $response->getArrayCopy());
    }

    public function testMarkDeliveredWithNullData()
    {
        $expectedResponse = new StreamResponse(['status' => 'success'], $this->createMockResponse());
        
        $this->client->expects($this->once())
            ->method('post')
            ->with(
                'channels/delivered',
                ['user_id' => 'test_user_id']
            )
            ->willReturn($expectedResponse);

        $response = $this->channel->markDelivered('test_user_id', null);
        
        $this->assertInstanceOf(StreamResponse::class, $response);
        $this->assertEquals(['status' => 'success'], $response->getArrayCopy());
    }

    public function testMarkDeliveredWithEmptyData()
    {
        $expectedResponse = new StreamResponse(['status' => 'success'], $this->createMockResponse());
        
        $this->client->expects($this->once())
            ->method('post')
            ->with(
                'channels/delivered',
                ['user_id' => 'test_user_id']
            )
            ->willReturn($expectedResponse);

        $response = $this->channel->markDelivered('test_user_id', []);
        
        $this->assertInstanceOf(StreamResponse::class, $response);
        $this->assertEquals(['status' => 'success'], $response->getArrayCopy());
    }

    public function testMarkDeliveredWithMultipleChannels()
    {
        $data = [
            'channel_delivered_message' => [
                'messaging:channel_1' => 'message_1_id',
                'messaging:channel_2' => 'message_2_id',
                'team:team_channel' => 'team_message_id'
            ]
        ];

        $expectedPayload = array_merge($data, ['user_id' => 'test_user_id']);
        $expectedResponse = new StreamResponse(['status' => 'success'], $this->createMockResponse());
        
        $this->client->expects($this->once())
            ->method('post')
            ->with(
                'channels/delivered',
                $expectedPayload
            )
            ->willReturn($expectedResponse);

        $response = $this->channel->markDelivered('test_user_id', $data);
        
        $this->assertInstanceOf(StreamResponse::class, $response);
        $this->assertEquals(['status' => 'success'], $response->getArrayCopy());
    }

    private function createMockResponse()
    {
        $mock = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mock->method('getStatusCode')->willReturn(200);
        $mock->method('getHeaders')->willReturn([]);
        $mock->method('hasHeader')->willReturn(false);
        return $mock;
    }
}

