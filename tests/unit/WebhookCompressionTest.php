<?php

declare(strict_types=1);

namespace GetStream\Unit;

use GetStream\StreamChat\Client;
use GetStream\StreamChat\InvalidWebhookError;
use GetStream\StreamChat\Webhook;
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

    public function testGunzipPayloadPassthroughPlainBytes(): void
    {
        $this->assertSame(self::JSON_BODY, Client::gunzipPayload(self::JSON_BODY));
    }

    public function testGunzipPayloadInflatesGzipBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $this->assertNotFalse($compressed);
        $this->assertSame(self::JSON_BODY, Client::gunzipPayload($compressed));
    }

    public function testGunzipPayloadEmptyInput(): void
    {
        $this->assertSame('', Client::gunzipPayload(''));
    }

    public function testGunzipPayloadShortInputBelowMagicLength(): void
    {
        $this->assertSame('ab', Client::gunzipPayload('ab'));
    }

    public function testGunzipPayloadThrowsOnTruncatedGzipMagic(): void
    {
        $bad = "\x1f\x8b\x08\x00\x00\x00";
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::GZIP_FAILED);
        Client::gunzipPayload($bad);
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
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::INVALID_BASE64);
        Client::decodeSqsPayload('!!!not-base64!!!');
    }

    public function testDecodeSnsPayloadPreExtractedMessageMatchesDecodeSqsPayload(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $this->assertSame(
            Client::decodeSqsPayload($wrapped),
            Client::decodeSnsPayload($wrapped)
        );
    }

    public function testDecodeSnsPayloadUnwrapsFullSnsEnvelope(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $envelope = $this->snsEnvelope($wrapped);
        $this->assertSame(self::JSON_BODY, Client::decodeSnsPayload($envelope));
    }

    public function testDecodeSnsPayloadHandlesEnvelopeWithWhitespacePrefix(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $envelope = "\n  " . $this->snsEnvelope($wrapped);
        $this->assertSame(self::JSON_BODY, Client::decodeSnsPayload($envelope));
    }

    private function snsEnvelope(string $innerMessage): string
    {
        return json_encode([
            'Type' => 'Notification',
            'MessageId' => '22b80b92-fdea-4c2c-8f9d-bdfb0c7bf324',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:stream-webhooks',
            'Message' => $innerMessage,
            'Timestamp' => '2026-05-11T10:00:00.000Z',
            'SignatureVersion' => '1',
            'MessageAttributes' => [
                'X-Signature' => ['Type' => 'String', 'Value' => '<signature placeholder>'],
            ],
        ]);
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
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::INVALID_JSON);
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
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::SIGNATURE_MISMATCH);
        $this->client->verifyAndParseWebhook(self::JSON_BODY, str_repeat('0', 64));
    }

    public function testVerifyAndParseWebhookRejectsSignatureOverCompressedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sigOverCompressed = hash_hmac('sha256', $compressed, self::API_SECRET);
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::SIGNATURE_MISMATCH);
        $this->client->verifyAndParseWebhook($compressed, $sigOverCompressed);
    }

    public function testParseSqsBase64Only(): void
    {
        $wrapped = base64_encode(self::JSON_BODY);
        $event = $this->client->parseSqs($wrapped);
        $this->assertSame('message.new', $event['type']);
    }

    public function testParseSqsBase64Plusgzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $event = $this->client->parseSqs($wrapped);
        $this->assertSame('message.new', $event['type']);
    }

    public function testParseSnsPreExtractedMessage(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $event = $this->client->parseSns($wrapped);
        $this->assertSame('message.new', $event['type']);
    }

    public function testParseSnsMatchesParseSqsForPreExtractedMessage(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $this->assertSame(
            $this->client->parseSqs($wrapped),
            $this->client->parseSns($wrapped)
        );
    }

    public function testParseSnsFullEnvelope(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $envelope = $this->snsEnvelope($wrapped);
        $event = $this->client->parseSns($envelope);
        $this->assertSame('message.new', $event['type']);
    }

    public function testVerifyWebhookBackwardCompatibility(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $this->assertTrue($this->client->verifyWebhook(self::JSON_BODY, $sig));
        $this->assertFalse($this->client->verifyWebhook(self::JSON_BODY, 'deadbeef'));
    }

    public function testWebhookStaticPrimitivesMatchClient(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $this->assertSame(Client::gunzipPayload($compressed), Webhook::gunzipPayload($compressed));
        $this->assertSame(Client::decodeSqsPayload($wrapped), Webhook::decodeSqsPayload($wrapped));
        $this->assertSame(Client::decodeSnsPayload($wrapped), Webhook::decodeSnsPayload($wrapped));
        $sig = $this->sign(self::JSON_BODY);
        $this->assertTrue(Webhook::verifySignature(self::JSON_BODY, $sig, self::API_SECRET));
        $this->assertSame(Client::parseEvent(self::JSON_BODY), Webhook::parseEvent(self::JSON_BODY));
    }

    public function testWebhookVerifyAndParseWebhookStaticPlain(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $event = Webhook::verifyAndParseWebhook(self::JSON_BODY, $sig, self::API_SECRET);
        $this->assertSame('message.new', $event['type']);
    }

    public function testWebhookVerifyAndParseWebhookStaticGzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sig = $this->sign(self::JSON_BODY);
        $event = Webhook::verifyAndParseWebhook($compressed, $sig, self::API_SECRET);
        $this->assertSame('message.new', $event['type']);
    }

    public function testWebhookVerifyAndParseWebhookStaticSignatureMismatch(): void
    {
        $this->expectException(InvalidWebhookError::class);
        $this->expectExceptionMessage(InvalidWebhookError::SIGNATURE_MISMATCH);
        Webhook::verifyAndParseWebhook(self::JSON_BODY, str_repeat('0', 64), self::API_SECRET);
    }

    public function testWebhookParseSqsStatic(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $event = Webhook::parseSqs($wrapped);
        $this->assertSame('message.new', $event['type']);
    }

    public function testWebhookParseSnsStaticMatchesParseSqs(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $this->assertSame(
            Webhook::parseSqs($wrapped),
            Webhook::parseSns($wrapped)
        );
    }

    public function testClientInstanceCompositesDelegateToWebhook(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sig = $this->sign(self::JSON_BODY);
        $this->assertSame(
            Webhook::verifyAndParseWebhook($compressed, $sig, self::API_SECRET),
            $this->client->verifyAndParseWebhook($compressed, $sig)
        );
        $this->assertSame(
            Webhook::parseSqs($wrapped),
            $this->client->parseSqs($wrapped)
        );
        $this->assertSame(
            Webhook::parseSns($wrapped),
            $this->client->parseSns($wrapped)
        );
    }
}
