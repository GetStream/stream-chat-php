Scheduled messages let a user write a message now and have it sent later. A server-side client can schedule, list, edit, cancel and send early the scheduled messages of any user.

Scheduled messages must be enabled for your app. Until the scheduled message is sent it is visible only to the user who scheduled it (and to your backend); the channel's other members see nothing until the message is sent.

## Scheduling a Message

`scheduleMessage` takes the same message and send options as `sendMessage`, plus the send time. The message is not sent: it is stored and sent at that time. The send time must be at least 1 minute and at most the app's maximum lead time (120 days by default) away.

```php
$response = $channel->scheduleMessage(
    ['text' => 'Good morning!'],
    'john',
    new DateTime('+1 hour'),
    ['skip_push' => true] // optional send options
);

$scheduled = $response['scheduled_message'];
$scheduled['id'];           // ID of the scheduled message
$scheduled['status'];       // "scheduled"
$scheduled['scheduled_at']; // when it will be sent
```

`$response['message']` holds the message as it will be sent; it is not in the channel yet. Slash commands cannot be scheduled, and a scheduled message cannot also be `pending`.

## Listing Scheduled Messages

Lists one user's scheduled messages across channels: pending ones first, then failed ones, each by `scheduled_at`. Each item carries a summary of its `channel`.

```php
$response = $client->queryScheduledMessages(
    'john',
    ['cid' => 'messaging:general', 'status' => ['scheduled', 'failed']], // optional filter
    null,                                                               // sort: only scheduled_at ascending
    ['limit' => 100]                                                    // 1 to 100, defaults to 20
);

foreach ($response['scheduled_messages'] as $scheduled) {
    // ...
}

// Next page
if (!empty($response['next'])) {
    $client->queryScheduledMessages('john', null, null, ['next' => $response['next']]);
}
```

## Editing a Scheduled Message

Replace the message, move the send time, or both. A new message replaces the stored one as a whole; the sender stays the user who scheduled it.

```php
// Change the text and the send time
$client->updateScheduledMessage($scheduled['id'], ['text' => 'Good morning, everyone!'], new DateTime('+2 hours'));

// Move the send time only
$client->updateScheduledMessage($scheduled['id'], null, new DateTime('+3 hours'));
```

## Canceling a Scheduled Message

Cancels a scheduled message so it is never sent, or removes one that failed.

```php
$response = $client->deleteScheduledMessage($scheduled['id']);
$response['scheduled_message']['status']; // "canceled"
```

## Sending a Scheduled Message Now

Moves the send time to now and queues the send.

```php
$client->sendScheduledMessageNow($scheduled['id']);
```

Editing, canceling and sending now fail with a 409 error while the message is being sent.

## Statuses and Failures

| Status      | Meaning                                                                    |
| ----------- | -------------------------------------------------------------------------- |
| `scheduled` | Waiting for its send time                                                  |
| `sending`   | Being sent                                                                 |
| `failed`    | The send failed; `failure_reason` says why. It can only be deleted         |
| `canceled`  | Canceled; returned by `deleteScheduledMessage`                             |
| `sent`      | Sent; reported only by the `scheduled_message.sent` event                  |

Failure reasons: `banned`, `no_permission`, `channel_frozen`, `channel_deleted`, `user_deleted`, `moderation_blocked`, `hook_rejected`, `expired`, `internal_error`, `invalid_message`, `pending_not_supported`.

## Events and Webhooks

| Event                       | Webhook | When                                                                  |
| --------------------------- | ------- | --------------------------------------------------------------------- |
| `scheduled_message.created` | No      | A message was scheduled                                               |
| `scheduled_message.updated` | No      | A scheduled message was edited or sent now                            |
| `scheduled_message.deleted` | No      | A scheduled message was canceled                                      |
| `scheduled_message.sent`    | Yes     | A scheduled message was sent; `message_id` is the sent message's ID   |
| `scheduled_message.failed`  | Yes     | A scheduled message failed to send                                    |

Every one of them carries `scheduled_message`. The sent message itself also triggers the usual `message.new`.

## Permissions

Scheduling needs the `ScheduleMessage` permission (channel capability `schedule-message`) in addition to the permission to send the message.
