# Official PHP SDK for [Stream Chat](https://getstream.io/chat/)

[![build](https://github.com/GetStream/stream-chat-php/workflows/build/badge.svg)](https://github.com/GetStream/stream-chat-php/actions) [![Latest Stable Version](https://poser.pugx.org/get-stream/stream-chat/v/stable)](https://packagist.org/packages/get-stream/stream-chat)

<p align="center">
    <img src="./assets/logo.svg" width="50%" height="50%">
</p>
<p align="center">
    Official PHP API client for Stream Chat, a service for building chat applications.
    <br />
    <a href="https://getstream.io/chat/docs/"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/GetStream/stream-chat-php/issues">Report Bug</a>
    ·
    <a href="https://github.com/GetStream/stream-chat-php/issues">Request Feature</a>
</p>

## 📝 About Stream

You can sign up for a Stream account at our [Get Started](https://getstream.io/chat/get_started/) page.

You can use this library to access chat API endpoints server-side.

For the client-side integrations (web and mobile) have a look at the JavaScript, iOS and Android SDK libraries ([docs](https://getstream.io/chat/)).

## ⚙️ Installation

```shell
$ composer require get-stream/stream-chat
```

## ✨ Getting started

```php
require_once "./vendor/autoload.php";
```

Instantiate a new client, find your API keys in the dashboard.

```php
$client = new GetStream\StreamChat\Client("<api-key>", "<api-secret>");
```

### Generate a token for client-side usage

```php
$token = $client->createToken("bob-1");

// with an expiration time
$expiration = (new DateTime())->getTimestamp() + 3600;
$token = $client->createToken("bob-1", $expiration);
```

### Update / Create users

```php
$bob = [
    'id' => 'bob-1',
    'role' => 'admin',
    'name' => 'Robert Tables',
];

$bob = $client->upsertUser($bob);

// Batch update is also supported
$jane = ['id' => 'jane', 'role' => 'admin'];
$june = ['id' => 'june', 'role' => 'user'];
$tom = ['id' => 'tom', 'role' => 'guest'];
$users = $client->upsertUsers([$jane, $june, $tom]);
```

### Channel types

```php
$channelConf = [
    'name' => 'livechat',
    'automod' => 'disabled',
    'commands' => ['ban'],
    'mutes' => true
];

$channelType = $client->createChannelType($channelConf);

$allChannelTypes =  $client->listChannelTypes();
```

### Channels and messages

```php
$channel = $client->Channel("messaging", "bob-and-jane");
$state = $channel->create("bob-1", ['bob-1', 'jane']);
$channel->addMembers(['mike', 'joe']);
```
### Messaging

```php
$msg_bob = $channel->sendMessage(["text" => "Hi June!"], 'bob-1');

// Reply to a message
$reply_bob = $channel->sendMessage(["text" => "Long time no see!"], 'bob-1', $msg_bob['message']['id']);
```

### Reactions
```php
$channel->sendReaction($reply_bob['message']['id'], ['type' => 'like'], 'june');
```

### Moderation

```php
$channel->addModerators(['june']);
$channel->demoteModerators(['june']);

$channel->banUser('june', ["reason" => "Being a big jerk", "timeout" => 5, "user_id" => 'bob-1']);
$channel->unbanUser('june', ["user_id" => 'bob-1']);
```

### Devices

```php
$device_id = "iOS_Device_Token_123";
$client->addDevice($device_id, "apn", "june");
$devices = $client->getDevices('june');

$client->deleteDevice($device_id, 'june');
```

## 🙋‍♀️ Frequently asked questions

- **Q**: What date formats does the backend accept?
- **A**: We accept [RFC3339](https://datatracker.ietf.org/doc/html/rfc3339) format. So you either use raw strings as date or you implement a serializer for your DateTime object.

```php
class MyDateTime extends \DateTime implements \JsonSerializable
{
    public function jsonSerialize()
    {
        // Note: this returns ISO8601
        // but it's compatible with 3339
       return $this->format("c");
    }
}

$createdAt = new MyDateTime();

$client->search( 
	['type' => "messaging"], 
	['created_at' => ['$lte' => $createdAt]], 
	['limit' => 10]
);
```

## ✍️ Contributing

We welcome code changes that improve this library or fix a problem, please make sure to follow all best practices and add tests if applicable before submitting a Pull Request on Github. We are very happy to merge your code in the official repository. Make sure to sign our [Contributor License Agreement (CLA)](https://docs.google.com/forms/d/e/1FAIpQLScFKsKkAJI7mhCr7K9rEIOpqIDThrWxuvxnwUq2XkHyG154vQ/viewform) first. See our [license file](./LICENSE) for more details.

Head over to [CONTRIBUTING.md](./CONTRIBUTING.md) for some development tips.

## 🧑‍💻 We are hiring!

We've recently closed a [$38 million Series B funding round](https://techcrunch.com/2021/03/04/stream-raises-38m-as-its-chat-and-activity-feed-apis-power-communications-for-1b-users/) and we keep actively growing.
Our APIs are used by more than a billion end-users, and you'll have a chance to make a huge impact on the product within a team of the strongest engineers all over the world.

Check out our current openings and apply via [Stream's website](https://getstream.io/team/#jobs).
