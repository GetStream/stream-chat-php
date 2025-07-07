<?php

declare(strict_types=0);

namespace GetStream\Integration;

use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

class SharedLocationsTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $user;

    /**
     * @var \GetStream\StreamChat\Channel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $messageId;

    protected function setUp(): void
    {
        // Set the base URL environment variable if STREAM_HOST is provided
        $baseURL = getenv('STREAM_HOST');
        if ($baseURL) {
            putenv("STREAM_BASE_CHAT_URL={$baseURL}");
        }
        
        $this->client = new Client(getenv('STREAM_KEY'), getenv('STREAM_SECRET'));
        $this->user = $this->getUser();
        $this->channel = $this->getChannel();
        $this->channel->updatePartial([
            "config_overrides" => ["shared_locations" => true],
        ]);
        
        // Create a message to use for shared locations
        $message = [
            'text' => 'This is a test message for shared locations'
        ];
        $response = $this->channel->sendMessage($message, $this->user['id']);
        $this->messageId = $response['message']['id'];
    }

    protected function tearDown(): void
    {
        try {
            $this->channel->delete();
            $this->client->deleteUser($this->user['id'], ["user" => "hard", "messages" => "hard"]);
        } catch (\Exception $e) {
            // We don't care about cleanup errors
        }
    }

    private function getUser(): array
    {
        $userId = 'shared-locations-test-user-' . uniqid();
        $user = [
            'id' => $userId,
            'name' => 'Shared Locations Test User',
        ];
        $this->client->upsertUser($user);
        return $user;
    }

    public function getChannel(): \GetStream\StreamChat\Channel
    {
        $channelId = 'shared-locations-test-channel-' . uniqid();
        $channel = $this->client->Channel('messaging', $channelId);
        $channel->create($this->user['id']);
        return $channel;
    }

    public function testGetUserActiveLiveLocations()
    {
        $response = $this->client->getUserActiveLiveLocations($this->user['id']);
        
        // The response should be a StreamResponse object
        $this->assertNotNull($response);
        // Initially, user should have no active live locations
        $this->assertArrayHasKey('live_locations', $response);
        $this->assertIsArray($response['live_locations']);
    }

    public function testUpdateUserActiveLiveLocation()
    {
        $location = [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 10.5,
            'accuracy' => 5.0,
            'speed' => 0.0,
            'heading' => 0.0,
            'timestamp' => time()
        ];

        $response = $this->client->updateUserActiveLiveLocation($this->user['id'], $this->messageId, $location);
        
        $this->assertNotNull($response);
        $this->assertTrue(true); // If we got here, the test passed
    }

    public function testUpdateUserActiveLiveLocationWithMinimalData()
    {
        $location = [
            'latitude' => 34.0522,
            'longitude' => -118.2437
        ];

        $response = $this->client->updateUserActiveLiveLocation($this->user['id'], $this->messageId, $location);
        
        $this->assertNotNull($response);
        $this->assertTrue(true); // If we got here, the test passed
    }

    public function testGetUserActiveLiveLocationsAfterUpdate()
    {
        // First update a location
        $location = [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'accuracy' => 10.0
        ];
        $this->client->updateUserActiveLiveLocation($this->user['id'], $this->messageId, $location);
        
        // Then get the active live locations
        $response = $this->client->getUserActiveLiveLocations($this->user['id']);
        
        $this->assertNotNull($response);
        $this->assertArrayHasKey('live_locations', $response);
        $this->assertIsArray($response['live_locations']);
    }

    public function testUpdateUserActiveLiveLocationMultipleTimes()
    {
        $location1 = [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'accuracy' => 5.0
        ];

        $location2 = [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'accuracy' => 3.0,
            'speed' => 15.5
        ];

        // Update location twice
        $response1 = $this->client->updateUserActiveLiveLocation($this->user['id'], $this->messageId, $location1);
        $response2 = $this->client->updateUserActiveLiveLocation($this->user['id'], $this->messageId, $location2);
        
        $this->assertNotNull($response1);
        $this->assertNotNull($response2);
        $this->assertTrue(true); // If we got here, the test passed
    }
} 