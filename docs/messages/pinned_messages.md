Pinned messages highlight important content in a channel. Use them for announcements, key information, or temporarily promoted content. Each channel can have multiple pinned messages, with optional expiration times.

## Pinning and Unpinning Messages

Pin an existing message using `pinMessage`, or create a pinned message by setting `pinned: true` when sending.

```php
$response = $channel->sendMessage([
  'text' => 'Important announcement',
  'pinned' => true,
  'pin_expires' => "2077-01-01T00:00:00Z",
], $user["id"]);

// Pin message for 120 seconds
$client->pinMessage($response["message"]["id"], $user["id"], 120);

// Unpin message
$client->unPinMessage($response["message"]["id"], $user["id"]);
```

### Pin Parameters

| Name        | Type    | Description                                                            | Default | Optional |
| ----------- | ------- | ---------------------------------------------------------------------- | ------- | -------- |
| pinned      | boolean | Whether the message is pinned                                          | false   | ✓        |
| pinned_at   | string  | Timestamp when the message was pinned                                  | -       | ✓        |
| pin_expires | string  | Timestamp when the pin expires. Null means the message does not expire | null    | ✓        |
| pinned_by   | object  | The user who pinned the message                                        | -       | ✓        |

> [!NOTE]
> Pinning a message requires the `PinMessage` permission. See [Permission Resources](/chat/docs/php/permissions_reference/) and [Default Permissions](/chat/docs/php/chat_permission_policies/) for details.


## Retrieving Pinned Messages

Query a channel to retrieve the 10 most recent pinned messages from `pinned_messages`.

```php
$response = $channel->query([
  "watch" => false,
  "state" => false,
  "presence" => false
]);
$pinnedMessages = $response["pinned_messages"];
```

## Paginating Pinned Messages

Use the dedicated pinned messages endpoint to retrieve all pinned messages with pagination.
