<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

use GuzzleHttp\Exception\ClientException;

/**
 * Exception when a client error is encountered
 */
class StreamException extends \Exception
{
    /**
     * @return string|null
     */
    public function getRateLimitLimit()
    {
        return $this->getRateLimitValue("limit");
    }

    /**
     * @return string|null
     */
    public function getRateLimitRemaining()
    {
        return $this->getRateLimitValue("remaining");
    }

    /**
     * @return string|null
     */
    public function getRateLimitReset()
    {
        return $this->getRateLimitValue("reset");
    }

    /**
     * @param string $headerName
     * @return string|null
     */
    private function getRateLimitValue($headerName)
    {
        $e = $this->getPrevious();

        if ($e && $e instanceof ClientException) {
            $headerValues = $e->getResponse()->getHeader("x-ratelimit-" . $headerName);

            if ($headerValues) {
                return $headerValues[0];
            }
        }

        return null;
    }
}
