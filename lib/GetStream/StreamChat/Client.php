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

const VERSION = '1.1.9';

class Client
{

    const API_ENDPOINT = 'https://chat-proxy-us-east.stream-io-api.com';

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
    protected $protocol;

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
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $apiVersion
     * @param string $location
     * @param float $timeout
     */
    public function __construct($apiKey, $apiSecret, $apiVersion='v1.0', $location='', $timeout=3.0)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiVersion = $apiVersion;
        $this->timeout = $timeout;
        $this->location = $location;
        $this->protocol = 'https';
        $this->authToken = JWT::encode(["server"=>"true"], $this->apiSecret, 'HS256');
    }

    /**
     * @param  string $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    /**
     * @param  string $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * @return Batcher
     */
    public function batcher()
    {
        return new Batcher($this, $this->signer, $this->apiKey);
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $baseUrl = getenv('STREAM_BASE_CHAT_URL');
        if (!$baseUrl) {
            // try STREAM_BASE_URL for backwards compatibility
            $baseUrl = getenv('STREAM_BASE_URL');
        }
        if ($baseUrl) {
            return $baseUrl;
        }
        $localPort = getenv('STREAM_LOCAL_API_PORT');
        if ($localPort) {
            return "http://localhost:$localPort/api";
        }
        if ($this->location) {
            return "{$this->protocol}://chat-proxy-{$this->location}.stream-io-api.com";
        }
        return static::API_ENDPOINT;
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
     * @return \GuzzleHttp\HandlerStack
     */
    public function getHandlerStack()
    {
        return HandlerStack::create();
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        $handler = $this->getHandlerStack();
        return new GuzzleClient([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => $this->timeout,
            'handler' => $handler,
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);
    }

    public function setGuzzleDefaultOption($option, $value)
    {
        $this->guzzleOptions[$option] = $value;
    }

    /**
     * @param  string $resource
     * @param  string $action
     * @return array
     */
    protected function getHttpRequestHeaders()
    {
        return [
            'Authorization' => $this->authToken,
            'Content-Type' => 'application/json',
            'stream-auth-type' => 'jwt',
            'X-Stream-Client' => 'stream-chat-php-client-' . VERSION,
        ];
    }

    /**
     * @param  string $uri
     * @param  string $method
     * @param  array $data
     * @param  array $queryParams
     * @param  string $resource
     * @param  string $action
     * @return mixed
     * @throws StreamException
     */
    public function makeHttpRequest($uri, $method, $data = [], $queryParams = [], $multipart = [])
    {
        $queryParams['api_key'] = $this->apiKey;
        $client = $this->getHttpClient();
        $headers = $this->getHttpRequestHeaders();

        $uri = (new Uri($this->buildRequestUrl($uri)))
            ->withQuery(http_build_query($queryParams));

        $options = $this->guzzleOptions;

        if($multipart) {
            $boundary = '----44cf242ea3173cfa0b97f80c68608c4c';
            $options['body'] = new MultipartStream($multipart, $boundary);
            $headers['Content-Type'] = "multipart/form-data;boundary=" . $boundary;
        } else {
            if ($method === 'POST' || $method == 'PUT' || $method == 'PATCH') {
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

        return json_decode($body, true);
    }

    /**
     * @param  string $userId
     * @param  int $expiration // a unix timestamp
     * @return string
     */
    public function createToken($userId, $expiration=null)
    {
        $payload = [
            'user_id'   => $userId,
        ];
        if($expiration !== null){
            if(gettype($expiration) !== 'integer'){
                throw new StreamException("expiration must be a unix timestamp");
            }
            $payload['exp'] = $expiration;
        }
        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * @param  string $uri
     * @param  array $queryParams
     * @return mixed
     * @throws StreamException
     */
    public function get($uri, $queryParams=null){
        return $this->makeHttpRequest($uri, "GET", null, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $queryParams
     * @return mixed
     * @throws StreamException
     */
    public function delete($uri, $queryParams=null){
        return $this->makeHttpRequest($uri, "DELETE", null, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $queryParams
     * @return mixed
     * @throws StreamException
     */
    public function patch($uri, $data, $queryParams=null){
        return $this->makeHttpRequest($uri, "PATCH", $data, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $queryParams
     * @return mixed
     * @throws StreamException
     */
    public function post($uri, $data, $queryParams=null){
        return $this->makeHttpRequest($uri, "POST", $data, $queryParams);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @return mixed
     * @throws StreamException
     */
    public function put($uri, $data, $queryParams=null){
        return $this->makeHttpRequest($uri, "PUT", $data, $queryParams);
    }

    /**
     * @return mixed
     * @throws StreamException
     */
    public function getAppSettings()
    {
        return $this->get("app");
    }

    /**
     * @param  array $settings
     * @return mixed
     * @throws StreamException
     */
    public function updateAppSettings($settings)
    {
        return $this->patch("app", $settings);
    }

    /**
     * @param  array $users
     * @return mixed
     * @throws StreamException
     */
    public function updateUsers($users)
    {
        $user_array = [];
        foreach($users as $user){
            $user_array[$user["id"]] = $user;
        }
        return $this->post("users", ["users" => $user_array]);
    }

    /**
     * @param  array $user
     * @return mixed
     * @throws StreamException
     */
    public function updateUser($user)
    {
        return $this->updateUsers([$user]);
    }

    /**
     * @param  array $partialUpdates An array of $partialUpdate arrays
     * @return mixed
     * @throws StreamException
     */
    public function partialUpdateUsers($partialUpdates)
    {
        return $this->patch("users", ["users" => $partialUpdates]);
    }

    /**
     * @param  array $partialUpdate ["id" => userId, set => [key => value], unset => [key]]
     * @return mixed
     * @throws StreamException
     */
    public function partialUpdateUser($partialUpdate)
    {
        return $this->partialUpdateUsers([$partialUpdate]);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function deleteUser($userId, $options=null)
    {
        return $this->delete("users/" . $userId, $options);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function deactivateUser($userId, $options=null)
    {
        if($options === null){
            $options = (object)array();
        }
        return $this->post("users/" . $userId . "/deactivate", $options);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function reactivateUser($userId, $options=null)
    {
        if($options === null){
            $options = (object)array();
        }
        return $this->post("users/" . $userId . "/reactivate", $options);
    }

    /**
     * @param  string $userId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function exportUser($userId, $options=null)
    {
        return $this->get("users/" . $userId . "/export", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function banUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/ban", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function unbanUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $targetId;
        return $this->delete("moderation/ban", $options);
    }

    /**
     * @param  string $messageId
     * @return mixed
     * @throws StreamException
     */
    public function getMessage($messageId)
    {
        return $this->get("messages/" . $messageId);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function flagMessage($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_message_id"] = $targetId;
        return $this->post("moderation/flag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function unFlagMessage($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_message_id"] = $targetId;
        return $this->post("moderation/unflag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function flagUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/flag", $options);
    }

    /**
     * @param  string $targetId
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function unFlagUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $targetId;
        return $this->post("moderation/unflag", $options);
    }

    /**
     * @param  string $targetId
     * @param  string $userId
     * @return mixed
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
     * @return mixed
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
     * @return mixed
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
     * @param  array $message
     * @return mixed
     * @throws StreamException
     */
    public function updateMessage($message)
    {
        try {
            $messageId = $message["id"];
        } catch(Exception $e) {
            throw StreamException("A message must have an id");
        }
        $options = ["message" => $message];
        return $this->post("messages/" . $messageId, $options);
    }

    /**
     * @param  string $messageId
     * @param  array $options
     * @return mixed
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
     * @return mixed
     * @throws StreamException
     */
    public function queryUsers($filterConditions, $sort=null, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $sortFields = [];
        if($sort !== null){
            foreach($sort as $k => $v){
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
     * @return mixed
     * @throws StreamException
     */
    public function queryChannels($filterConditions, $sort=null, $options=null)
    {
        if($options === null){
            $options = array();
        }
        if(!in_array("state", $options)){
            $options["state"] = true;
        }
        if(!in_array("watch", $options)){
            $options["watch"] = false;
        }
        if(!in_array("presence", $options)){
            $options["presence"] = false;
        }
        $sortFields = [];
        if($sort !== null){
            foreach($sort as $k => $v){
                $sortFields[] = ["field" => $k, "direction" => $v];
            }
        }
        $options["filter_conditions"] = $filterConditions;
        $options["sort"] = $sortFields;
        return $this->get("channels", ["payload" => json_encode($options)]);
    }

    /**
     * @param  array $data
     * @return mixed
     * @throws StreamException
     */
    public function createChannelType($data)
    {
        if((!in_array("commands", $data)) || empty($data["commands"])){
            $data["commands"] = ["all"];
        }
        return $this->post("channeltypes", $data);
    }

    /**
     * @param  string $channelTypeName
     * @return mixed
     * @throws StreamException
     */
    public function getChannelType($channelTypeName)
    {
        return $this->get("channeltypes/" . $channelTypeName);
    }

    /**
     * @return mixed
     * @throws StreamException
     */
    public function listChannelTypes()
    {
        return $this->get("channeltypes");
    }

    /**
     * @param  string $channelTypeName
     * @param  array $settings
     * @return mixed
     * @throws StreamException
     */
    public function updateChannelType($channelTypeName, $settings)
    {
        return $this->put("channeltypes/" .$channelTypeName, $settings);
    }

   /**
     * @param  string $channelTypeName
     * @return mixed
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

   /**
     * @param  string $deviceId
     * @param  string $pushProvider // apn or firebase
     * @param  array $userId
     * @return mixed
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
     * @param  array $userId
     * @return mixed
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
     * @param  array $userId
     * @return mixed
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
     * @param  array $userId
     * @return mixed
     * @throws StreamException
     */
    public function verifyWebhook($requestBody, $XSignature)
    {
        $signature = hash_hmac("sha256", $requestBody, $this->apiSecret);

        return $signature == $XSignature;
    }

   /**
     * @param  array $filterConditions
     * @param  string $query
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function search($filterConditions, $query, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options['filter_conditions'] = $filterConditions;
        $options['query'] = $query;
        return $this->get("search", ["payload" => json_encode($options)]);
    }

    public function sendFile($uri, $url, $name, $user, $contentType=null) {
        if($contentType === null){
            $contentType = 'application/octet-stream';
        }
        $multipart = [
            [
                'name' => 'file',
                'contents' => file_get_contents($url, 'r'),
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
        $response = $this->makeHttpRequest($uri, 'POST', null, null, $multipart);
        return $response;
    }

}
