<?php

declare(strict_types=0);

namespace GetStream\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use GetStream\StreamChat\Channel;
use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamResponse;
use PHPUnit\Framework\TestCase;

class ScheduledMessageTest extends TestCase
{
    private function client(array $methods): Client
    {
        return $this->getMockBuilder(Client::class)
            ->setConstructorArgs(['key', 'secret'])
            ->onlyMethods($methods)
            ->getMock();
    }

    public function testFormatScheduledAtIsUtcToTheMillisecond()
    {
        $at = new DateTimeImmutable('2030-01-01 02:00:00.123456', new DateTimeZone('+02:00'));

        $this->assertSame('2030-01-01T00:00:00.123Z', Client::formatScheduledAt($at));
    }

    public function testScheduleMessageSendsScheduledAt()
    {
        $client = $this->client(['post']);
        $client->expects($this->once())
            ->method('post')
            ->with("channels/messaging/chan/message", [
                "skip_push" => true,
                "scheduled_at" => "2030-01-01T00:00:00.000Z",
                "message" => ["text" => "hi", "user_id" => "gandalf"],
            ])
            ->willReturn($this->createMock(StreamResponse::class));
        $channel = new Channel($client, "messaging", "chan");

        $channel->scheduleMessage(
            ["text" => "hi"],
            "gandalf",
            new DateTime('2030-01-01T00:00:00Z'),
            ["skip_push" => true]
        );
    }

    public function testUpdateScheduledMessageWithOnlyScheduledAt()
    {
        $client = $this->client(['patch']);
        $client->expects($this->once())
            ->method('patch')
            ->with("scheduled_messages/sm-1", ["scheduled_at" => "2030-01-01T00:00:00.000Z"])
            ->willReturn($this->createMock(StreamResponse::class));

        $client->updateScheduledMessage("sm-1", null, new DateTime('2030-01-01T00:00:00Z'));
    }

    public function testUpdateScheduledMessageWithMessageAndScheduledAt()
    {
        $client = $this->client(['patch']);
        $client->expects($this->once())
            ->method('patch')
            ->with("scheduled_messages/sm-1", [
                "message" => ["text" => "edited"],
                "scheduled_at" => "2030-01-01T00:00:00.000Z",
            ])
            ->willReturn($this->createMock(StreamResponse::class));

        $client->updateScheduledMessage("sm-1", ["text" => "edited"], new DateTime('2030-01-01T00:00:00Z'));
    }

    public function testDeleteScheduledMessage()
    {
        $client = $this->client(['delete']);
        $client->expects($this->once())
            ->method('delete')
            ->with("scheduled_messages/sm-1")
            ->willReturn($this->createMock(StreamResponse::class));

        $client->deleteScheduledMessage("sm-1");
    }

    public function testSendScheduledMessageNow()
    {
        $client = $this->client(['post']);
        $client->expects($this->once())
            ->method('post')
            ->with("scheduled_messages/sm-1/send_now", (object)[])
            ->willReturn($this->createMock(StreamResponse::class));

        $client->sendScheduledMessageNow("sm-1");
    }

    public function testQueryScheduledMessagesWithOnlyUserId()
    {
        $client = $this->client(['post']);
        $client->expects($this->once())
            ->method('post')
            ->with("scheduled_messages/query", ["user_id" => "gandalf"])
            ->willReturn($this->createMock(StreamResponse::class));

        $client->queryScheduledMessages("gandalf");
    }

    public function testQueryScheduledMessagesWithAllOptions()
    {
        $client = $this->client(['post']);
        $client->expects($this->once())
            ->method('post')
            ->with("scheduled_messages/query", [
                "user_id" => "gandalf",
                "filter" => ["cid" => "messaging:chan", "status" => ["scheduled", "failed"]],
                "sort" => [["field" => "scheduled_at", "direction" => 1]],
                "limit" => 50,
                "next" => "cursor",
            ])
            ->willReturn($this->createMock(StreamResponse::class));

        $client->queryScheduledMessages(
            "gandalf",
            ["cid" => "messaging:chan", "status" => ["scheduled", "failed"]],
            [["field" => "scheduled_at", "direction" => 1]],
            ["limit" => 50, "next" => "cursor"]
        );
    }
}
