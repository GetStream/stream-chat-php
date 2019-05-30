<?php

namespace GetStream\StreamChat;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;

const VERSION = '1.0.0';

class Client
{

    const API_ENDPOINT = 'chat-us-east-1.stream-io-api.com';

    /**
     * @var string
     */
    protected $api_key;

    /**
     * @var string
     */
    protected $api_secret;

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
    public $api_version;

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
     * @param string $api_key
     * @param string $api_secret
     * @param string $api_version
     * @param string $location
     * @param float $timeout
     */
    public function __construct($api_key, $api_secret, $api_version='v1.0', $location='', $timeout=3.0)
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->api_version = $api_version;
        $this->timeout = $timeout;
        $this->location = $location;
        $this->protocol = 'https';
        $this->auth_token = JWT::encode(["server"=>"true"], $this->api_secret, 'HS256');
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
        return new Batcher($this, $this->signer, $this->api_key);
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $baseUrl = getenv('STREAM_BASE_URL');
        if (!$baseUrl) {
            $api_endpoint = static::API_ENDPOINT;
            $localPort = getenv('STREAM_LOCAL_API_PORT');
            if ($localPort) {
                $baseUrl = "http://localhost:$localPort/api";
            } else {
                if ($this->location) {
                    $subdomain = "{$this->location}-api";
                } else {
                    $subdomain = 'api';
                }
                // $baseUrl = "{$this->protocol}://{$subdomain}." . $api_endpoint;
                $baseUrl = "{$this->protocol}://" . $api_endpoint;
            }
        }
        return $baseUrl;
    }

    /**
     * @param  string $uri
     * @return string
     */
    public function buildRequestUrl($uri)
    {
        $baseUrl = $this->getBaseUrl();
        // return "{$baseUrl}/{$this->api_version}/{$uri}";
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
            'Authorization' => $this->auth_token,
            'Content-Type' => 'application/json',
            'stream-auth-type' => 'jwt',
            'X-Stream-Client' => 'stream-chat-php-client-' . VERSION,
        ];
    }

    /**
     * @param  string $uri
     * @param  string $method
     * @param  array $data
     * @param  array $query_params
     * @param  string $resource
     * @param  string $action
     * @return mixed
     * @throws StreamException
     */
    public function makeHttpRequest($uri, $method, $data = [], $query_params = [])
    {
        $query_params['api_key'] = $this->api_key;
        $client = $this->getHttpClient();
        $headers = $this->getHttpRequestHeaders();

        $uri = (new Uri($this->buildRequestUrl($uri)))
            ->withQuery(http_build_query($query_params));

        $options = $this->guzzleOptions;
        $options['headers'] = $headers;

        if ($method === 'POST') {
            $options['json'] = $data;
        }

        try {
            $response = $client->request($method, $uri, $options);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $msg = $response->getBody();
            $code = $response->getStatusCode();
            $previous = $e;
            throw new StreamException($msg, $code, $previous);
        }

        $body = $response->getBody()->getContents();

        return json_decode($body, true);
    }

    /**
     * @param  string $user_id
     * @param  array $extra_data
     * @return string
     */
    public function createToken($user_id, $extra_data)
    {
        $payload = [
            'user_id'   => $user_id,
        ];
        foreach($extra_data as $name => $value){
            $payload[$name] = $value;
        }
        return JWT::encode($payload, $this->api_secret, 'HS256');
    }

    /**
     * @param  string $uri
     * @param  array $query_params
     * @return mixed
     * @throws StreamException
     */
    private function get($uri, $query_params=null){
        return $this->makeHttpRequest($uri, "GET", null, $query_params);
    }

    /**
     * @param  string $uri
     * @param  array $query_params
     * @return mixed
     * @throws StreamException
     */
    private function delete($uri, $query_params=null){
        return $this->makeHttpRequest($uri, "DELETE", null, $query_params);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $query_params
     * @return mixed
     * @throws StreamException
     */
    private function patch($uri, $data, $query_params=null){
        return $this->makeHttpRequest($uri, "PATCH", $data, $query_params);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @param  array $query_params
     * @return mixed
     * @throws StreamException
     */
    private function post($uri, $data, $query_params=null){
        return $this->makeHttpRequest($uri, "PUT", $data, $query_params);
    }

    /**
     * @param  string $uri
     * @param  array $data
     * @return mixed
     * @throws StreamException
     */
    private function put($uri, $data, $query_params=null){
        return $this->makeHttpRequest($uri, "GET", null, $query_params);
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
        return $this->patch("app");
    }

    /**
     * @param  array $users
     * @return mixed
     * @throws StreamException
     */
    public function updateUsers($users)
    {
        return $this->post("app");
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
     * @param  string $user_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function deleteUser($user_id, $options=null)
    {
        return $this->delete("users/" . $user_id, $options);
    }

    /**
     * @param  string $user_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function deactivateUser($user_id, $options=null)
    {
        return $this->post("users/" . $user_id . "/deactivate", $options);
    }

    /**
     * @param  string $user_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function exportUser($user_id, $options=null)
    {
        return $this->get("users/" . $user_id . "/export", $options);
    }

    /**
     * @param  string $target_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function banUser($target_id, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $target_id;
        return $this->post("moderation/ban", $options);
    }

    /**
     * @param  string $target_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function unbanUser($target_id, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["target_user_id"] = $target_id;
        return $this->post("moderation/unban", $options);
    }

    /**
     * @param  string $target_id
     * @param  string $user_id
     * @return mixed
     * @throws StreamException
     */
    public function muteUser($target_id, $user_id)
    {
        $options = [];
        $options["target_id"] = $target_id;
        $options["user_id"] = $user_id;
        return $this->post("moderation/mute", $options);
    }

    /**
     * @param  string $target_id
     * @param  string $user_id
     * @return mixed
     * @throws StreamException
     */
    public function unmuteUser($target_id, $user_id)
    {
        $options = [];
        $options["target_id"] = $target_id;
        $options["user_id"] = $user_id;
        return $this->post("moderation/unmute", $options);
    }

    /**
     * @param  string $user_id
     * @return mixed
     * @throws StreamException
     */
    public function markAllRead($user_id)
    {
        $options = [
            "user" => [
                "id" => $user_id
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
            $message_id = $message["id"];
        } catch(Exception $e) {
            throw StreamException("A message must have an id");
        }
        $options = ["message" => $message];
        return $this->post("messages/" . $message_id, $options);
    }

    /**
     * @param  string $message_id
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function deleteMessage($message_id, $options=null)
    {
        return $this->delete("messages/" . $message_id, $options);
    }

    /**
     * @param  array $filter_conditions
     * @param  array $sort
     * @param  array $options
     * @return mixed
     * @throws StreamException
     */
    public function queryUsers($filter_conditions, $sort=null, $options=null)
    {
        $sort_fields = [];
        if($options === null){
            $options = array();
        }
        if($sort !== null){
            foreach($sort as $k => $v){
                $sort_fields[] = ["field" => $k, "direction" => $v];
            }
        }
        $options["filter_conditions"] = $filter_conditions;
        $options["sort"] = $sort_fields;
        return $this->get("users", ["payload" => json_encode($option)]);
    }

}
