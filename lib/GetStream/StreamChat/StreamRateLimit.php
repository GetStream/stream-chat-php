<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

use DateTime;

/**
 * A data class that includes the 3 fields of Stream's ratelimit response.
 */
class StreamRateLimit
{
    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $remaining;

    /**
     * @var DateTime
     */
    private $reset;

    /** @internal */
    public function __construct(string $limit, string $remaining, string $reset)
    {
        $this->limit = intval($limit);
        $this->remaining = intval($remaining);
        $this->reset = new DateTime("@" . $reset);
    }

    /** Returns the max amount of requests that can be made in the current period.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /** Returns how many requests are remaining in the current period.
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }

    /** Returns the date when the current period will end.
     */
    public function getReset(): DateTime
    {
        return $this->reset;
    }
}
