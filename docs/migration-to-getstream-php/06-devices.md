# Devices

This guide covers migrating device management operations (push notification setup) from `stream-chat-php` to `getstream-php`.

## Adding a Device

### APN (Apple Push Notifications)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$client->addDevice("device-token-abc", "apn", "user-123");

// With a named push provider configuration
$client->addDevice("device-token-abc", "apn", "user-123", "my-apn-provider");
```

**After (getstream-php):**

```php
use GetStream\ChatClient;
use GetStream\GeneratedModels\CreateDeviceRequest;

$client = new ChatClient("<api-key>", "<api-secret>");

$client->createDevice(new CreateDeviceRequest(
    id: 'device-token-abc',
    pushProvider: 'apn',
    userID: 'user-123',
));

// With a named push provider configuration
$client->createDevice(new CreateDeviceRequest(
    id: 'device-token-abc',
    pushProvider: 'apn',
    userID: 'user-123',
    pushProviderName: 'my-apn-provider',
));
```

> **Key differences:**
> - Method renamed from `addDevice()` to `createDevice()`
> - Uses a `CreateDeviceRequest` object with named arguments instead of positional string parameters
> - Push provider and user ID are properties on the request object

### Firebase (Android Push Notifications)

**Before (stream-chat-php):**

```php
$client->addDevice("fcm-token-xyz", "firebase", "user-123");

// With a named push provider configuration
$client->addDevice("fcm-token-xyz", "firebase", "user-123", "my-firebase-provider");
```

**After (getstream-php):**

```php
use GetStream\GeneratedModels\CreateDeviceRequest;

$client->createDevice(new CreateDeviceRequest(
    id: 'fcm-token-xyz',
    pushProvider: 'firebase',
    userID: 'user-123',
));

// With a named push provider configuration
$client->createDevice(new CreateDeviceRequest(
    id: 'fcm-token-xyz',
    pushProvider: 'firebase',
    userID: 'user-123',
    pushProviderName: 'my-firebase-provider',
));
```

### VoIP Token (Apple)

The new SDK adds support for registering Apple VoIP push tokens, which was not available as a dedicated option in the old SDK.

**After (getstream-php):**

```php
use GetStream\GeneratedModels\CreateDeviceRequest;

$client->createDevice(new CreateDeviceRequest(
    id: 'voip-token-abc',
    pushProvider: 'apn',
    userID: 'user-123',
    voipToken: true,
));
```

## Listing Devices for a User

**Before (stream-chat-php):**

```php
$response = $client->getDevices("user-123");
```

**After (getstream-php):**

```php
$response = $client->listDevices('user-123');
```

> **Key differences:**
> - Method renamed from `getDevices()` to `listDevices()`
> - Response is typed as `ListDevicesResponse` instead of a generic `StreamResponse`

## Deleting a Device

**Before (stream-chat-php):**

```php
$client->deleteDevice("device-token-abc", "user-123");
```

**After (getstream-php):**

```php
$client->deleteDevice('device-token-abc', 'user-123');
```

> **Key differences:**
> - The method signature is the same: `deleteDevice(string $id, string $userID)`
> - Parameter names changed from `$deviceId` to `$id` and `$userId` to `$userID`, but since both are positional strings this has no practical impact

## Method Mapping Summary

| Operation | stream-chat-php | getstream-php |
|-----------|----------------|---------------|
| Add device | `$client->addDevice($deviceId, $provider, $userId, $providerName)` | `$client->createDevice(new CreateDeviceRequest(...))` |
| List devices | `$client->getDevices($userId)` | `$client->listDevices($userID)` |
| Delete device | `$client->deleteDevice($deviceId, $userId)` | `$client->deleteDevice($id, $userID)` |
