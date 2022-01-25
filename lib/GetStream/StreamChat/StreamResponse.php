<?php

namespace GetStream\StreamChat;

use Psr\Http\Message\ResponseInterface;

/**
 * A class that extends ArrayObject and additionally contains response metadata
 * such as rate limits.
 */
class StreamResponse extends \ArrayObject
{
    /**
     * @var StreamRateLimit|null
     */
    private $rateLimits;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string[][]
     */
    private $headers;

    /**
      * @param array $array
      * @param ResponseInterface $response
      */
    public function __construct($array, $response)
    {
        parent::__construct($array);

        if ($response->hasHeader("x-ratelimit-limit")
          && $response->hasHeader("x-ratelimit-remaining")
          && $response->hasHeader("x-ratelimit-reset")) {
            $this->rateLimits = new StreamRateLimit(
                $response->getHeader("x-ratelimit-limit")[0],
                $response->getHeader("x-ratelimit-remaining")[0],
                $response->getHeader("x-ratelimit-reset")[0]
            );
        }

        $this->statusCode = $response->getStatusCode();
        $this->headers = $response->getHeaders();
    }

    /** Returns rate limit information about the response. The array's keys: "Limit", "Remaining", "Reset".
      * @return StreamRateLimit|null
      */
    public function getRateLimits()
    {
        return $this->rateLimits;
    }

    /** Returns the status code of the response.
      * @return int
      */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /** Returns the headers of the response.
      * @return string[][]
      */
    public function getHeaders()
    {
        return $this->headers;
    }
}
