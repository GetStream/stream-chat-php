<?php

declare(strict_types=0);

namespace GetStream\Unit;

use GetStream\StreamChat\Channel;
use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamResponse;
use PHPUnit\Framework\TestCase;

class ChannelDeleteTest extends TestCase
{
    private function channel(Client $client): Channel
    {
        return new Channel($client, "messaging", "chan");
    }

    public function testDeleteWithoutOptions()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('delete')
            ->with("channels/messaging/chan", [])
            ->willReturn($this->createMock(StreamResponse::class));

        $this->channel($client)->delete();
    }

    public function testDeleteWithSkipTruncate()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('delete')
            ->with("channels/messaging/chan", ["skip_truncate" => true])
            ->willReturn($this->createMock(StreamResponse::class));

        $this->channel($client)->delete(["skip_truncate" => true]);
    }
}
