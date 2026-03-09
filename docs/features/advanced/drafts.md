Draft messages allow users to save messages as drafts for later use. This feature is useful when users want to compose a message but aren't ready to send it yet.

## Creating a draft message

It is possible to create a draft message for a channel or a thread. Only one draft per channel/thread can exist at a time, so a newly created draft overrides the existing one.

```php
// Create/update a draft message in a channel
$message = ["text" => "This is a draft message"];
$response = $channel->createDraft($message, $userId);

// Create/update a draft message in a thread (parent message)
$draftReply = ["text" => "This is a draft reply", "parent_id" => $parentMessageId];
$response = $channel->createDraft($draftReply, $userId);
```

## Deleting a draft message

You can delete a draft message for a channel or a thread as well.

```php
// Delete the draft message for a channel
$channel->deleteDraft($userId);

// Delete the draft message for a thread
$channel->deleteDraft($userId, $parentMessageId);
```

## Loading a draft message

It is also possible to load a draft message for a channel or a thread. Although, when querying channels, each channel will contain the draft message payload, in case there is one. The same for threads (parent messages). So, for the most part this function will not be needed.

```php
// Load the draft message for a channel
$response = $channel->getDraft($userId);

// Load the draft message for a thread
$response = $channel->getDraft($userId, $parentMessageId);
```

## Querying draft messages

The Stream Chat SDK provides a way to fetch all the draft messages for the current user. This can be useful to for the current user to manage all the drafts they have in one place.

```php
// Query all user drafts
$response = $client->queryDrafts($userId);

// Query drafts for certain channels and sort
$response = $client->queryDrafts(
    $userId,
    ["channel_cid" => $channel->getCID()],
    [["field" => "created_at", "direction" => 1]]
);

// Query drafts with pagination
$response = $client->queryDrafts(
    $userId,
    options: ["limit" => 1]
);

// Query drafts with pagination and next
$response = $client->queryDrafts(
    $userId,
    options: ["limit" => 1, "next" => $response["next"]]
);
```

Filtering is possible on the following fields:

| Name        | Type                       | Description                    | Supported operations      | Example                                                |
| ----------- | -------------------------- | ------------------------------ | ------------------------- | ------------------------------------------------------ |
| channel_cid | string                     | the ID of the message          | $in, $eq                  | { channel_cid: { $in: [ 'channel-1', 'channel-2' ] } } |
| parent_id   | string                     | the ID of the parent message   | $in, $eq, $exists         | { parent_id: 'parent-message-id' }                     |
| created_at  | string (RFC3339 timestamp) | the time the draft was created | $eq, $gt, $lt, $gte, $lte | { created_at: { $gt: '2024-04-24T15:50:00.00Z' }       |

Sorting is possible on the `created_at` field. By default, draft messages are returned with the newest first.

### Pagination

In case the user has a lot of draft messages, you can paginate the results.

```php
// Query drafts with a limit
$response = $client->queryDrafts($userId, options: ["limit" => 5]);

// Query the next page
$response = $client->queryDrafts($userId, options: ["limit" => 5, "next" => $response["next"]]);
```

## Events

The following WebSocket events are available for draft messages:

- `draft.updated`, triggered when a draft message is updated.
- `draft.deleted`, triggered when a draft message is deleted.

You can subscribe to these events using the Stream Chat SDK.
