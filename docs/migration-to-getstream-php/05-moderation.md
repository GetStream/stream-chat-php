# Moderation

This guide covers migrating moderation operations from `stream-chat-php` to `getstream-php`.

> All `getstream-php` examples assume the client is already instantiated. See [01-setup-and-auth.md](./01-setup-and-auth.md) for client setup.

## Adding and Removing Moderators

### Adding Moderators to a Channel

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->addModerators(['user-1', 'user-2']);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    addModerators: ['user-1', 'user-2'],
));
```

**Key differences:**
- Old SDK uses a dedicated `addModerators()` method on the Channel object
- New SDK uses `updateChannel()` with the `addModerators` parameter on `UpdateChannelRequest`
- No separate Channel object in the new SDK; channel type and ID are passed directly

### Demoting Moderators

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->demoteModerators(['user-1', 'user-2']);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateChannel('messaging', 'general', new GeneratedModels\UpdateChannelRequest(
    demoteModerators: ['user-1', 'user-2'],
));
```

**Key differences:**
- Same pattern as adding moderators: old SDK has `demoteModerators()` on Channel, new SDK uses `updateChannel()` with `demoteModerators` parameter

## Banning and Unbanning Users

### App-level Ban

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Ban a user app-wide
$response = $client->banUser('bad-user', [
    'user_id' => 'admin-user',
    'reason' => 'Spamming',
    'timeout' => 60, // minutes
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Ban a user app-wide
$response = $client->ban(new GeneratedModels\BanRequest(
    targetUserID: 'bad-user',
    bannedByID: 'admin-user',
    reason: 'Spamming',
    timeout: 60, // minutes
));
```

**Key differences:**
- Old SDK: `banUser($targetId, $options)` with flat associative array options
- New SDK: `ban(BanRequest)` with typed named arguments
- `user_id` in old SDK becomes `bannedByID` in new SDK
- The new SDK method lives on the moderation trait (`$client->ban()`), not under a `banUser` name

### Channel-level Ban

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->banUser('bad-user', [
    'user_id' => 'admin-user',
    'reason' => 'Off-topic messages',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->ban(new GeneratedModels\BanRequest(
    targetUserID: 'bad-user',
    bannedByID: 'admin-user',
    reason: 'Off-topic messages',
    channelCid: 'messaging:general',
));
```

**Key differences:**
- Old SDK: `$channel->banUser()` automatically includes channel type/ID from the Channel object
- New SDK: use the same `ban()` method but pass `channelCid` (format: `type:id`) to scope the ban to a channel

### Unbanning a User (App-level)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->unbanUser('bad-user');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->unban(
    targetUserID: 'bad-user',
    channelCid: '',
    createdBy: 'admin-user',
    requestData: new GeneratedModels\UnbanRequest(),
);
```

**Key differences:**
- Old SDK: `unbanUser($targetId)` with optional options array
- New SDK: `unban()` requires `targetUserID`, `channelCid` (empty string for app-level), `createdBy`, and an `UnbanRequest` object
- The new SDK requires explicitly specifying who is performing the unban (`createdBy`)

### Unbanning a User (Channel-level)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$channel = $client->Channel('messaging', 'general');
$response = $channel->unbanUser('bad-user');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->unban(
    targetUserID: 'bad-user',
    channelCid: 'messaging:general',
    createdBy: 'admin-user',
    requestData: new GeneratedModels\UnbanRequest(),
);
```

**Key differences:**
- Old SDK: `$channel->unbanUser()` scopes the unban to the channel automatically
- New SDK: pass `channelCid` as `type:id` to scope the unban to a channel

### Shadow Banning

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Shadow ban (app-level)
$response = $client->shadowBan('bad-user', [
    'user_id' => 'admin-user',
]);

// Remove shadow ban
$response = $client->removeShadowBan('bad-user', [
    'user_id' => 'admin-user',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Shadow ban (app-level)
$response = $client->ban(new GeneratedModels\BanRequest(
    targetUserID: 'bad-user',
    bannedByID: 'admin-user',
    shadow: true,
));

// Remove shadow ban
$response = $client->unban(
    targetUserID: 'bad-user',
    channelCid: '',
    createdBy: 'admin-user',
    requestData: new GeneratedModels\UnbanRequest(),
);
```

**Key differences:**
- Old SDK has dedicated `shadowBan()` and `removeShadowBan()` methods
- New SDK uses the same `ban()` method with `shadow: true`
- Removing a shadow ban uses the same `unban()` method (no separate method needed)

### Querying Banned Users

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->queryBannedUsers(
    ['channel_cid' => 'messaging:general'],
    ['limit' => 10, 'offset' => 0],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->queryBannedUsers(new GeneratedModels\QueryBannedUsersPayload(
    filterConditions: (object) ['channel_cid' => 'messaging:general'],
    limit: 10,
    offset: 0,
));
```

**Key differences:**
- Old SDK: filter conditions and options are separate array arguments
- New SDK: everything goes into a single `QueryBannedUsersPayload` object with named arguments
- Filter conditions must be cast to `(object)` in the new SDK

## Muting and Unmuting Users

### Muting a User

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->muteUser('target-user', 'requesting-user');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->mute(new GeneratedModels\MuteRequest(
    targetIds: ['target-user'],
    userID: 'requesting-user',
));
```

**Key differences:**
- Old SDK: `muteUser($targetId, $userId)` takes two string arguments
- New SDK: `mute(MuteRequest)` takes a typed request object
- New SDK accepts an array of `targetIds`, allowing you to mute multiple users in one call
- New SDK supports an optional `timeout` parameter (duration in minutes)

### Muting a User with Timeout

**Before (stream-chat-php):**

```php
// The old SDK does not support mute timeouts directly.
// You would need to handle expiration on your own.
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->mute(new GeneratedModels\MuteRequest(
    targetIds: ['target-user'],
    userID: 'requesting-user',
    timeout: 60, // mute for 60 minutes
));
```

**Key differences:**
- Mute timeout is a new capability in `getstream-php` that was not available in the old SDK

### Unmuting a User

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->unmuteUser('target-user', 'requesting-user');
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->unmute(new GeneratedModels\UnmuteRequest(
    targetIds: ['target-user'],
    userID: 'requesting-user',
));
```

**Key differences:**
- Old SDK: `unmuteUser($targetId, $userId)` takes two string arguments
- New SDK: `unmute(UnmuteRequest)` takes a typed request object
- New SDK accepts an array of `targetIds`, allowing you to unmute multiple users in one call

## Summary of Method Mapping

| Operation | stream-chat-php | getstream-php |
|---|---|---|
| Add moderators | `$channel->addModerators($userIds)` | `$client->updateChannel($type, $id, new UpdateChannelRequest(addModerators: [...]))` |
| Demote moderators | `$channel->demoteModerators($userIds)` | `$client->updateChannel($type, $id, new UpdateChannelRequest(demoteModerators: [...]))` |
| Ban user (app) | `$client->banUser($targetId, $opts)` | `$client->ban(new BanRequest(targetUserID: ..., ...))` |
| Ban user (channel) | `$channel->banUser($targetId, $opts)` | `$client->ban(new BanRequest(targetUserID: ..., channelCid: ..., ...))` |
| Unban user (app) | `$client->unbanUser($targetId)` | `$client->unban($targetUserID, '', $createdBy, new UnbanRequest())` |
| Unban user (channel) | `$channel->unbanUser($targetId)` | `$client->unban($targetUserID, $channelCid, $createdBy, new UnbanRequest())` |
| Shadow ban | `$client->shadowBan($targetId, $opts)` | `$client->ban(new BanRequest(targetUserID: ..., shadow: true, ...))` |
| Remove shadow ban | `$client->removeShadowBan($targetId, $opts)` | `$client->unban($targetUserID, '', $createdBy, new UnbanRequest())` |
| Query banned users | `$client->queryBannedUsers($filter, $opts)` | `$client->queryBannedUsers(new QueryBannedUsersPayload(...))` |
| Mute user | `$client->muteUser($targetId, $userId)` | `$client->mute(new MuteRequest(targetIds: [...], userID: ...))` |
| Unmute user | `$client->unmuteUser($targetId, $userId)` | `$client->unmute(new UnmuteRequest(targetIds: [...], userID: ...))` |
