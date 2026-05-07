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

    public function testDecompressWebhookBodyPassthroughWhenEncodingNull(): void
    {
        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody(self::JSON_BODY, null));
    }

    public function testDecompressWebhookBodyPassthroughWhenEncodingEmpty(): void
    {
        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody(self::JSON_BODY, ''));
        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody(self::JSON_BODY, '   '));
    }

    public function testDecompressWebhookBodyRoundTripsGzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $this->assertNotFalse($compressed);
        $this->assertNotEquals(self::JSON_BODY, $compressed);

        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody($compressed, 'gzip'));
    }

    public function testDecompressWebhookBodyHandlesEncodingCaseInsensitively(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody($compressed, 'GZIP'));
        $this->assertSame(self::JSON_BODY, $this->client->decompressWebhookBody($compressed, '  gzip  '));
    }

    /**
     * @dataProvider nonGzipEncodings
     */
    public function testDecompressWebhookBodyRejectsEveryNonGzipEncoding(string $encoding): void
    {
        try {
            $this->client->decompressWebhookBody(self::JSON_BODY, $encoding);
            $this->fail("expected StreamException for encoding '$encoding'");
        } catch (StreamException $e) {
            $this->assertStringContainsString('unsupported', $e->getMessage());
            $this->assertStringContainsString('gzip', $e->getMessage());
        }
    }

    public static function nonGzipEncodings(): array
    {
        return [
            'brotli short' => ['br'],
            'brotli long'  => ['brotli'],
            'zstd'         => ['zstd'],
            'deflate'      => ['deflate'],
            'compress'     => ['compress'],
            'lz4'          => ['lz4'],
        ];
    }

    public function testDecompressWebhookBodyThrowsOnInvalidGzipBytes(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/failed to gzip-decode/');
        $this->client->decompressWebhookBody('not actually gzip', 'gzip');
    }

    public function testVerifyWebhookUsesConstantTimeComparison(): void
    {
        $sig = $this->sign(self::JSON_BODY);
        $this->assertTrue($this->client->verifyWebhook(self::JSON_BODY, $sig));
        $this->assertFalse($this->client->verifyWebhook(self::JSON_BODY, 'deadbeef'));
    }

    public function testVerifyAndDecodeWebhookGzipHappyPath(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sig = $this->sign(self::JSON_BODY);

        $decoded = $this->client->verifyAndDecodeWebhook($compressed, $sig, 'gzip');
        $this->assertSame(self::JSON_BODY, $decoded);
    }

    public function testVerifyAndDecodeWebhookPassthroughHappyPath(): void
    {
        $sig = $this->sign(self::JSON_BODY);

        $this->assertSame(
            self::JSON_BODY,
            $this->client->verifyAndDecodeWebhook(self::JSON_BODY, $sig, null)
        );
        $this->assertSame(
            self::JSON_BODY,
            $this->client->verifyAndDecodeWebhook(self::JSON_BODY, $sig, '')
        );
    }

    public function testVerifyAndDecodeWebhookThrowsOnSignatureMismatch(): void
    {
        $compressed = gzencode(self::JSON_BODY);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndDecodeWebhook($compressed, 'deadbeef', 'gzip');
    }

    public function testVerifyAndDecodeWebhookRejectsSignatureOverCompressedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $sigOverCompressed = hash_hmac('sha256', $compressed, self::API_SECRET);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndDecodeWebhook($compressed, $sigOverCompressed, 'gzip');
    }

    public function testDecompressWebhookBodyRoundTripsBase64Gzip(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);

        $this->assertSame(
            self::JSON_BODY,
            $this->client->decompressWebhookBody($wrapped, 'gzip', 'base64')
        );
        $this->assertSame(
            self::JSON_BODY,
            $this->client->decompressWebhookBody($wrapped, 'GZIP', 'BASE64')
        );
        $this->assertSame(
            self::JSON_BODY,
            $this->client->decompressWebhookBody($wrapped, 'gzip', 'b64')
        );
    }

    public function testDecompressWebhookBodyRoundTripsBase64Only(): void
    {
        $wrapped = base64_encode(self::JSON_BODY);

        $this->assertSame(
            self::JSON_BODY,
            $this->client->decompressWebhookBody($wrapped, null, 'base64')
        );
        $this->assertSame(
            self::JSON_BODY,
            $this->client->decompressWebhookBody($wrapped, '', 'base64')
        );
    }

    /**
     * @dataProvider unsupportedPayloadEncodings
     */
    public function testDecompressWebhookBodyRejectsUnsupportedPayloadEncoding(string $payloadEncoding): void
    {
        try {
            $this->client->decompressWebhookBody(self::JSON_BODY, null, $payloadEncoding);
            $this->fail("expected StreamException for payload_encoding '$payloadEncoding'");
        } catch (StreamException $e) {
            $this->assertStringContainsString('payload_encoding', $e->getMessage());
        }
    }

    public static function unsupportedPayloadEncodings(): array
    {
        return [
            'hex'    => ['hex'],
            'url'    => ['url'],
            'binary' => ['binary'],
        ];
    }

    public function testDecompressWebhookBodyThrowsOnInvalidBase64(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/base64-decode/');
        $this->client->decompressWebhookBody('not!valid!base64', null, 'base64');
    }

    public function testVerifyAndDecodeWebhookBase64GzipHappyPath(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sig = $this->sign(self::JSON_BODY);

        $decoded = $this->client->verifyAndDecodeWebhook($wrapped, $sig, 'gzip', 'base64');
        $this->assertSame(self::JSON_BODY, $decoded);
    }

    public function testVerifyAndDecodeWebhookRejectsSignatureOverWrappedBytes(): void
    {
        $compressed = gzencode(self::JSON_BODY);
        $wrapped = base64_encode($compressed);
        $sigOverWrapped = hash_hmac('sha256', $wrapped, self::API_SECRET);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessageMatches('/invalid webhook signature/');
        $this->client->verifyAndDecodeWebhook($wrapped, $sigOverWrapped, 'gzip', 'base64');
    }
}
