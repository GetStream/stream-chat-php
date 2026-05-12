<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

class InvalidWebhookException extends StreamException
{
    public const SIGNATURE_MISMATCH = 'signature mismatch';
    public const INVALID_BASE64     = 'invalid base64 encoding';
    public const GZIP_FAILED        = 'gzip decompression failed';
    public const INVALID_JSON       = 'invalid JSON payload';
}
