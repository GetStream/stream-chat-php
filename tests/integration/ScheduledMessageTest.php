<?php

declare(strict_types=0);

namespace GetStream\Integration;

use DateTime;
use GetStream\StreamChat\Client;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real app, which must have scheduled messages enabled.
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

    /**
     * Returns the time $modifier from now, floored to a whole minute. The
     * server rounds a send time up to the next whole UTC minute, so only a
     * whole minute comes back as sent.
     */
    private function wholeMinute(string $modifier): DateTime
    {
        $time = new DateTime($modifier);
        $time->setTime((int)$time->format('H'), (int)$time->format('i'));
        return $time;
    }

    private function schedule(string $text): array
    {
        $response = $this->channel->scheduleMessage(['text' => $text], $this->user['id'], new DateTime('+1 hour'));
        return $response['scheduled_message'];
    }

    public function testScheduleMessage()
    {
        $scheduledAt = $this->wholeMinute('+1 hour');
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
        $newTime = $this->wholeMinute('+2 hours');

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
