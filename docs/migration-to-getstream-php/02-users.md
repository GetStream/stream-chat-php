# Users

This guide covers migrating user management operations from `stream-chat-php` to `getstream-php`.

> All `getstream-php` examples assume the client is already instantiated. See [01-setup-and-auth.md](./01-setup-and-auth.md) for client setup.

## Upsert a Single User

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->upsertUser([
    'id' => 'user-123',
    'name' => 'John Doe',
    'role' => 'admin',
    'custom_field' => 'value',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateUsers(new GeneratedModels\UpdateUsersRequest(
    users: [
        'user-123' => new GeneratedModels\UserRequest(
            id: 'user-123',
            name: 'John Doe',
            role: 'admin',
            custom: (object) ['custom_field' => 'value'],
        ),
    ],
));

$user = $response->getData()->users['user-123'];
```

**Key differences:**

- The old SDK accepts a flat associative array. The new SDK uses typed `UserRequest` model objects.
- Custom fields are placed in the `custom` property (cast to `object`) instead of being mixed into the top-level array.
- The `users` parameter is keyed by user ID.
- There is no dedicated `upsertUser()` method in the new SDK. Use `updateUsers()` with a single entry.

## Batch Upsert Users

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->upsertUsers([
    ['id' => 'user-1', 'name' => 'Alice'],
    ['id' => 'user-2', 'name' => 'Bob'],
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->updateUsers(new GeneratedModels\UpdateUsersRequest(
    users: [
        'user-1' => new GeneratedModels\UserRequest(
            id: 'user-1',
            name: 'Alice',
        ),
        'user-2' => new GeneratedModels\UserRequest(
            id: 'user-2',
            name: 'Bob',
        ),
    ],
));

$users = $response->getData()->users;
```

**Key differences:**

- The old SDK takes a plain array of user arrays and indexes them by ID internally. The new SDK requires you to key the array by user ID yourself.

## Query Users

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Query by ID
$response = $client->queryUsers(
    ['id' => ['$in' => ['user-1', 'user-2']]],
);

// With sort and pagination
$response = $client->queryUsers(
    ['role' => 'admin'],
    ['last_active' => -1],  // sort descending
    ['limit' => 10, 'offset' => 0],
);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Query by ID
$response = $client->queryUsers(new GeneratedModels\QueryUsersPayload(
    filterConditions: (object) [
        'id' => (object) ['$in' => ['user-1', 'user-2']],
    ],
));

// With sort and pagination
$response = $client->queryUsers(new GeneratedModels\QueryUsersPayload(
    filterConditions: (object) ['role' => (object) ['$eq' => 'admin']],
    sort: [
        new GeneratedModels\SortParamRequest(
            field: 'last_active',
            direction: -1,
        ),
    ],
    limit: 10,
    offset: 0,
));

$users = $response->getData()->users;
```

**Key differences:**

- Filter conditions must be cast to `(object)`. The old SDK accepts plain associative arrays.
- Sort is now an array of `SortParamRequest` objects instead of an associative array.
- Pagination options (`limit`, `offset`) are named parameters on the payload instead of a separate `$options` array.
- To include deactivated users, pass `includeDeactivatedUsers: true` on the payload.

## Partial Update Users

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Set and unset fields
$response = $client->partialUpdateUser([
    'id' => 'user-123',
    'set' => ['name' => 'Jane Doe', 'country' => 'NL'],
    'unset' => ['custom_field'],
]);

// Batch partial update
$response = $client->partialUpdateUsers([
    [
        'id' => 'user-1',
        'set' => ['role' => 'admin'],
    ],
    [
        'id' => 'user-2',
        'set' => ['role' => 'user'],
    ],
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Set and unset fields
$response = $client->updateUsersPartial(new GeneratedModels\UpdateUsersPartialRequest(
    users: [
        new GeneratedModels\UpdateUserPartialRequest(
            id: 'user-123',
            set: (object) ['name' => 'Jane Doe', 'country' => 'NL'],
            unset: ['custom_field'],
        ),
    ],
));

// Batch partial update
$response = $client->updateUsersPartial(new GeneratedModels\UpdateUsersPartialRequest(
    users: [
        new GeneratedModels\UpdateUserPartialRequest(
            id: 'user-1',
            set: (object) ['role' => 'admin'],
        ),
        new GeneratedModels\UpdateUserPartialRequest(
            id: 'user-2',
            set: (object) ['role' => 'user'],
        ),
    ],
));
```

**Key differences:**

- Method renamed from `partialUpdateUser()` / `partialUpdateUsers()` to `updateUsersPartial()`.
- The `set` property must be cast to `(object)`.
- Both single and batch updates use the same `updateUsersPartial()` method with an array of `UpdateUserPartialRequest` objects.

## Deactivate and Reactivate Users

### Single User

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Deactivate
$response = $client->deactivateUser('user-123', [
    'mark_messages_deleted' => true,
    'created_by_id' => 'admin-user',
]);

// Reactivate
$response = $client->reactivateUser('user-123', [
    'restore_messages' => true,
    'created_by_id' => 'admin-user',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Deactivate
$response = $client->deactivateUser('user-123', new GeneratedModels\DeactivateUserRequest(
    markMessagesDeleted: true,
    createdByID: 'admin-user',
));

// Reactivate
$response = $client->reactivateUser('user-123', new GeneratedModels\ReactivateUserRequest(
    restoreMessages: true,
    createdByID: 'admin-user',
));
```

### Batch (Async)

Both SDKs support batch deactivation/reactivation as asynchronous operations that return a task ID.

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

// Batch deactivate
$response = $client->deactivateUsers(['user-1', 'user-2'], [
    'mark_messages_deleted' => true,
    'created_by_id' => 'admin-user',
]);

// Batch reactivate
$response = $client->reactivateUsers(['user-1', 'user-2'], [
    'restore_messages' => true,
    'created_by_id' => 'admin-user',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

// Batch deactivate
$response = $client->deactivateUsers(new GeneratedModels\DeactivateUsersRequest(
    userIds: ['user-1', 'user-2'],
    markMessagesDeleted: true,
    createdByID: 'admin-user',
));
$taskId = $response->getData()->taskID;

// Batch reactivate
$response = $client->reactivateUsers(new GeneratedModels\ReactivateUsersRequest(
    userIds: ['user-1', 'user-2'],
    restoreMessages: true,
    createdByID: 'admin-user',
));
$taskId = $response->getData()->taskID;
```

**Key differences:**

- Option keys changed from snake_case strings to camelCase named arguments (e.g. `mark_messages_deleted` becomes `markMessagesDeleted`).
- Options are passed as typed request objects instead of associative arrays.
- The method signatures are otherwise very similar between the two SDKs.

## Delete Users

### Single User (Sync)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->deleteUser('user-123', [
    'mark_messages_deleted' => true,
]);
```

**After (getstream-php):**

The new SDK does not have a synchronous single-user delete. Use `deleteUsers()` with a single user ID instead:

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteUsers(new GeneratedModels\DeleteUsersRequest(
    userIds: ['user-123'],
    user: 'soft',
    messages: 'soft',
));
$taskId = $response->getData()->taskID;
```

### Batch Delete (Async)

**Before (stream-chat-php):**

```php
use GetStream\StreamChat\Client;

$client = new Client("<api-key>", "<api-secret>");

$response = $client->deleteUsers(['user-1', 'user-2'], [
    'user' => 'hard',
    'messages' => 'hard',
    'conversations' => 'hard',
]);
```

**After (getstream-php):**

```php
use GetStream\ClientBuilder;
use GetStream\GeneratedModels;

$client = ClientBuilder::fromEnv()->build();

$response = $client->deleteUsers(new GeneratedModels\DeleteUsersRequest(
    userIds: ['user-1', 'user-2'],
    user: 'hard',
    messages: 'hard',
    conversations: 'hard',
));
$taskId = $response->getData()->taskID;
```

**Key differences:**

- The old SDK has both `deleteUser()` (sync, single user) and `deleteUsers()` (async, batch). The new SDK only has `deleteUsers()`, which is always async.
- Options are passed as named arguments on a typed request object instead of an associative array.

## Summary of Method Changes

| Operation | stream-chat-php | getstream-php |
|-----------|-----------------|---------------|
| Upsert user(s) | `$client->upsertUser($user)` / `$client->upsertUsers($users)` | `$client->updateUsers(new UpdateUsersRequest(...))` |
| Query users | `$client->queryUsers($filter, $sort, $options)` | `$client->queryUsers(new QueryUsersPayload(...))` |
| Partial update | `$client->partialUpdateUser($update)` / `$client->partialUpdateUsers($updates)` | `$client->updateUsersPartial(new UpdateUsersPartialRequest(...))` |
| Deactivate (single) | `$client->deactivateUser($id, $opts)` | `$client->deactivateUser($id, new DeactivateUserRequest(...))` |
| Deactivate (batch) | `$client->deactivateUsers($ids, $opts)` | `$client->deactivateUsers(new DeactivateUsersRequest(...))` |
| Reactivate (single) | `$client->reactivateUser($id, $opts)` | `$client->reactivateUser($id, new ReactivateUserRequest(...))` |
| Reactivate (batch) | `$client->reactivateUsers($ids, $opts)` | `$client->reactivateUsers(new ReactivateUsersRequest(...))` |
| Delete (single, sync) | `$client->deleteUser($id, $opts)` | _(use `deleteUsers()` with one ID)_ |
| Delete (batch, async) | `$client->deleteUsers($ids, $opts)` | `$client->deleteUsers(new DeleteUsersRequest(...))` |
