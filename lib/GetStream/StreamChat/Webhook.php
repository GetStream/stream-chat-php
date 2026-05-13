<?php

declare(strict_types=1);

namespace GetStream\StreamChat;

/**
 * Stateless helpers implementing the cross-SDK webhook contract documented at
 * https://getstream.io/chat/docs/node/webhooks_overview/.
 *
 * The composite functions (`verifyAndParseWebhook`, `parseSqs`, `parseSns`).
 * The primitives they
 * compose (`gunzipPayload`, `decodeSqsPayload`, `decodeSnsPayload`,
 * `verifySignature`, `parseEvent`) are exposed so callers can build custom
 * flows or run individual steps in isolation.
 *
 * The PHP SDK currently returns the parsed JSON as an associative array; typed
 * event classes will land in a future release.
 */
class Webhook
{
    /** Constant-time HMAC-SHA256 verification of `$signature` against the digest of
     * `$body` using `$secret` as the key.
     *
     * The signature is always computed over the **uncompressed** JSON bytes, so
     * callers that decoded a gzipped or base64-wrapped payload must pass the
     * inflated bytes here.
     */
    public static function verifySignature(string $body, string $signature, string $secret): bool
    {
        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /** Returns `$body` unchanged unless it starts with the gzip magic
     * (`1f 8b`, per RFC 1952), in which case the gzip stream is inflated and
     * the decompressed bytes are returned.
     *
     * Magic-byte detection (rather than relying on a header) keeps the same
     * handler correct when middleware auto-decompresses the request before your
     * code sees it.
     *
     * @throws InvalidWebhookError when the body has the gzip magic but cannot be
     *   inflated.
     */
    public static function gunzipPayload(string $body): string
    {
        if (substr($body, 0, 2) !== "\x1f\x8b") {
            return $body;
        }
        $decoded = @gzdecode($body);
        if ($decoded === false) {
            throw new InvalidWebhookError(InvalidWebhookError::GZIP_FAILED);
        }
        return $decoded;
    }

    /** Reverses the SQS firehose envelope: the message `Body` is base64-decoded
     * and, when the result begins with the gzip magic, gzip-decompressed. The
     * same call works whether or not Stream is currently compressing payloads.
     *
     * @throws InvalidWebhookError when the input is not valid base64 or the
     *   inner gzip stream cannot be inflated.
     */
    public static function decodeSqsPayload(string $body): string
    {
        $decoded = base64_decode($body, true);
        if ($decoded === false) {
            throw new InvalidWebhookError(InvalidWebhookError::INVALID_BASE64);
        }
        return self::gunzipPayload($decoded);
    }

    /** Reverses an SNS HTTP notification envelope. When `$notificationBody` is
     * a JSON envelope (`{"Type":"Notification","Message":"..."}`), the inner
     * `Message` field is extracted and run through the SQS pipeline
     * (base64-decode, then gzip-if-magic). When the input is not a JSON
     * envelope it is treated as the already-extracted `Message` string, so
     * call sites that pre-unwrap continue to work.
     *
     * @throws InvalidWebhookError
     */
    public static function decodeSnsPayload(string $notificationBody): string
    {
        $inner = self::extractSnsMessage($notificationBody);
        return self::decodeSqsPayload($inner ?? $notificationBody);
    }

    private static function extractSnsMessage(string $notificationBody): ?string
    {
        $trimmed = ltrim($notificationBody);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }
        $parsed = json_decode($trimmed, true);
        if (!is_array($parsed)) {
            return null;
        }
        $message = $parsed['Message'] ?? null;
        return is_string($message) ? $message : null;
    }

    /** Parse a JSON-encoded webhook event into an associative array.
     *
     * The PHP SDK currently returns the parsed JSON as an array; typed event
     * classes will land in a future release. The function name matches the
     * documented primitive so callers can swap in a typed parser later without
     * changing call sites.
     *
     * @return array<string, mixed>
     * @throws InvalidWebhookError when the bytes are not valid JSON.
     */
    public static function parseEvent(string $payload): array
    {
        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidWebhookError(InvalidWebhookError::INVALID_JSON);
        }
        if (!is_array($event)) {
            throw new InvalidWebhookError(InvalidWebhookError::INVALID_JSON);
        }
        return $event;
    }

    /** Decompress `$body` when gzipped, verify the HMAC `$signature`, and return
     * the parsed event.
     *
     * @return array<string, mixed>
     * @throws InvalidWebhookError when the signature does not match or the gzip
     *   envelope is malformed.
     */
    public static function verifyAndParseWebhook(string $body, string $signature, string $secret): array
    {
        $inflated = self::gunzipPayload($body);
        if (!self::verifySignature($inflated, $signature, $secret)) {
            throw new InvalidWebhookError(InvalidWebhookError::SIGNATURE_MISMATCH);
        }
        return self::parseEvent($inflated);
    }

    /** Decode the SQS `Body` (base64, then gzip-if-magic) and return the parsed event.
     * Stream does not HMAC-sign SQS message bodies.
     *
     * @return array<string, mixed>
     * @throws InvalidWebhookError
     */
    public static function parseSqs(string $messageBody): array
    {
        $inflated = self::decodeSqsPayload($messageBody);

        return self::parseEvent($inflated);
    }

    /** Decode an SNS payload (unwrap envelope when present). No HMAC verification.
     *
     * @return array<string, mixed>
     * @throws InvalidWebhookError
     */
    public static function parseSns(string $message): array
    {
        $inflated = self::decodeSnsPayload($message);

        return self::parseEvent($inflated);
    }
}
