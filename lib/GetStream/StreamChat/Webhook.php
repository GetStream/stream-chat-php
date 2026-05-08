<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

/**
 * Stateless helpers implementing the cross-SDK webhook contract documented at
 * https://getstream.io/chat/docs/node/webhooks_overview/.
 *
 * The composite functions (`verifyAndParseWebhook`, `verifyAndParseSqs`,
 * `verifyAndParseSns`) are the recommended entry points. The primitives they
 * compose (`ungzipPayload`, `decodeSqsPayload`, `decodeSnsPayload`,
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
        return Client::verifySignature($body, $signature, $secret);
    }

    /** Returns `$body` unchanged unless it starts with the gzip magic
     * (`1f 8b`, per RFC 1952), in which case the gzip stream is inflated and
     * the decompressed bytes are returned.
     *
     * @throws StreamException when the body has the gzip magic but cannot be
     *   inflated.
     */
    public static function ungzipPayload(string $body): string
    {
        return Client::ungzipPayload($body);
    }

    /** Reverses the SQS firehose envelope: the message `Body` is base64-decoded
     * and, when the result begins with the gzip magic, gzip-decompressed.
     *
     * @throws StreamException when the input is not valid base64 or the inner
     *   gzip stream cannot be inflated.
     */
    public static function decodeSqsPayload(string $body): string
    {
        return Client::decodeSqsPayload($body);
    }

    /** Identical to {@see decodeSqsPayload()}; exposed under both names so call
     * sites read intent.
     *
     * @throws StreamException
     */
    public static function decodeSnsPayload(string $message): string
    {
        return Client::decodeSnsPayload($message);
    }

    /** Parse a JSON-encoded webhook event into an associative array.
     *
     * @return array<string, mixed>
     * @throws StreamException when the bytes are not valid JSON.
     */
    public static function parseEvent(string $payload): array
    {
        return Client::parseEvent($payload);
    }

    /** Decompress `$body` when gzipped, verify the HMAC `$signature`, and return
     * the parsed event.
     *
     * @return array<string, mixed>
     * @throws StreamException when the signature does not match or the gzip
     *   envelope is malformed.
     */
    public static function verifyAndParseWebhook(string $body, string $signature, string $secret): array
    {
        $inflated = self::ungzipPayload($body);
        if (!self::verifySignature($inflated, $signature, $secret)) {
            throw new StreamException('invalid webhook signature');
        }
        return self::parseEvent($inflated);
    }

    /** Decode the SQS `Body` (base64, then gzip-if-magic), verify the HMAC
     * `$signature` from the `X-Signature` message attribute, and return the
     * parsed event.
     *
     * @return array<string, mixed>
     * @throws StreamException
     */
    public static function verifyAndParseSqs(string $messageBody, string $signature, string $secret): array
    {
        $inflated = self::decodeSqsPayload($messageBody);
        if (!self::verifySignature($inflated, $signature, $secret)) {
            throw new StreamException('invalid webhook signature');
        }
        return self::parseEvent($inflated);
    }

    /** Decode the SNS notification `Message` (identical to SQS handling), verify
     * the HMAC `$signature` from the `X-Signature` message attribute, and return
     * the parsed event.
     *
     * @return array<string, mixed>
     * @throws StreamException
     */
    public static function verifyAndParseSns(string $message, string $signature, string $secret): array
    {
        $inflated = self::decodeSnsPayload($message);
        if (!self::verifySignature($inflated, $signature, $secret)) {
            throw new StreamException('invalid webhook signature');
        }
        return self::parseEvent($inflated);
    }
}
