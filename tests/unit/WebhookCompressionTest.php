<?php

declare(strict_types=0);

namespace GetStream\Unit;

use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamException;
use PHPUnit\Framework\TestCase;

class WebhookCompressionTest extends TestCase
{
    private const API_KEY = 'key';
    private const API_SECRET = 'tsec2';
    private const JSON_BODY = '{"type":"message.new","message":{"text":"the quick brown fox"}}';

    private Client $client;

    public function setUp(): void
    {
        $this->client = new Client(self::API_KEY, self::API_SECRET);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::API_SECRET);
    }

    public function testUngzipPayloadPassthroughPlainBytes(): void
    {
        $this->assertSame(self::JSON_BODY, Client::ungzipPayload(self::JSON_BODY));
    }

    public function testUngzipPayloadInflatesGzipBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $this->assertNotFalse($compressed);
        $this->assertSame(self::JSON_BODY, Client::ungzipPayload($compressed));
    }

    public function testUngzipPayloadEmptyInput(): void
    {
        $this->assertSame('', Client::ungzipPayload(''));
    }

    public function testUngzipPayloadShortInputBelowMagicLength(): void
    {
        $this->assertSame('ab', Client::ungzipPayload('ab'));
    }

    public function testUngzipPayloadThrowsOnTruncatedGzipMagic(): void
    {
        $bad = "\x1f\x8b\x08\x00\x00\x00";
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/decompress gzip/');
        Client::ungzipPayload($bad);
    }

    public function testDecodeSqsPayloadBase64Only(): void
    {
        $this->assertSame(
            self::JSON_BODY,
            Client::decodeSqsPayload(base64_encode(self::JSON_BODY))
        );
    }

    public function testDecodeSqsPayloadBase64Plusgzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $this->assertSame(
            self::JSON_BODY,
            Client::decodeSqsPayload(base64_encode($compressed))
        );
    }

    public function testDecodeSqsPayloadThrowsOnMalformedBase64(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/base64-decode/');
        Client::decodeSqsPayload('!!!not-base64!!!');
    }

    public function testDecodeSnsPayloadAliasesDecodeSqsPayload(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $this->assertSame(
            Client::decodeSqsPayload($wrapped),
            Client::decodeSnsPayload($wrapped)
        );
    }

    public function testVerifySignatureMatching(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $this->assertTrue(Client::verifySignature(self::JSON_BODY, $sig, self::API_SECRET));
    }

    public function testVerifySignatureMismatched(): void
    {
        $this->assertFalse(
            Client::verifySignature(self::JSON_BODY, str_repeat('0', 64), self::API_SECRET)
        );
    }

    public function testVerifySignatureRejectsSignatureOverCompressedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sigOverCompressed = hash_hmac('sha256', $compressed, self::API_SECRET);
        $this->assertFalse(
            Client::verifySignature(self::JSON_BODY, $sigOverCompressed, self::API_SECRET)
        );
    }

    public function testParseEventKnownEventType(): void
    {
        $event = Client::parseEvent(self::JSON_BODY);
        $this->assertSame('message.new', $event['type']);
        $this->assertSame('the quick brown fox', $event['message']['text']);
    }

    public function testParseEventUnknownTypeStillParses(): void
    {
        $event = Client::parseEvent('{"type":"a.future.event","custom":42}');
        $this->assertSame('a.future.event', $event['type']);
        $this->assertSame(42, $event['custom']);
    }

    public function testParseEventMalformedJsonThrows(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/parse webhook event/');
        Client::parseEvent('not json');
    }

    public function testVerifyAndParseWebhookPlain(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $event = $this->client->verifyAndParseWebhook(self::JSON_BODY, $sig);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyAndParseWebhookGzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sig = $this->sign(self::JSON_BODY);
        $event = $this->client->verifyAndParseWebhook($compressed, $sig);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyAndParseWebhookSignatureMismatch(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndParseWebhook(self::JSON_BODY, str_repeat('0', 64));
    }

    public function testVerifyAndParseWebhookRejectsSignatureOverCompressedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sigOverCompressed = hash_hmac('sha256', $compressed, self::API_SECRET);
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndParseWebhook($compressed, $sigOverCompressed);
    }

    public function testVerifyAndParseSqsBase64Only(): void
    {
        $wrapped = base64_encode(self::JSON_BODY);
        $sig = $this->sign(self::JSON_BODY);
        $event = $this->client->verifyAndParseSqs($wrapped, $sig);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyAndParseSqsBase64Plusgzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sig = $this->sign(self::JSON_BODY);
        $event = $this->client->verifyAndParseSqs($wrapped, $sig);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyAndParseSqsRejectsSignatureOverWrappedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sigOverWrapped = hash_hmac('sha256', $wrapped, self::API_SECRET);
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndParseSqs($wrapped, $sigOverWrapped);
    }

    public function testVerifyAndParseSnsRoundTrip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sig = $this->sign(self::JSON_BODY);
        $event = $this->client->verifyAndParseSns($wrapped, $sig);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyAndParseSnsMatchesSqs(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sig = $this->sign(self::JSON_BODY);
        $this->assertSame(
            $this->client->verifyAndParseSqs($wrapped, $sig),
            $this->client->verifyAndParseSns($wrapped, $sig)
        );
    }

    public function testVerifyWebhookBackwardCompatibility(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $this->assertTrue($this->client->verifyWebhook(self::JSON_BODY, $sig));
        $this->assertFalse($this->client->verifyWebhook(self::JSON_BODY, 'deadbeef'));
    }
}
