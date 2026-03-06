# Messages and Reactions

This guide covers migrating messaging and reaction operations from `stream-chat-php` to `getstream-php`.

> All `getstream-php` examples assume the client is already instantiated. See [01-setup-and-auth.md](./01-setup-and-auth.md) for client setup.

## Sending a Message

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

$response = $client->sendMessage('messaging', 'general', new GeneratedModels\SendMessageRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'Hello, world!',
        userID: 'user-1',
    ),
));
```

**Key differences:**

- The old SDK calls `sendMessage()` on a `Channel` object, passing the user ID as a separate argument. The new SDK calls `sendMessage()` on the client with channel type and ID, wrapping the message in a typed `SendMessageRequest`.
- The user ID moves from a standalone parameter into the `MessageRequest` as `userID`.
- Message properties change from snake_case array keys to camelCase named arguments.

## Sending a Message with Options

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->sendMessage(
    [
        'text' => 'Hello!',
        'custom_field' => 'value',
    ],
    'user-1',
    null, // parentId
    ['skip_push' => true],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->sendMessage('messaging', 'general', new GeneratedModels\SendMessageRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'Hello!',
        userID: 'user-1',
        custom: (object) ['custom_field' => 'value'],
    ),
    skipPush: true,
));
```

**Key differences:**

- Options like `skip_push` become named arguments on `SendMessageRequest` instead of a separate options array.
- Custom fields go into the `custom` property (cast to `(object)`) on `MessageRequest`, rather than being mixed in with the top-level message array.

## Replying to a Message (Threads)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');

// Reply in a thread
$response = $channel->sendMessage(
    ['text' => 'This is a thread reply'],
    'user-1',
    'parent-message-id',
);

// Reply in a thread and show in channel
$response = $channel->sendMessage(
    ['text' => 'This reply also appears in the channel', 'show_in_channel' => true],
    'user-1',
    'parent-message-id',
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Reply in a thread
$response = $client->sendMessage('messaging', 'general', new GeneratedModels\SendMessageRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'This is a thread reply',
        userID: 'user-1',
        parentID: 'parent-message-id',
    ),
));

// Reply in a thread and show in channel
$response = $client->sendMessage('messaging', 'general', new GeneratedModels\SendMessageRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'This reply also appears in the channel',
        userID: 'user-1',
        parentID: 'parent-message-id',
        showInChannel: true,
    ),
));
```

**Key differences:**

- The old SDK passes `parentId` as a separate third argument to `sendMessage()`. The new SDK sets `parentID` directly on the `MessageRequest`.
- `show_in_channel` moves from the message array into the typed `showInChannel` named argument on `MessageRequest`.

## Getting a Message

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->getMessage('message-id-123');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->getMessage('message-id-123', showDeletedMessage: false);
```

**Key differences:**

- Both SDKs call `getMessage()` on the client. The new SDK adds a required `showDeletedMessage` boolean parameter.

## Updating a Message

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->updateMessage([
    'id' => 'message-id-123',
    'text' => 'Updated message text',
    'user_id' => 'user-1',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateMessage('message-id-123', new GeneratedModels\UpdateMessageRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'Updated message text',
        userID: 'user-1',
    ),
));
```

**Key differences:**

- The old SDK passes a flat array with the message `id` included in it. The new SDK passes the message ID as the first argument and the update data as a typed `UpdateMessageRequest`.
- The message ID is no longer part of the message body; it is a separate parameter.

### Partial Update

The new SDK also supports partial message updates, which the old SDK does not have as a dedicated method.

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateMessagePartial('message-id-123', new GeneratedModels\UpdateMessagePartialRequest(
    set: (object) ['text' => 'Partially updated text', 'color' => 'blue'],
    unset: ['old_field'],
    userID: 'user-1',
));
```

## Deleting a Message

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Soft delete
$response = $client->deleteMessage('message-id-123');

// Hard delete
$response = $client->deleteMessage('message-id-123', ['hard' => true]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

// Soft delete
$response = $client->deleteMessage('message-id-123', hard: false, deletedBy: '', deleteForMe: false);

// Hard delete
$response = $client->deleteMessage('message-id-123', hard: true, deletedBy: '', deleteForMe: false);
```

**Key differences:**

- The old SDK accepts an optional options array for `hard` delete. The new SDK uses explicit named parameters: `hard`, `deletedBy`, and `deleteForMe`.
- The new SDK adds `deletedBy` (to attribute deletion to a specific user) and `deleteForMe` (to delete only for the requesting user), which were not available in the old SDK.

## Sending a Reaction

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->sendReaction(
    'message-id-123',
    ['type' => 'like'],
    'user-1',
);

// With a score
$response = $channel->sendReaction(
    'message-id-123',
    ['type' => 'like', 'score' => 5],
    'user-1',
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->sendReaction('message-id-123', new GeneratedModels\SendReactionRequest(
    reaction: new GeneratedModels\ReactionRequest(
        type: 'like',
        userID: 'user-1',
    ),
));

// With a score
$response = $client->sendReaction('message-id-123', new GeneratedModels\SendReactionRequest(
    reaction: new GeneratedModels\ReactionRequest(
        type: 'like',
        score: 5,
        userID: 'user-1',
    ),
));
```

**Key differences:**

- The old SDK calls `sendReaction()` on a `Channel` object with the reaction as a flat array and user ID as a separate argument. The new SDK calls `sendReaction()` on the client with a typed `SendReactionRequest` wrapping a `ReactionRequest`.
- The user ID moves from a standalone parameter into the `ReactionRequest` as `userID`.
- The new SDK adds `enforceUnique` on `SendReactionRequest` to replace all existing reactions by the user, and `skipPush` to suppress push notifications.

## Listing Reactions

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');

// Get reactions for a message
$response = $channel->getReactions('message-id-123');

// With pagination
$response = $channel->getReactions('message-id-123', ['limit' => 10, 'offset' => 0]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

// Get reactions for a message
$response = $client->getReactions('message-id-123', limit: 25, offset: 0);

// With pagination
$response = $client->getReactions('message-id-123', limit: 10, offset: 0);
```

**Key differences:**

- The old SDK calls `getReactions()` on a `Channel` object with an optional options array. The new SDK calls `getReactions()` on the client with explicit `limit` and `offset` named parameters.
- Pagination parameters are no longer bundled in an associative array.

## Deleting a Reaction

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->deleteReaction('message-id-123', 'like', 'user-1');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteReaction('message-id-123', type: 'like', userID: 'user-1');
```

**Key differences:**

- The old SDK calls `deleteReaction()` on a `Channel` object. The new SDK calls it on the client directly.
- The parameters are the same (message ID, reaction type, user ID), but the new SDK uses named arguments.

## Summary of Method Changes

| Operation | stream-chat-php | getstream-php |
|-----------|-----------------|---------------|
| Send message | `$channel->sendMessage($msg, $userId)` | `$client->sendMessage($type, $id, new SendMessageRequest(...))` |
| Send thread reply | `$channel->sendMessage($msg, $userId, $parentId)` | `$client->sendMessage($type, $id, new SendMessageRequest(message: new MessageRequest(parentID: ...)))` |
| Get message | `$client->getMessage($id)` | `$client->getMessage($id, showDeletedMessage: false)` |
| Update message | `$client->updateMessage($msg)` | `$client->updateMessage($id, new UpdateMessageRequest(...))` |
| Partial update message | _(not available)_ | `$client->updateMessagePartial($id, new UpdateMessagePartialRequest(...))` |
| Delete message | `$client->deleteMessage($id, $opts)` | `$client->deleteMessage($id, hard: false, deletedBy: '', deleteForMe: false)` |
| Send reaction | `$channel->sendReaction($msgId, $reaction, $userId)` | `$client->sendReaction($msgId, new SendReactionRequest(...))` |
| List reactions | `$channel->getReactions($msgId, $opts)` | `$client->getReactions($msgId, limit: 25, offset: 0)` |
| Delete reaction | `$channel->deleteReaction($msgId, $type, $userId)` | `$client->deleteReaction($msgId, type: $type, userID: $userId)` |
