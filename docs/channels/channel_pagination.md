The channel query endpoint allows you to paginate messages, watchers, and members for a channel. Messages use ID-based pagination for consistency, while members and watchers use offset-based pagination.

## Message Pagination

Message pagination uses ID-based parameters rather than simple offset/limit. This approach improves performance and prevents issues when the message list changes while paginating.

For example, if you fetched the first 100 messages and want to load the next 100, pass the ID of the oldest message (when paginating in descending order) or the newest message (when paginating in ascending order).

### Pagination Parameters

| Parameter   | Description                                        |
| ----------- | -------------------------------------------------- |
| `id_lt`     | Retrieve messages older than (less than) the ID    |
| `id_gt`     | Retrieve messages newer than (greater than) the ID |
| `id_lte`    | Retrieve messages older than or equal to the ID    |
| `id_gte`    | Retrieve messages newer than or equal to the ID    |
| `id_around` | Retrieve messages around a specific message ID     |

```php
$channel = $client->Channel("messaging", "general");
$params = [
  "messages" => ["limit" => 20, "id_lt" => $lastMessageId],
];
$response = $channel->query($params);
```

## Member and Watcher Pagination

Members and watchers use `limit` and `offset` parameters for pagination.

| Parameter | Description                 | Maximum |
| --------- | --------------------------- | ------- |
| `limit`   | Number of records to return | 300     |
| `offset`  | Number of records to skip   | 10000   |

```php
$channel = $client->Channel("messaging", "general");
$params = [
  "members" => ["limit" => 20, "offset" => 0],
  "watchers" => ["limit" => 20, "offset" => 0],
];
$response = $channel->query($params);
```

> [!NOTE]
> To retrieve filtered and sorted members in a channel use the [Query Members](/chat/docs/php/query_members/) API
