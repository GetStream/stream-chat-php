Channel members are users who have been added to a channel and can participate in conversations. This page covers how to manage channel membership, including adding and removing members, controlling message history visibility, and managing member roles.

## Adding and Removing Members

### Adding Members

Using the `addMembers()` method adds the given users as members to a channel.

```php
$channel->addMembers(['thierry', 'jenny']);
```

> [!NOTE]
> **Note:** You can only add/remove up to 100 members at once.


Members can also be added when creating a channel:


### Removing Members

Using the `removeMembers()` method removes the given users from the channel.

```php
$channel->removeMembers(['thierry', 'jenny']);
```

### Leaving a Channel

Users can leave a channel without moderator-level permissions. Ensure channel members have the `Leave Own Channel` permission enabled.


> [!NOTE]
> You can familiarize yourself with all permissions in the [Permissions section](/chat/docs/php/chat_permission_policies/).


## Hide History

When members join a channel, you can specify whether they have access to the channel's message history. By default, new members can see the history. Set `hide_history` to `true` to hide it for new members.

```php
$channel->addMembers(["thierry"], ["hide_history" => true]);
```

### Hide History Before a Specific Date

Alternatively, `hide_history_before` can be used to hide any history before a given timestamp while giving members access to later messages. The value must be a timestamp in the past in RFC 3339 format. If both parameters are defined, `hide_history_before` takes precedence over `hide_history`.

```php
$cutoff = new DateTime('-7 days'); // Last 7 days
$channel->addMembers(["thierry"], ["hide_history_before" => $cutoff]);
```

## System Message Parameter

You can optionally include a message object when adding or removing members that client-side SDKs will use to display a system message. This works for both adding and removing members.

```php
$channel->addMembers(['tommaso'], ["message" =>
  ["text" => "Tommaso joined the channel.", "user_id" => "tommaso"]
 ]);
```

## Adding and Removing Moderators

Using the `addModerators()` method adds the given users as moderators (or updates their role to moderator if already members), while `demoteModerators()` removes the moderator status.

### Add Moderators

```php
$channel->addModerators(['thierry', 'jenny']);
```

### Remove Moderators

```php
$channel->demoteModerators(['thierry', 'jenny']);
```

> [!NOTE]
> These operations can only be performed server-side, and a maximum of 100 moderators can be added or removed at once.


## Member Custom Data

Custom data can be added at the channel member level. This is useful for storing member-specific information that is separate from user-level data. Ensure custom data does not exceed 5KB.

### Adding Custom Data


### Updating Member Data

Channel members can be partially updated. Only custom data and channel roles are eligible for modification. You can set or unset fields, either separately or in the same call.

```php
$userId = "amy";

// Set some fields
$response = $this->channel->updateMemberPartial($userId, ["hat" => "blue"]);

// Unset some fields
$response = $this->channel->updateMemberPartial($userId, null, ["hat"]);

// Set and unset in the same call
$response = $this->channel->updateMemberPartial($userId, ["hat" => "blue"], ["hat"]);
```
