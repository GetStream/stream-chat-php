<?php

namespace GetStream\StreamChat;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * A constant class for internal usage
 * @internal
 */
class Constant
{
    const VERSION = '2.8.0';
}

/**
 * A client for the Stream Chat API
 */
class Client
{
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $apiSecret;

    /**
     * @var string
     */
    protected $location;

    /**
     * @var string
     */
    protected $authToken;

    /**
     * @var string
     */
    public $apiVersion;

    /**
     * @var float
     */
    public $timeout;

    /**
     * @var array
     */
    protected $guzzleOptions = [];

    /**
     * @var array
     */
    protected $httpRequestHeaders = [];

    /**
     * @var HandlerStack
     */
    private $handler;

    /**
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $apiVersion
     * @param string $location
     * @param float $timeout
     */
    public function __construct($apiKey, $apiSecret, $apiVersion='v1.0', $location='us-east', $timeout=null)
    {
        $this->apiKey = $apiKey ?? getenv("STREAM_KEY");
        $this->apiSecret = $apiSecret ?? getenv("STREAM_SECRET");

        if (!$this->apiKey || !$this->apiSecret) {
            throw new StreamException('API key and secret are required.');
        }

        if ($timeout != null) {
            $this->timeout = $timeout;
        } elseif (getenv("STREAM_CHAT_TIMEOUT")) {
            $this->timeout = floatval(getenv("STREAM_CHAT_TIMEOUT"));
        } else {
            $this->timeout = 3.0;
        }

        $this->apiVersion = $apiVersion;
        $this->location = $location;
        $this->authToken = JWT::encode(["server"=>"true"], $this->apiSecret, 'HS256');
        $this->handler = HandlerStack::create();
    }

    /** Sets the location for the URL. Deprecated, and will be removed in a future version.
     * Stream's new Edge infrastructure removes the need to specifically set a regional URL.
     * The baseURL is https://chat.stream-io-api.com regardless of region.
     * @param string $location
     * @return void
     * @deprecated
     */
    public function setLocation($location)
    {
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $envVarKeys = ["STREAM_CHAT_URL", "STREAM_BASE_CHAT_URL", "STREAM_BASE_URL"];

        foreach ($envVarKeys as $envVarKey) {
            $baseUrl = getenv($envVarKey);

            if ($baseUrl) {
                return $baseUrl;
            }
        }

        $localPort = getenv('STREAM_LOCAL_API_PORT');
        if ($localPort) {
            return "http://localhost:$localPort";
        }

        return "https://chat.stream-io-api.com";
    }

    /**
     * @param  string $uri
     * @return string
     */
    public function buildRequestUrl($uri)
    {
        $baseUrl = $this->getBaseUrl();
        return "{$baseUrl}/{$uri}";
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        return new GuzzleClient([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => $this->timeout,
            'handler' => $this->handler,
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);
    }

    /** Sets a Guzzle HTTP option that add to the request. See `\GuzzleHttp\RequestOptions`.
     * @param  string $option
     * @param  mixed $value
     * @return void
     */
    public function setGuzzleDefaultOption($option, $value)
    {
        $this->guzzleOptions[$option] = $value;
    }

    /**
     * @return string[]
     */
    protected function getHttpRequestHeaders()
    {
        return [
            'Authorization' => $this->authToken,
            'Content-Type' => 'application/json',
            'stream-auth-type' => 'jwt',
            'X-Stream-Client' => 'stream-chat-php-client-' . Constant::VERSION,
        ];
    }

    /**
     * @param  string $uri
     * @param  string $method
     * @param  array $data
     * @param  array $queryParams
     * @param  array $multipart
     * @return StreamResponse
     * @throws StreamException
     * @suppress PhanPluginMoreSpecificActualReturnType
     */
    public function makeHttpRequest($uri, $method, $data = [], $queryParams = [], $multipart = [])
    {
        $queryParams['api_key'] = $this->apiKey;
        $client = $this->getHttpClient();
        $headers = $this->getHttpRequestHeaders();

        $uri = (new Uri($this->buildRequestUrl($uri)))
            ->withQuery(http_build_query($queryParams));

        $options = $this->guzzleOptions;

        if ($multipart) {
            $boundary = '----44cf242ea3173cfa0b97f80c68608c4c';
            $options['body'] = new MultipartStream($multipart, $boundary);
            $headers['Content-Type'] = "multipart/form-data;boundary=" . $boundary;
        } else {
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                $options['json'] = $data;
            }
        }

        $options['headers'] = $headers;

        try {
            $response = $client->request($method, $uri, $options);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $msg = $response->getBody()->getContents();
            $code = $response->getStatusCode();
            $previous = $e;
            throw new StreamException($msg, $code, $previous);
        }

        $body = $response->getBody()->getContents();

        return new StreamResponse(json_decode($body, true), $response);
    }

    /**
     * @param  string $userId
     * @param  int $expiration // a unix timestamp
     * @param int $issuedAt // a unix timestamp
     * @return string
     */
    public function createToken($userId, $expiration=null, $issuedAt=null)
    {
        $payload = ['user_id' => $userId];

        if ($expiration != null) {
            if (gettype($expiration) !== 'integer') {
                throw new StreamException("expiration must be a unix timestamp");
            }
            $payload['exp'] = $expiration;
        }

        if ($issuedAt != null) {
            if (gettype($issuedAt) !== 'integer') {
                throw new StreamException("issuedAt must be a unix timestamp");
            }
            $payload['iat'] = $issuedAt;
        }

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * @param  string $uri
     * @param  array $queryParams
     * @return StreamResponse
     * @throws StreamException
     */
    public function get($uri, $queryParams=null)
    {
        return $this->makeHttpRequest($uri, "GET", [], $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $queryParams
     * @return StreamResponse
     * @throws StreamException
     */
    public function delete($uri, $queryParams=null)
    {
        return $this->makeHttpRequest($uri, "DELETE", [], $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $queryParams
     * @return StreamResponse
     * @throws StreamException
     */
    public function patch($uri, $data, $queryParams=null)
    {
        return $this->makeHttpRequest($uri, "PATCH", $data, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $queryParams
     * @return StreamResponse
     * @throws StreamException
     */
    public function post($uri, $data, $queryParams=null)
    {
        return $this->makeHttpRequest($uri, "POST", $data, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @return StreamResponse
     * @throws StreamException
     */
    public function put($uri, $data, $queryParams=null)
    {
        return $this->makeHttpRequest($uri, "PUT", $data, $queryParams);
    }

    /**
     * @return StreamResponse
     * @throws StreamException
     */
    public function getAppSettings()
    {
        return $this->get("app");
    }

    /**
     * @param  array $settings
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateAppSettings($settings)
    {
        return $this->patch("app", $settings);
    }

    /** Sends a test push.
     * @param  array $pushSettings
     * @return StreamResponse
     * @throws StreamException
     */
    public function checkPush($pushSettings)
    {
        return $this->post("check_push", $pushSettings);
    }

    /** Sends a test SQS push.
     * @param  array $sqsSettings
     * @return StreamResponse
     * @throws StreamException
     */
    public function checkSqs($sqsSettings)
    {
        return $this->post("check_sqs", $sqsSettings);
    }

    /**
     * @param  array $users
     * @return StreamResponse
     * @throws StreamException
     */
    public function upsertUsers($users)
    {
        $user_array = [];
        foreach ($users as $user) {
            $user_array[$user["id"]] = $user;
        }
        return $this->post("users", ["users" => $user_array]);
    }

    /**
     * @param  array $user
     * @return StreamResponse
     * @throws StreamException
     */
    public function upsertUser($user)
    {
        return $this->upsertUsers([$user]);
    }

    /**
     * @deprecated use $client->upsertUsers instead
     * @param  array $users
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateUsers($users)
    {
        return $this->upsertUsers($users);
    }

    /**
     * @deprecated use $client->upsertUser instead
     * @param  array $user
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateUser($user)
    {
        return $this->upsertUsers([$user]);
    }

    /**
     * @param  array $partialUpdates An array of $partialUpdate arrays
     * @return StreamResponse
     * @throws StreamException
     */
    public function partialUpdateUsers($partialUpdates)
    {
        return $this->patch("users", ["users" => $partialUpdates]);
    }

    /**
     * @param  array $partialUpdate ["id" => userId, set => [key => value], unset => [key]]
     * @return StreamResponse
     * @throws StreamException
     */
    public function partialUpdateUser($partialUpdate)
    {
        return $this->partialUpdateUsers([$partialUpdate]);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function deleteUser($userId, $options=null)
    {
        return $this->delete("users/" . $userId, $options);
    }

    /**
     * @param  array $userIds
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function deleteUsers($userIds, $options=null)
    {
        if ($options === null) {
            $options = (object)[];
        }
        $options["user_ids"] = $userIds;
        return $this->post("users/delete", $options);
    }

    /**
     * @param  array $cids
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function deleteChannels($cids, $options=null)
    {
        if ($options === null) {
            $options = (object)[];
        }
        $options["cids"] = $cids;
        return $this->post("channels/delete", $options);
    }

    /** Creates a guest user.
     * @param  array $guestRequest
     * @return StreamResponse
     * @throws StreamException
     */
    public function setGuestUser($guestRequest)
    {
        return $this->post("guest", $guestRequest);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function deactivateUser($userId, $options=null)
    {
        if ($options === null) {
            $options = (object)[];
        }
        return $this->post("users/" . $userId . "/deactivate", $options);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function reactivateUser($userId, $options=null)
    {
        if ($options === null) {
            $options = (object)[];
        }
        return $this->post("users/" . $userId . "/reactivate", $options);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function exportUser($userId, $options=null)
    {
        return $this->get("users/" . $userId . "/export", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function banUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/ban", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function unbanUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_user_id"] = $targetId;
        return $this->delete("moderation/ban", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function shadowBan($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["shadow"] = true;
        return $this->banUser($targetId, $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function removeShadowBan($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["shadow"] = true;
        return $this->unbanUser($targetId, $options);
    }

    /** Queries banned users.
     * @param  array $filterConditions
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function queryBannedUsers($filterConditions, $options=[])
    {
        $options["filter_conditions"] = $filterConditions;
        return $this->get("query_banned_users", ["payload" => json_encode($options)]);
    }

    /**
     * @param  string $messageId
     * @return StreamResponse
     * @throws StreamException
     */
    public function getMessage($messageId)
    {
        return $this->get("messages/" . $messageId);
    }

    /**
     * @param  array $filterConditions
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function queryMessageFlags($filterConditions, $options=[])
    {
        $options["filter_conditions"] = $filterConditions;
        return $this->get("moderation/flags/message", ["payload" => json_encode($options)]);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function flagMessage($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_message_id"] = $targetId;
        return $this->post("moderation/flag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function unFlagMessage($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_message_id"] = $targetId;
        return $this->post("moderation/unflag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function flagUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/flag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function unFlagUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/unflag", $options);
    }

    /**
     * @param  string $targetId
     * @param  string $userId
     * @return StreamResponse
     * @throws StreamException
     */
    public function muteUser($targetId, $userId)
    {
        $options = [];
        $options["target_id"] = $targetId;
        $options["user_id"] = $userId;
        return $this->post("moderation/mute", $options);
    }

    /**
     * @param  string $targetId
     * @param  string $userId
     * @return StreamResponse
     * @throws StreamException
     */
    public function unmuteUser($targetId, $userId)
    {
        $options = [];
        $options["target_id"] = $targetId;
        $options["user_id"] = $userId;
        return $this->post("moderation/unmute", $options);
    }

    /**
     * @param  string $userId
     * @return StreamResponse
     * @throws StreamException
     */
    public function markAllRead($userId)
    {
        $options = [
            "user" => [
                "id" => $userId
            ]
        ];
        return $this->post("channels/read", $options);
    }

    /**
     * @param  string $messageId
     * @param  string $userId
     * @param  int $expiration
     * @return StreamResponse
     * @throws StreamException
     */
    public function pinMessage($messageId, $userId, $expiration=null)
    {
        $updates = [
            "set" => [
                "pinned" => true,
                "pin_expires" => $expiration,
            ]
        ];
        return $this->partialUpdateMessage($messageId, $updates, $userId);
    }

    /**
     * @param  string $messageId
     * @param  string $userId
     * @return StreamResponse
     * @throws StreamException
     */
    public function unPinMessage($messageId, $userId)
    {
        $updates = [
            "set" => [
                "pinned" => false,
            ]
        ];
        return $this->partialUpdateMessage($messageId, $updates, $userId);
    }

    /**
     * @param  string $messageId
     * @param  array $updates [set => [key => value], unset => [key]]
     * @param  string $userId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function partialUpdateMessage($messageId, $updates, $userId=null, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        if ($userId !== null) {
            $options["user"] = ["id" => $userId];
        }
        $options = array_merge($options, $updates);
        return $this->put("messages/" .$messageId, $options);
    }

    /**
     * @param  array $message
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateMessage($message)
    {
        try {
            $messageId = $message["id"];
        } catch (Exception $e) {
            throw new StreamException("A message must have an id");
        }
        $options = ["message" => $message];
        return $this->post("messages/" . $messageId, $options);
    }

    /**
     * @param  string $messageId
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function deleteMessage($messageId, $options=null)
    {
        return $this->delete("messages/" . $messageId, $options);
    }

    /**
     * @param  array $filterConditions
     * @param  array $sort
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function queryUsers($filterConditions, $sort=null, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $sortFields = [];
        if ($sort !== null) {
            foreach ($sort as $k => $v) {
                $sortFields[] = ["field" => $k, "direction" => $v];
            }
        }
        $options["filter_conditions"] = $filterConditions;
        $options["sort"] = $sortFields;
        return $this->get("users", ["payload" => json_encode($options)]);
    }

    /**
     * @param  array $filterConditions
     * @param  array $sort
     * @param  array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function queryChannels($filterConditions, $sort=null, $options=null)
    {
        if (!$filterConditions) {
            throw new StreamException("filterConditions can't be empty");
        }
        if ($options === null) {
            $options = [];
        }
        if (!in_array("state", $options)) {
            $options["state"] = true;
        }
        if (!in_array("watch", $options)) {
            $options["watch"] = false;
        }
        if (!in_array("presence", $options)) {
            $options["presence"] = false;
        }
        $sortFields = [];
        if ($sort !== null) {
            foreach ($sort as $k => $v) {
                $sortFields[] = ["field" => $k, "direction" => $v];
            }
        }
        $options["filter_conditions"] = $filterConditions;
        $options["sort"] = $sortFields;
        return $this->post("channels", $options);
    }

    /**
     * @param  array $data
     * @return StreamResponse
     * @throws StreamException
     */
    public function createChannelType($data)
    {
        if ((!in_array("commands", $data)) || empty($data["commands"])) {
            $data["commands"] = ["all"];
        }
        return $this->post("channeltypes", $data);
    }

    /**
     * @param  string $channelTypeName
     * @return StreamResponse
     * @throws StreamException
     */
    public function getChannelType($channelTypeName)
    {
        return $this->get("channeltypes/" . $channelTypeName);
    }

    /**
     * @return StreamResponse
     * @throws StreamException
     */
    public function listChannelTypes()
    {
        return $this->get("channeltypes");
    }

    /**
     * @param  string $channelTypeName
     * @param  array $settings
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateChannelType($channelTypeName, $settings)
    {
        return $this->put("channeltypes/" .$channelTypeName, $settings);
    }

    /**
      * @param  string $channelTypeName
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteChannelType($channelTypeName)
    {
        return $this->delete("channeltypes/" . $channelTypeName);
    }

    /**
      * @param  string $channelTypeName
      * @param  string $channelId
      * @param  array $data
      * @return Channel
      * @throws StreamException
      */
    public function Channel($channelTypeName, $channelId, $data=null)
    {
        return new Channel($this, $channelTypeName, $channelId, $data);
    }

    /**
      *
      * deprecated method: use $client->Channel instead
      *
      * @param  string $channelTypeName
      * @param  string $channelId
      * @param  array $data
      * @return Channel
      * @throws StreamException
      */
    public function getChannel($channelTypeName, $channelId, $data=null)
    {
        return $this->Channel($channelTypeName, $channelId, $data);
    }

    /** Creates a blocklist.
      * @param  array $blocklist
      * @return StreamResponse
      * @throws StreamException
      */
    public function createBlocklist($blocklist)
    {
        return $this->post("blocklists", $blocklist);
    }

    /** Lists all blocklists.
      * @return StreamResponse
      * @throws StreamException
      */
    public function listBlocklists()
    {
        return $this->get("blocklists");
    }

    /** Returns a blocklist.
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function getBlocklist($name)
    {
        return $this->get("blocklists/${name}");
    }

    /** Updates a blocklist.
      * @param  string $name
      * @param  array $blocklist
      * @return StreamResponse
      * @throws StreamException
      */
    public function updateBlocklist($name, $blocklist)
    {
        return $this->put("blocklists/${name}", $blocklist);
    }

    /** Deletes a blocklist.
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteBlocklist($name)
    {
        return $this->delete("blocklists/${name}");
    }

    /** Creates a command.
      * @param  array $command
      * @return StreamResponse
      * @throws StreamException
      */
    public function createCommand($command)
    {
        return $this->post("commands", $command);
    }

    /** Lists all commands.
      * @return StreamResponse
      * @throws StreamException
      */
    public function listCommands()
    {
        return $this->get("commands");
    }

    /** Returns a command.
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function getCommand($name)
    {
        return $this->get("commands/${name}");
    }

    /** Updates a command.
      * @param  string $name
      * @param  array $command
      * @return StreamResponse
      * @throws StreamException
      */
    public function updateCommand($name, $command)
    {
        return $this->put("commands/${name}", $command);
    }

    /** Deletes a command.
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteCommand($name)
    {
        return $this->delete("commands/${name}");
    }

    /**
      * @param  string $deviceId
      * @param  string $pushProvider // apn or firebase
      * @param  string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function addDevice($deviceId, $pushProvider, $userId)
    {
        $data = [
            "id" => $deviceId,
            "push_provider" => $pushProvider,
            "user_id" => $userId,
        ];
        return $this->post("devices", $data);
    }

    /**
      * @param  string $deviceId
      * @param  string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteDevice($deviceId, $userId)
    {
        $data = [
            "id" => $deviceId,
            "user_id" => $userId,
        ];
        return $this->delete("devices", $data);
    }

    /**
      * @param  string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function getDevices($userId)
    {
        $data = [
            "user_id" => $userId,
        ];
        return $this->get("devices", $data);
    }

    /**
      * @param  DateTime $before
      * @return StreamResponse
      * @throws StreamException
      */
    public function revokeTokens($before)
    {
        if ($before instanceof DateTime) {
            $before = $before->format(DateTime::ATOM);
        }
        $settings = [
            "revoke_tokens_issued_before" => $before
        ];
        return $this->updateAppSettings($settings);
    }

    /**
      * @param array $userID
      * @param DateTime $before
      * @return StreamResponse
      * @throws StreamException
      */
    public function revokeUserToken($userID, $before)
    {
        return $this->revokeUsersToken([$userID], $before);
    }

    /**
      * @param  $userIDs
      * @param  DateTime $before
      * @return StreamResponse
      * @throws StreamException
      */
    public function revokeUsersToken($userIDs, $before)
    {
        if ($before instanceof DateTime) {
            $before = $before->format(DateTime::ATOM);
        }
        $updates = [];
        foreach ($userIDs as $userID) {
            array_push($updates, [
                "id" => $userID,
                "set" => [
                    "revoke_tokens_issued_before" => $before
                ]
            ]);
        }
        return $this->partialUpdateUsers($updates);
    }

    /**
      * @param  bool $serverSide
      * @param  bool $android
      * @param  bool $ios
      * @param  bool $web
      * @param  array $endpoints
      * @return StreamResponse
      * @throws StreamException
      */
    public function getRateLimits($serverSide=false, $android=false, $ios=false, $web=false, $endpoints=null)
    {
        $data = [];
        if ($serverSide) {
            $data["server_side"] = "true";
        }
        if ($android) {
            $data["android"] = "true";
        }
        if ($ios) {
            $data["ios"] = "true";
        }
        if ($web) {
            $data["web"] = "true";
        }
        if ($endpoints !== null && is_array($endpoints)) {
            $data["endpoints"] = implode(",", $endpoints);
        }
        return $this->get("rate_limits", $data);
    }

    /**
      * @param  string $requestBody
      * @param  string $XSignature
      * @return bool
      * @throws StreamException
      */
    public function verifyWebhook($requestBody, $XSignature)
    {
        $signature = hash_hmac("sha256", $requestBody, $this->apiSecret);

        return $signature === $XSignature;
    }

    /**
      * @param  array $filterConditions
      * @param  mixed $query // string query or filters for messages
      * @param  array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function search($filterConditions, $query, $options=null)
    {
        if ($options === null) {
            $options = [];
        }

        if (array_key_exists('offset', $options) && $options['offset'] > 0) {
            if (array_key_exists('next', $options) || array_key_exists('sort', $options)) {
                throw new StreamException("Cannot use offset with next or sort parameters");
            }
        }

        $options['filter_conditions'] = $filterConditions;
        if (is_string($query)) {
            $options['query'] = $query;
        } else {
            $options['message_filter_conditions'] = $query;
        }

        $sortFields = [];
        if (array_key_exists('sort', $options)) {
            $sort = $options['sort'];
            foreach ($sort as $k => $v) {
                $sortFields[] = ["field" => $k, "direction" => $v];
            }
        }
        $options['sort'] = $sortFields;
        return $this->get("search", ["payload" => json_encode($options)]);
    }

    /**
      * @param  string $uri
      * @param  string $url
      * @param  string $name
      * @param  array $user
      * @param  string $contentType
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendFile($uri, $url, $name, $user, $contentType=null)
    {
        if ($contentType === null) {
            $contentType = 'application/octet-stream';
        }
        $multipart = [
            [
                'name' => 'file',
                'contents' => file_get_contents($url),
                'filename' => $name,
                // let guzzle handle the content-type
                // 'headers'  => [ 'Content-Type' => $contentType]
            ],
            [
                'name'     => 'user',
                'contents' => json_encode($user),
                // let guzzle handle the content-type
                // 'headers'  => ['Content-Type' => 'application/json']
            ]
        ];
        return $this->makeHttpRequest($uri, 'POST', [], [], $multipart);
    }

    /** Runs a message command action.
      * @param  string $messageId
      * @param  array $formData
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendMessageAction($messageId, $userId, $formData)
    {
        return $this->post("messages/${messageId}/action", ["user_id" => $userId, "form_data" => $formData]);
    }

    /**
      * @return StreamResponse
      * @throws StreamException
      */
    public function listRoles()
    {
        return $this->get("roles");
    }

    /**
      * @return StreamResponse
      * @throws StreamException
      */
    public function listPermissions()
    {
        return $this->get("permissions");
    }

    /**
      * @param  string $id
      * @return StreamResponse
      * @throws StreamException
      */
    public function getPermission($id)
    {
        return $this->get("permissions/${id}");
    }

    /**
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function createRole($name)
    {
        $data = [
            'name' => $name,
        ];
        return $this->post("roles", $data);
    }

    /**
      * @param  string $name
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteRole($name)
    {
        return $this->delete("roles/${name}");
    }

    /** Translates a message to a language.
      * @param  string $messageId
      * @param  string $language
      * @return StreamResponse
      * @throws StreamException
      */
    public function translateMessage($messageId, $language)
    {
        return $this->post("messages/${messageId}/translate", ["language" => $language]);
    }

    /**
     * Schedules channel export task for list of channels
     * @param $requests array of requests for channel export. Each of them should contain `type` and `id` fields and optionally `messages_since` and `messages_until`
     * @param $options array of options
     * @return StreamResponse returns task ID that you can use to get export status (see getTask method)
     */
    public function exportChannels($requests, $options = [])
    {
        $data = array_merge($options, [
            'channels' => $requests,
        ]);
        return $this->post("export_channels", $data);
    }

    /**
     * Schedules channel export task for a single channel
     * @param $request export channel request (see exportChannel)
     * @param $options array of options
     * @return StreamResponse returns task ID that you can use to get export status (see getTask method)
     */
    public function exportChannel($request, $options)
    {
        return $this->exportChannels([$request], $options);
    }

    /**
     * Gets the status of a channel export task.
     * @param string $id id of the task
     * @return StreamResponse returns the status of the task
     */
    public function getExportChannelStatus($id)
    {
        return $this->get("export_channels/${id}");
    }

    /**
     * Returns task status
     * @param $id string task ID
     * @return StreamResponse
     */
    public function getTask($id)
    {
        return $this->get("tasks/{$id}");
    }
}
