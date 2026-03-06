# Migrating from stream-chat-php to getstream-php

## Why Migrate?

Stream has released **[getstream-php](https://github.com/GetStream/getstream-php)**, a new full-product PHP SDK that covers Chat, Video, Moderation, and Feeds in a single package. It is generated from OpenAPI specs, which means it stays up to date with the latest API features automatically.

**getstream-php** is the long-term-supported SDK going forward. **stream-chat-php** will enter maintenance mode and continue receiving critical bug fixes, but new features and API coverage will only be added to getstream-php.

If you are starting a new project, use **getstream-php**. If you have an existing project using stream-chat-php, we encourage you to migrate at your convenience. There is no rush, as stream-chat-php is not going away, but migrating gives you access to the latest features and the best developer experience.

## Key Differences

| | **stream-chat-php** | **getstream-php** |
|---|---|---|
| **Package** | `get-stream/stream-chat` | `getstream/getstream-php` |
| **Namespace** | `GetStream\StreamChat\Client` | `GetStream\Client` / `GetStream\ChatClient` |
| **Client init** | `new Client($apiKey, $apiSecret)` | `ClientBuilder::fromEnv()->build()` or `new Client(apiKey: ..., apiSecret: ...)` |
| **API style** | Associative arrays for everything | Typed model classes with named arguments |
| **Channel operations** | `$client->Channel($type, $id)->method()` | `$client->methodName($type, $id, ...)` |
| **Custom fields** | Top-level array keys | `custom: (object) [...]` property |
| **Filters / Sort** | Associative arrays | `(object)` casts and `SortParamRequest` objects |
| **Response types** | Associative arrays | Typed response objects |
| **Autoloading** | PSR-0 | PSR-4 |
| **Product coverage** | Chat only | Chat, Video, Moderation, Feeds |

## Quick Before/After Example

The most common operation (initialize client and send a message):

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->sendMessage(
    ['text' => 'Hello, world!'],
    'user-1',
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->sendMessage(
    'messaging',
    'general',
    new GeneratedModels\SendMessageRequest(
        message: new GeneratedModels\MessageRequest(
            text: 'Hello, world!',
            userID: 'user-1',
        ),
    ),
);
```

## Migration Guides by Topic

| # | Topic | Guide |
|---|-------|-------|
| 1 | Setup and Authentication | [01-setup-and-auth.md](./01-setup-and-auth.md) |
| 2 | Users | [02-users.md](./02-users.md) |
| 3 | Channels | [03-channels.md](./03-channels.md) |
| 4 | Messages and Reactions | [04-messages-and-reactions.md](./04-messages-and-reactions.md) |
| 5 | Moderation | [05-moderation.md](./05-moderation.md) |
| 6 | Devices | [06-devices.md](./06-devices.md) |

Each guide provides side-by-side "Before" and "After" code examples for every operation, along with notes on key differences.

## Continued Support for stream-chat-php

stream-chat-php is not being removed or abandoned. It will continue to receive:

- Critical bug fixes
- Security patches
- Requested features on a case-by-case basis

However, all new API features, generated model types, and multi-product support will only be available in getstream-php. We recommend migrating when it makes sense for your project timeline.

## Resources

- [getstream-php on GitHub](https://github.com/GetStream/getstream-php)
- [getstream-php on Packagist](https://packagist.org/packages/getstream/getstream-php)
- [Stream Chat documentation](https://getstream.io/chat/docs/)
