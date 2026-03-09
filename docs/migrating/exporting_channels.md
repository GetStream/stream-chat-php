Export channels and users to retrieve messages, metadata, and associated data. All exports run asynchronously and return a task ID for tracking status.

> [!NOTE]
> All export endpoints require server-side authentication.


## Exporting Channels

```php
$response = $client->exportChannel([
  'type' => 'livestream',
  'id' => 'white-room',
  'messages_since' => '2020-11-10T09:30:00.000Z',
  'messages_until' => '2020-11-10T11:30:00.000Z'
  ],
  ['include_truncated_messages' => true]
);

$taskId = $response['task_id'];
```

### Channel Export Options

| Parameter                       | Description                                                 |
| ------------------------------- | ----------------------------------------------------------- |
| `type`                          | Channel type (required)                                     |
| `id`                            | Channel ID (required)                                       |
| `messages_since`                | Export messages after this timestamp (RFC3339 format)       |
| `messages_until`                | Export messages before this timestamp (RFC3339 format)      |
| `include_truncated_messages`    | Include messages that were truncated (default: `false`)     |
| `include_soft_deleted_channels` | Include soft-deleted channels (default: `false`)            |
| `version`                       | Export format: `v1` (default) or `v2` (line-separated JSON) |

> [!NOTE]
> A single request can export up to 25 channels.


### Export Format (v2)

Add `version: "v2"` for line-separated JSON output, where each entity appears on its own line.

```php
$response = $client->exportChannel([
  'type' => 'livestream',
  'id' => 'white-room',
  ],
  ['version' => 'v2']
);
```

### Checking Export Status

Poll the task status using the returned task ID. When the task completes, the response includes a URL to download the JSON export file.

```php
$response = $client->getExportChannelStatus($taskId);

echo $response['status'];          // Task status
echo $response['result'];          // Result object (when completed)
echo $response['result']['url'];   // Download URL
echo $response['error'];           // Error description (if failed)
```

> [!NOTE]
> - Download URLs expire after 24 hours but are regenerated on each status request
> - Export files remain available for 60 days
> - Timestamps use UTC in RFC3339 format (e.g., `2021-02-17T08:17:49.745857Z`)


## Exporting Users

Export user data including messages, reactions, calls, and custom data. The export uses line-separated JSON format (same as channel export v2).


> [!NOTE]
> A single request can export up to 25 users with a maximum of 10,000 messages per user. [Contact support](https://getstream.io/contact/support/) to export users with more than 10,000 messages.


### Checking Export Status

```php
$response = $client->getTask($taskId);

if ($response["status"] == "completed") {
  echo $response["result"]["url"];
}
```
