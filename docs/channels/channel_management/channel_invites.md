Invites allow you to add users to a channel with a pending state. The invited user receives a notification and can accept or reject the invite.

Unread counts are not incremented for channels with a pending invite.

## Invite Users

```php
$channel->inviteMembers(['thierry']);
```

## Accept an Invite

Call `acceptInvite` to accept a pending invite. You can optionally include a `message` parameter to post a system message when the user joins (e.g., "Nick joined this channel!").

```php
// initialize the channel
$channel = $client->Channel('messaging', 'team-chat-5');

// accept the invite
$accept = $channel->acceptInvite(['user_id'=>'elon']);
```

## Reject an Invite

Call `rejectInvite` to decline a pending invite. Client-side calls use the currently connected user. Server-side calls require a `user_id` parameter.

```php
$reject = $channel->rejectInvite(['user_id'=>'elon']);
```

## Query Invites by Status

Use `queryChannels` with the `invite` filter to retrieve channels based on invite status. Valid values are `pending`, `accepted`, and `rejected`.

### Query Accepted Invites

```php
$invites = $client->queryChannels(['invite' => 'accepted'],[], ['user_id' => 'jenny']);
```

### Query Rejected Invites

```php
$invites = $client->queryChannels(['invite' => 'rejected'],[], ['user_id' => 'jenny']);
```

### Query Pending Invites

```php
$invites = $client->queryChannels(['invite' => 'pending'],[], ['user_id' => 'jenny']);
```
