<?php

declare(strict_types=0);

namespace GetStream\Integration;

use DateTime;
use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

class ReminderTest extends TestCase
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
            "config_overrides" => ["user_message_reminders" => true],
        ]);
        
        // Create a message to use for reminders
        $message = [
            'text' => 'This is a test message for reminders'
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
        $userId = 'reminder-test-user-' . uniqid();
        $user = [
            'id' => $userId,
            'name' => 'Reminder Test User',
        ];
        $this->client->upsertUser($user);
        return $user;
    }

    public function getChannel(): \GetStream\StreamChat\Channel
    {
        $channelId = 'reminder-test-channel-' . uniqid();
        $channel = $this->client->Channel('messaging', $channelId);
        $channel->create($this->user['id']);
        return $channel;
    }

    public function testCreateReminder()
    {
        $remindAt = new DateTime('+1 day');
        $response = $this->client->createReminder($this->messageId, $this->user['id'], $remindAt);
        
        $this->assertArrayHasKey('reminder', $response);
        $this->assertEquals($this->messageId, $response['reminder']['message_id']);
        $this->assertEquals($this->user['id'], $response['reminder']['user_id']);
        $this->assertNotEmpty($response['reminder']['remind_at']);
    }

    public function testCreateReminderWithoutRemindAt()
    {
        $response = $this->client->createReminder($this->messageId, $this->user['id']);
        
        $this->assertArrayHasKey('reminder', $response);
        $this->assertEquals($this->messageId, $response['reminder']['message_id']);
        $this->assertEquals($this->user['id'], $response['reminder']['user_id']);
    }

    public function testUpdateReminder()
    {
        // First create a reminder
        $this->client->createReminder($this->messageId, $this->user['id']);
        
        // Then update it
        $newRemindAt = new DateTime('+2 days');
        $response = $this->client->updateReminder($this->messageId, $this->user['id'], $newRemindAt);
        
        $this->assertArrayHasKey('reminder', $response);
        $this->assertEquals($this->messageId, $response['reminder']['message_id']);
        $this->assertEquals($this->user['id'], $response['reminder']['user_id']);
        $this->assertNotEmpty($response['reminder']['remind_at']);
    }

    public function testDeleteReminder()
    {
        // First create a reminder
        $this->client->createReminder($this->messageId, $this->user['id']);
        
        // Then delete it
        $response = $this->client->deleteReminder($this->messageId, $this->user['id']);
        
        // The response is a StreamResponse object, so we'll just check that it exists
        $this->assertNotNull($response);
        $this->assertTrue(true); // If we got here, the test passed
    }

    public function testQueryReminders()
    {
        // Create a reminder
        $remindAt = new DateTime('+1 day');
        $this->client->createReminder($this->messageId, $this->user['id'], $remindAt);
        
        // Query reminders
        $response = $this->client->queryReminders($this->user['id']);
        
        $this->assertArrayHasKey('reminders', $response);
        $this->assertGreaterThan(0, count($response['reminders']));
        
        // Test with filter conditions
        $filterConditions = [
            'message_id' => $this->messageId
        ];
        $response = $this->client->queryReminders($this->user['id'], $filterConditions);
        
        $this->assertArrayHasKey('reminders', $response);
        $this->assertGreaterThan(0, count($response['reminders']));
        $this->assertEquals($this->messageId, $response['reminders'][0]['message_id']);
    }
}
