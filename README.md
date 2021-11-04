# stream-chat-php

[![build](https://github.com/GetStream/stream-chat-php/workflows/build/badge.svg)](https://github.com/GetStream/stream-chat-php/actions) [![Latest Stable Version](https://poser.pugx.org/get-stream/stream-chat/v/stable)](https://packagist.org/packages/get-stream/stream-chat)

The official PHP API client for [Stream chat](https://getstream.io/chat/) a service for building chat applications.

You can sign up for a Stream account at https://getstream.io/chat/get_started/.

You can use this library to access chat API endpoints server-side, for
the client-side integrations (web and mobile) have a look at the
Javascript, iOS and Android SDK libraries https://getstream.io/chat/.

### Installation

```bash
composer require get-stream/stream-chat
```

### Documentation

[Official API docs](https://getstream.io/chat/docs/)

### Supported features

- Chat channels
- Messages
- Chat channel types
- User management
- Moderation API
- Push configuration
- User devices
- User search
- Channel search
- Hide / Show channels

### Testing and contributing

We love contributions. We love contributions with tests even more! To
run the test-suite to ensure everything still works, run phpunit:

```bash
vendor/bin/phpunit --testsuite "Unit Test Suite"
```

### Getting started

```php
require_once "./vendor/autoload.php";
```

Instantiate a new client, find your API keys in the dashboard.

```php
$client = new GetStream\StreamChat\Client(getenv("STREAM_API_KEY"), getenv("STREAM_API_SECRET"));
```

Generate a token for clientside use

```php
$token = $client->createToken("bob-1");

// with an expiration time
$expiration = (new DateTime())->getTimestamp() + 3600;
$token = $client->createToken("bob-1", $expiration);
```

Set location. Tell the client where your app is [hosted](https://getstream.io/chat/docs/multi_region/?language=php&q=locations).

```php

$client->setLocation("singapore");

```

## Update / Create users

```php
$bob = [
    'id' => 'bob-1',
    'role' => 'admin',
    'name' => 'Robert Tables',
];

$bob = $client->upsertUser($bob);

//batch update is also supported
$jane = ['id' => 'jane', 'role' => 'admin'];
$june = ['id' => 'june', 'role' => 'user'];
$tom = ['id' => 'tom', 'role' => 'guest'];
$users = $client->upsertUsers([$jane, $june, $tom]);
```

## ChannelType CRUD

```php
$channelConfName = 'livechat';

try {
 $client->deleteChannelType($channelConfName);
} catch (GetStream\StreamChat\StreamException $e) {
  if($e->getCode() !== 404){
     throw($e);
  }
}

$channelConf = [
    'name' => $channelConfName,
    'automod' => 'disabled',
    'commands' => ['ban'],
    'mutes' => true
];

// create
$channelType = $client->createChannelType($channelConf);
echo($channelType['created_at']);

// update
$channelConf['mutes'] = false;
unset($channelConf['name']);
$channelType = $client->updateChannelType($channelConfName, $channelConf);
echo($channelType['updated_at']);
echo($channelType['mutes']);

// get
$messaging = $client->getChannelType('messaging');

// list
$channels =  $client->listChannelTypes();

// delete
$channelType = $client->deleteChannelType($channelConfName);

```

## Channels and messages

```php
$channel = $client->Channel("messaging", "bob-and-jane");
$state = $channel->create("bob-1", ['bob-1', 'jane']);

foreach($state['members'] as $member){
   echo $member['user']['id'] ."\n";
}

// Alternatively
$channel = $client->Channel("messaging", "bob-june");
$state = $channel->create("bob-1");
$channel->addMembers(['bob-1', 'jane']);

// send messages
$msg_bob = $channel->sendMessage(["text" => "Hi June!"], 'bob-1');
$msg_june = $channel->sendMessage(["text" => "Hi Bob!"], 'june');

echo "{$msg_bob['message']['user']['id']} says {$msg_bob['message']['text']} at {$msg_bob['message']['created_at']}\n";
echo "{$msg_june['message']['user']['id']} says {$msg_june['message']['text']} at {$msg_june['message']['created_at']}\n";

$reply_bob = $channel->sendMessage(["text" => "Long time no see!"], 'bob-1', $msg_june['message']['id']);

echo "{$reply_bob['message']['user']['id']} replied with {$reply_bob['message']['text']} to {$reply_bob['message']['parent_id']}\n";

// alternatively
$reply_bob = $channel->sendMessage(["text" => "Nice to see you again!", "parent_id" => $msg_june['message']['id']], 'bob-1');

echo "{$reply_bob['message']['user']['id']} replied with {$reply_bob['message']['text']} to {$reply_bob['message']['parent_id']}\n";

$msg_bob = $channel->sendMessage(["text" => "Nothing ever lasts forever"], 'bob-1');

// delete a message from any channel by ID
$response = $client->deleteMessage($msg_bob['message']['id']);

echo $response['message']['deleted_at'];

// Send reactions
$response = $channel->sendReaction($reply_bob['message']['id'], ['type' => 'like'], 'june');

echo "{$response['reaction']['user']['id']} reacted with {$response['reaction']['type']} to {$response['message']['id']}\n";

// add / remove moderators
$channel->addModerators(['june']);
$channel->demoteModerators(['june']);

// add a ban with a timeout
$channel->banUser('june', ["reason" => "Being a big jerk", "timeout" => 5, "user_id" => 'bob-1']);

// remove the ban
$channel->unbanUser('june', ["user_id" => 'bob-1']);

//query channel state
$params = [
  "state" => true,
  "messages" => [
    "limit" => 5,
    "id_lte" => $reply_bob['message']['id'],
  ],
];
$state = $channel->query($params);

echo "ChannelId: " . $state['channel']['id'];
echo "ChannelType: " . $state['channel']['type'];
foreach($state['members'] as $member){
   echo $member['user']['id'] ."\n";
}
foreach($state['messages'] as $msg){
   echo $msg['id'] . ' ' . $msg['text'] ."\n";
}

```

## Devices

```php
$device_id = "iOS Device Token";
$client->addDevice($device_id, "apn", "june");
$response = $client->getDevices('june');

echo 'DEVICE ID' . $response['devices'][0]['id'];

$client->deleteDevice($device_id, 'june');
```

### Copyright and License Information

[BSD-3](https://github.com/GetStream/stream-chat-php/blob/master/LICENSE).
