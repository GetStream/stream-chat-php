# Channels

This guide covers migrating channel operations from `stream-chat-php` to `getstream-php`.

> All `getstream-php` examples assume the client is already instantiated. See [01-setup-and-auth.md](./01-setup-and-auth.md) for client setup.

## Channel Type Creation

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->createChannelType([
    'name' => 'support',
    'typing_events' => true,
    'read_events' => true,
    'connect_events' => true,
    'search' => true,
    'reactions' => true,
    'replies' => true,
    'mutes' => true,
    'commands' => ['all'],
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->createChannelType(new GeneratedModels\CreateChannelTypeRequest(
    name: 'support',
    typingEvents: true,
    readEvents: true,
    connectEvents: true,
    search: true,
    reactions: true,
    replies: true,
    mutes: true,
    commands: ['all'],
));
```

**Key differences:**

- The old SDK accepts a flat associative array. The new SDK uses a typed `CreateChannelTypeRequest` with named arguments.
- Option keys change from snake_case strings to camelCase named arguments.
- The old SDK defaults `commands` to `['all']` if not provided. The new SDK does not auto-set this.

## Listing Channel Types

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->listChannelTypes();
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->listChannelTypes();
```

**Key differences:**

- The method name and signature are identical. No changes needed.

## Getting a Channel Type

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->getChannelType('support');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->getChannelType('support');
```

**Key differences:**

- The method name and signature are identical. No changes needed.

## Updating a Channel Type

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->updateChannelType('support', [
    'typing_events' => false,
    'read_events' => true,
    'replies' => false,
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannelType('support', new GeneratedModels\UpdateChannelTypeRequest(
    typingEvents: false,
    readEvents: true,
    replies: false,
));
```

**Key differences:**

- Settings change from a flat associative array to a typed `UpdateChannelTypeRequest` with named arguments.

## Deleting a Channel Type

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->deleteChannelType('support');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteChannelType('support');
```

**Key differences:**

- The method name and signature are identical. No changes needed.

## Creating a Channel with Members

In the old SDK, you create a `Channel` object on the client and then call `create()` on it. In the new SDK, you call `getOrCreateChannel()` directly on the client.

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->create('admin-user', ['user-1', 'user-2']);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->getOrCreateChannel('messaging', 'general', new GeneratedModels\ChannelGetOrCreateRequest(
    data: new GeneratedModels\ChannelInput(
        createdByID: 'admin-user',
        members: [
            new GeneratedModels\ChannelMemberRequest(userID: 'user-1'),
            new GeneratedModels\ChannelMemberRequest(userID: 'user-2'),
        ],
    ),
));
```

**Key differences:**

- The old SDK uses a two-step pattern: `$client->Channel()` returns a `Channel` object, then `$channel->create()` creates it. The new SDK uses a single `getOrCreateChannel()` call.
- Members are `ChannelMemberRequest` objects (with optional `channelRole` and `custom` properties) instead of plain user ID strings.
- The creating user is specified via `createdByID` on `ChannelInput` rather than as the first argument to `create()`.

### Creating a Distinct Channel (Without an ID)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Pass null as the channel ID to create a distinct channel based on members
$channel = $client->Channel('messaging', null, ['members' => ['user-1', 'user-2']]);
$response = $channel->create('user-1');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Use getOrCreateDistinctChannel (no channel ID needed)
$response = $client->getOrCreateDistinctChannel('messaging', new GeneratedModels\ChannelGetOrCreateRequest(
    data: new GeneratedModels\ChannelInput(
        createdByID: 'user-1',
        members: [
            new GeneratedModels\ChannelMemberRequest(userID: 'user-1'),
            new GeneratedModels\ChannelMemberRequest(userID: 'user-2'),
        ],
    ),
));
```

**Key differences:**

- The old SDK passes `null` as the channel ID and includes members in the data array. The new SDK has a dedicated `getOrCreateDistinctChannel()` method that omits the channel ID parameter entirely.

## Adding Members

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->addMembers(['user-3', 'user-4']);

// With options (e.g. hide history)
$response = $channel->addMembers(['user-5'], [
    'hide_history' => true,
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    addMembers: [
        new GeneratedModels\ChannelMemberRequest(userID: 'user-3'),
        new GeneratedModels\ChannelMemberRequest(userID: 'user-4'),
    ],
));

// With options (e.g. hide history)
$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    addMembers: [
        new GeneratedModels\ChannelMemberRequest(userID: 'user-5'),
    ],
    hideHistory: true,
));
```

**Key differences:**

- The old SDK calls `addMembers()` on a `Channel` object with an array of user ID strings. The new SDK calls `updateChannel()` on the client with `addMembers` containing `ChannelMemberRequest` objects.
- Options like `hide_history` become named arguments on the `UpdateChannelRequest`.

## Removing Members

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->removeMembers(['user-3', 'user-4']);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    removeMembers: ['user-3', 'user-4'],
));
```

**Key differences:**

- The old SDK calls `removeMembers()` on a `Channel` object. The new SDK passes `removeMembers` as a named argument to `updateChannel()`.
- Unlike `addMembers`, `removeMembers` takes plain user ID strings in both SDKs.

## Querying Channels

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Basic query
$response = $client->queryChannels(
    ['type' => 'messaging', 'members' => ['$in' => ['user-1']]],
);

// With sort and pagination
$response = $client->queryChannels(
    ['type' => 'messaging'],
    ['last_message_at' => -1],
    ['limit' => 10, 'offset' => 0],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Basic query
$response = $client->queryChannels(new GeneratedModels\QueryChannelsRequest(
    filterConditions: (object) [
        'type' => 'messaging',
        'members' => (object) ['$in' => ['user-1']],
    ],
));

// With sort and pagination
$response = $client->queryChannels(new GeneratedModels\QueryChannelsRequest(
    filterConditions: (object) ['type' => 'messaging'],
    sort: [
        new GeneratedModels\SortParamRequest(
            field: 'last_message_at',
            direction: -1,
        ),
    ],
    limit: 10,
    offset: 0,
));

$channels = $response->getData()->channels;
```

**Key differences:**

- Filter conditions must be cast to `(object)`. The old SDK accepts plain associative arrays.
- Sort is now an array of `SortParamRequest` objects instead of an associative array.
- Pagination options (`limit`, `offset`) are named parameters on the request instead of a separate `$options` array.
- The old SDK auto-sets `state`, `watch`, and `presence` defaults. The new SDK leaves them as null unless explicitly provided.

## Updating a Channel

### Full Update

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->update(
    ['name' => 'General Chat', 'image' => 'https://example.com/img.png'],
    ['text' => 'Channel updated', 'user_id' => 'admin-user'],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    message: new GeneratedModels\MessageRequest(
        text: 'Channel updated',
        userID: 'admin-user',
    ),
));
```

> **Note:** In the new SDK, channel data fields like `name` and `image` are not set via `updateChannel()`. Use `updateChannelPartial()` (see below) to update individual channel properties, or pass a `data` parameter with a `ChannelInput` if a full overwrite is needed.

### Partial Update

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->updatePartial(
    ['name' => 'Updated Name', 'color' => 'blue'],  // set
    ['old_field'],                                     // unset
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannelPartial('messaging', 'general', new GeneratedModels\UpdateChannelPartialRequest(
    set: (object) ['name' => 'Updated Name', 'color' => 'blue'],
    unset: ['old_field'],
));
```

**Key differences:**

- The old SDK calls `updatePartial()` on a `Channel` object with positional `$set` and `$unset` arrays. The new SDK calls `updateChannelPartial()` on the client with a typed request object.
- The `set` property must be cast to `(object)`.

## Deleting Channels

### Single Channel

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->delete();
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteChannel('messaging', 'general', hardDelete: false);
```

**Key differences:**

- The old SDK calls `delete()` on a `Channel` object. The new SDK calls `deleteChannel()` on the client with the channel type and ID as arguments.
- The new SDK requires an explicit `hardDelete` boolean parameter.

### Batch Delete

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->deleteChannels(['messaging:general', 'messaging:support']);

// Hard delete
$response = $client->deleteChannels(
    ['messaging:general', 'messaging:support'],
    ['hard_delete' => true],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteChannels(new GeneratedModels\DeleteChannelsRequest(
    cids: ['messaging:general', 'messaging:support'],
));

// Hard delete
$response = $client->deleteChannels(new GeneratedModels\DeleteChannelsRequest(
    cids: ['messaging:general', 'messaging:support'],
    hardDelete: true,
));
```

**Key differences:**

- The old SDK takes a plain array of CIDs and an optional options array. The new SDK uses a typed `DeleteChannelsRequest` with named arguments.

## Summary of Method Changes

| Operation | stream-chat-php | getstream-php |
|-----------|-----------------|---------------|
| Create channel type | `$client->createChannelType($data)` | `$client->createChannelType(new CreateChannelTypeRequest(...))` |
| List channel types | `$client->listChannelTypes()` | `$client->listChannelTypes()` |
| Get channel type | `$client->getChannelType($name)` | `$client->getChannelType($name)` |
| Update channel type | `$client->updateChannelType($name, $settings)` | `$client->updateChannelType($name, new UpdateChannelTypeRequest(...))` |
| Delete channel type | `$client->deleteChannelType($name)` | `$client->deleteChannelType($name)` |
| Create channel | `$client->Channel($type, $id)->create($userId, $members)` | `$client->getOrCreateChannel($type, $id, new ChannelGetOrCreateRequest(...))` |
| Create distinct channel | `$client->Channel($type, null, $data)->create($userId)` | `$client->getOrCreateDistinctChannel($type, new ChannelGetOrCreateRequest(...))` |
| Add members | `$channel->addMembers($userIds)` | `$client->updateChannel($type, $id, new UpdateChannelRequest(addMembers: [...]))` |
| Remove members | `$channel->removeMembers($userIds)` | `$client->updateChannel($type, $id, new UpdateChannelRequest(removeMembers: [...]))` |
| Query channels | `$client->queryChannels($filter, $sort, $opts)` | `$client->queryChannels(new QueryChannelsRequest(...))` |
| Update channel | `$channel->update($data, $message)` | `$client->updateChannel($type, $id, new UpdateChannelRequest(...))` |
| Partial update channel | `$channel->updatePartial($set, $unset)` | `$client->updateChannelPartial($type, $id, new UpdateChannelPartialRequest(...))` |
| Delete channel | `$channel->delete()` | `$client->deleteChannel($type, $id, hardDelete: false)` |
| Batch delete channels | `$client->deleteChannels($cids, $opts)` | `$client->deleteChannels(new DeleteChannelsRequest(...))` |
