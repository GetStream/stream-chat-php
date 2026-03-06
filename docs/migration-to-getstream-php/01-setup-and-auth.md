# Setup and Authentication

This guide covers migrating client setup, configuration, and token generation from `stream-chat-php` to `getstream-php`.

## Installation

**Before (stream-chat-php):**

```bash
composer require get-stream/stream-chat
```

**After (getstream-php):**

```bash
composer require getstream/getstream-php
```

## Client Instantiation

### Direct Constructor

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// With optional timeout (seconds)
$client = new Client("<api-key>", "<api-secret>", timeout: 5.0);
```

**After (getstream-php):**

```php
use GetStream\Client;

$client = new Client(
    apiKey: "<api-key>",
    apiSecret: "<api-secret>",
);

// With optional base URL or custom HTTP client
$client = new Client(
    apiKey: "<api-key>",
    apiSecret: "<api-secret>",
    baseUrl: "https://chat.stream-io-api.com",
);
```

### ClientBuilder (New)

The new SDK introduces a `ClientBuilder` with a fluent interface. This pattern has no equivalent in the old SDK.

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

// Build from environment variables (STREAM_API_KEY, STREAM_API_SECRET, STREAM_BASE_URL)
$client = ClientBuilder::fromEnv()->build();

// Build from a custom .env file path
$client = ClientBuilder::fromEnv('/path/to/.env')->build();

// Mix environment and explicit values
$client = ClientBuilder::fromEnv()
    ->apiKey("<api-key>")
    ->apiSecret("<api-secret>")
    ->baseUrl("<custom-url>")
    ->build();

// Skip .env file loading entirely
$client = ClientBuilder::fromEnv()
    ->skipEnvLoad()
    ->apiKey("<api-key>")
    ->apiSecret("<api-secret>")
    ->build();
```

### Environment Variables

The environment variable names have changed:

| Purpose | stream-chat-php | getstream-php |
|---------|-----------------|---------------|
| API Key | `STREAM_KEY` | `STREAM_API_KEY` |
| API Secret | `STREAM_SECRET` | `STREAM_API_SECRET` |
| Base URL | `STREAM_CHAT_URL`, `STREAM_BASE_CHAT_URL`, or `STREAM_BASE_URL` | `STREAM_BASE_URL` |
| Timeout | `STREAM_CHAT_TIMEOUT` | _(configure via HTTP client)_ |

## Namespace Changes

Update your `use` statements:

| Purpose | stream-chat-php | getstream-php |
|---------|-----------------|---------------|
| Client | `GetStream\StreamChat\Client` | `GetStream\Client` |
| Chat Client | _(same class)_ | `GetStream\ChatClient` |
| Builder | _(none)_ | `GetStream\ClientBuilder` |

`ChatClient` extends `Client` and adds all chat-specific methods via `ChatTrait`. For chat operations, you can use either `Client` or `ChatClient` directly.

## User Token Generation

### Token Without Expiration

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");
$token = $client->createToken("user-id-123");
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();
$token = $client->createUserToken("user-id-123");
```

### Token With Expiration

The expiration parameter changed from an absolute Unix timestamp to a relative duration in seconds.

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Expiration is an absolute Unix timestamp
$expiration = time() + 3600; // 1 hour from now
$token = $client->createToken("user-id-123", $expiration);

// With both expiration and issued-at
$issuedAt = time();
$expiration = $issuedAt + 3600;
$token = $client->createToken("user-id-123", $expiration, $issuedAt);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

// Expiration is a duration in seconds (relative, not absolute)
$token = $client->createUserToken(
    userId: "user-id-123",
    expiration: 3600, // expires 1 hour from now
);

// With additional custom claims
$token = $client->createUserToken(
    userId: "user-id-123",
    claims: ['role' => 'admin'],
    expiration: 3600,
);
```

> **Important:** In `stream-chat-php`, `createToken()` expects an absolute Unix timestamp for expiration. In `getstream-php`, `createUserToken()` expects a duration in seconds. If you are computing `time() + 3600` in your old code, simply pass `3600` to the new SDK.

## Summary of Method Changes

| Operation | stream-chat-php | getstream-php |
|-----------|-----------------|---------------|
| Create client | `new Client($key, $secret)` | `new Client(apiKey: $key, apiSecret: $secret)` or `ClientBuilder::fromEnv()->build()` |
| Generate user token | `$client->createToken($userId)` | `$client->createUserToken($userId)` |
| Token with expiry | `$client->createToken($userId, time() + 3600)` | `$client->createUserToken($userId, expiration: 3600)` |
| Token with claims | _(not supported)_ | `$client->createUserToken($userId, claims: [...])` |
