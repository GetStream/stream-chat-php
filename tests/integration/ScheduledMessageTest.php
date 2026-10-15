<?php

declare(strict_types=0);

namespace GetStream\Integration;

use DateTime;
use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real app. The scheduled messages endpoints are behind a
 * per-app flag, so these tests run only when
 * STREAM_SCHEDULED_MESSAGES_ENABLED=true says the flag is on for the app of
 * STREAM_KEY.
 */
class ScheduledMessageTest extends TestCase
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

    protected function setUp(): void
    {
        if (getenv('STREAM_SCHEDULED_MESSAGES_ENABLED') !== 'true') {
            $this->markTestSkipped('Set STREAM_SCHEDULED_MESSAGES_ENABLED=true once scheduled messages are enabled for the app of STREAM_KEY.');
        }

        $baseURL = getenv('STREAM_HOST');
        if ($baseURL) {
            putenv("STREAM_BASE_CHAT_URL={$baseURL}");
        }

        $this->client = new Client(getenv('STREAM_KEY'), getenv('STREAM_SECRET'));
        $this->user = ['id' => 'scheduled-test-user-' . uniqid(), 'name' => 'Scheduled Test User'];
        $this->client->upsertUser($this->user);
        $this->channel = $this->client->Channel('messaging', 'scheduled-test-channel-' . uniqid());
        $this->channel->create($this->user['id']);
    }

    protected function tearDown(): void
    {
        if ($this->channel === null) {
            return;
        }
        try {
            $this->channel->delete();
            $this->client->deleteUser($this->user['id'], ["user" => "hard", "messages" => "hard"]);
        } catch (\Exception $e) {
            // We don't care about cleanup errors
        }
    }

    private function schedule(string $text): array
    {
        $response = $this->channel->scheduleMessage(['text' => $text], $this->user['id'], new DateTime('+1 hour'));
        return $response['scheduled_message'];
    }

    public function testScheduleMessage()
    {
        $scheduledAt = new DateTime('+1 hour');
        $response = $this->channel->scheduleMessage(['text' => 'later'], $this->user['id'], $scheduledAt);

        $this->assertArrayHasKey('scheduled_message', $response);
        $sm = $response['scheduled_message'];
        $this->assertSame('scheduled', $sm['status']);
        $this->assertSame($this->channel->getCID(), $sm['cid']);
        $this->assertSame($this->user['id'], $sm['user']['id']);
        $this->assertSame('later', $sm['message']['text']);
        $this->assertSame($scheduledAt->getTimestamp(), (new DateTime($sm['scheduled_at']))->getTimestamp());
    }

    public function testQueryScheduledMessages()
    {
        $sm = $this->schedule('to list');

        $response = $this->client->queryScheduledMessages(
            $this->user['id'],
            ['cid' => $this->channel->getCID()],
            null,
            ['limit' => 100]
        );

        $ids = array_column($response['scheduled_messages'], 'id');
        $this->assertContains($sm['id'], $ids);
    }

    public function testUpdateScheduledMessage()
    {
        $sm = $this->schedule('to edit');
        $newTime = new DateTime('+2 hours');

        $response = $this->client->updateScheduledMessage($sm['id'], ['text' => 'edited'], $newTime);

        $this->assertSame($sm['id'], $response['scheduled_message']['id']);
        $this->assertSame('edited', $response['scheduled_message']['message']['text']);
        $this->assertSame($newTime->getTimestamp(), (new DateTime($response['scheduled_message']['scheduled_at']))->getTimestamp());
    }

    public function testDeleteScheduledMessage()
    {
        $sm = $this->schedule('to cancel');

        $response = $this->client->deleteScheduledMessage($sm['id']);

        $this->assertSame($sm['id'], $response['scheduled_message']['id']);
        $this->assertSame('canceled', $response['scheduled_message']['status']);
    }

    public function testSendScheduledMessageNow()
    {
        $sm = $this->schedule('to send now');

        $response = $this->client->sendScheduledMessageNow($sm['id']);

        $this->assertSame($sm['id'], $response['scheduled_message']['id']);
        $this->assertLessThan(
            (new DateTime($sm['scheduled_at']))->getTimestamp(),
            (new DateTime($response['scheduled_message']['scheduled_at']))->getTimestamp()
        );
    }
}
