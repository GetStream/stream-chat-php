<?php

declare(strict_types=1);

namespace GetStream\StreamChat;

/**
 * Raised by webhook verify/parse helpers when the HMAC does not match, or a
 * gzip/base64/JSON envelope cannot be decoded.
 *
 * The message text identifies which failure mode fired; the class constants
 * below are the canonical strings for callers that prefer exact-match
 * filtering over substring matching.
 */
class InvalidWebhookError extends StreamException
{
    public const SIGNATURE_MISMATCH = 'signature mismatch';
    public const INVALID_BASE64     = 'invalid base64 encoding';
    public const GZIP_FAILED        = 'gzip decompression failed';
    public const INVALID_JSON       = 'invalid JSON payload';
}
