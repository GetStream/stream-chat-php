<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

/** Class for encoding JWTs.
 */
class JwtHandler
{
    /**
     * @var string
     */
    private $header = "{\"alg\":\"HS256\",\"typ\":\"JWT\"}";

    /** Creates a server side JWT with 'server: true' payload.
     */
    public function createServerSideToken(string $secret): string
    {
        return $this->encode($secret, ["server" => "true"]);
    }

    /** Creates a new JWT.
     */
    public function encode(string $secret, array $payload): string
    {
        // Encode Header
        $base64UrlHeader = $this->base64UrlEncode($this->header);

        // Encode Payload
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        // HMAC Signature
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

        // Encode Signature to Base64Url String
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function base64UrlEncode(string $text): string
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }
}
