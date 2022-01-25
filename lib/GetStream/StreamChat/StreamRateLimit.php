<?php

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

    /**
      * @param string $limit
      * @param string $remaining
      * @param string $reset
      */
    public function __construct($limit, $remaining, $reset)
    {
        $this->limit = intval($limit);
        $this->remaining = intval($remaining);
        $this->reset = new DateTime("@".$reset);
    }

    /** Returns the max amount of requests that can be made in the current period.
      * @return int
      */
    public function getLimit()
    {
        return $this->limit;
    }

    /** Returns how many requests are remaining in the current period.
      * @return int
      */
    public function getRemaining()
    {
        return $this->remaining;
    }

    /** Returns the date when the current period will end.
      * @return DateTime
      */
    public function getReset()
    {
        return $this->reset;
    }
}
