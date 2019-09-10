<?php

namespace GetStream\StreamChat;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;


class Channel
{

    /**
     * @var string
     */
    protected $channelType;

    /**
     * @var string
     */
    public $id;

    /**
     * @var array
     */
    protected $customData;

    /**
     * @var Client
     */
    protected $client;

    public function __construct($client, $channelTypeName, $channelId=null, $data=null)
    {
        if($data === null){
            $data = array();
        }
        $this->client = $client;
        $this->channelType = $channelTypeName;
        $this->id = $channelId;
        $this->customData = $data;
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    private function getUrl()
    {
        if(!$this->id){
            throw new StreamException("Channel does not (yet) have an id");
        }
        return "channels/" . $this->channelType . '/' . $this->id;
    }

   /**
     * @param array $payload
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    private function addUser($payload, $userId)
    {
        $payload["user"] = ["id" => $userId];
        return $payload;
    }

   /**
     * @param array $message
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function sendMessage($message, $userId, $parentId=null)
    {
        if($parentId !== null){
            $message['parent_id'] = $parentId;
        }
        $payload = [
            "message" => $this->addUser($message, $userId)
        ];
        return $this->client->post($this->getUrl() . "/message", $payload);
    }

   /**
     * @param array $event
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function sendEvent($event, $userId)
    {
        $payload = [
            "event" => $this->addUser($event, $userId)
        ];
        return $this->client->post($this->getUrl() . "/event", $payload);
    }

   /**
     * @param string $messageId
     * @param array $reaction
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function sendReaction($messageId, $reaction, $userId)
    {
        $payload = [
            "reaction" => $this->addUser($reaction, $userId)
        ];
        return $this->client->post(
            "messages/" . $messageId . "/reaction",
            $payload);
    }

   /**
     * @param string $messageId
     * @param string $reactionType
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function deleteReaction($messageId, $reactionType, $userId)
    {
        $payload = [
            "user_id" => $userId
        ];
        return $this->client->delete(
            "messages/" . $messageId . "/reaction/" . $reactionType,
            $payload);
    }

   /**
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function create($userId, $members=null)
    {
        $this->customData['created_by'] = ["id" => $userId];
        $response = $this->query([
            "watch" => false,
            "state" => false,
            "presence" => false
        ]);
        if($members !== null){
            $this->addMembers($members);
        }
        return $response;
    }

   /**
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function query($options)
    {
        if(!array_key_exists("state", $options)){
            $options["state"] = true;
        }
        if(!array_key_exists("data", $options)){
            $options["data"] = $this->customData;
        }

        $url = "channels/" . $this->channelType;

        if($this->id !== null){
            $url .= '/' . $this->id;
        }

        $state = $this->client->post($url . "/query", $options);

        if($this->id === null){
            $this->id = $state["channel"]["id"];
        }

        return $state;
    }

   /**
     * @param array $channelData
     * @param string $updateMessage
     * @return mixed
     * @throws StreamException
     */
    public function update($channelData, $updateMessage=null)
    {
        $payload = [
            "data" => $channelData,
            "message" => $updateMessage
        ];
        return $this->client->post($this->getUrl(), $payload);
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function delete()
    {
        return $this->client->delete($this->getUrl());
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function truncate()
    {
        // need to post 'some' json?
        $options = (object)array();
        return $this->client->post($this->getUrl() . "/truncate", $options);
    }

   /**
     * @param array $userIds
     * @return mixed
     * @throws StreamException
     */
    public function addMembers($userIds)
    {
        $payload = [
            "add_members" => $userIds
        ];
        return $this->client->post($this->getUrl(), $payload);
    }

   /**
     * @param array $userIds
     * @return mixed
     * @throws StreamException
     */
    public function addModerators($userIds)
    {
        $payload = [
            "add_moderators" => $userIds
        ];
        return $this->client->post($this->getUrl(), $payload);
    }

   /**
     * @param array $userIds
     * @return mixed
     * @throws StreamException
     */
    public function removeMembers($userIds)
    {
        $payload = [
            "remove_members" => $userIds
        ];
        return $this->client->post($this->getUrl(), $payload);
    }

   /**
     * @param array $userIds
     * @return mixed
     * @throws StreamException
     */
    public function demoteModerators($userIds)
    {
        $payload = [
            "demote_moderators" => $userIds
        ];
        return $this->client->post($this->getUrl(), $payload);
    }

   /**
     * @param string $userId
     * @param array $data
     * @return mixed
     * @throws StreamException
     */
    public function markRead($userId, $data=null)
    {
        if($data === null){
            $data = array();
        }
        $payload = $this->addUser($data, $userId);
        return $this->client->post($this->getUrl() . "/read", $payload);
    }

   /**
     * @param string $parentId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function getReplies($parentId, $options=null)
    {
        return $this->client->get("messages/" . $parentId . "/replies", $options);
    }

   /**
     * @param string $messageId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function getReactions($messageId, $options=null)
    {
        return $this->client->get("messages/" . $messageId . "/reactions", $options);
    }

   /**
     * @param string $targetId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function banUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->banUser($targetId, $options);
    }

   /**
     * @param string $targetId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function unbanUser($targetId, $options=null)
    {
        if($options === null){
            $options = array();
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->unbanUser($targetId, $options);
    }

   /**
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function acceptInvite($options=null)
    {
        if($options === null){
            $options = array();
        }
        $options['accept_invite'] = true;
        $response = $this->client->post($this->getUrl(), $options);
        $this->customData = $response['channel'];
        return $response;
    }

   /**
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function rejectInvite($options=null)
    {
        if($options === null){
            $options = array();
        }
        $options['reject_invite'] = true;
        $response = $this->client->post($this->getUrl(), $options);
        $this->customData = $response['channel'];
        return $response;
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function sendFile()
    {
        throw new StreamException("Not Implemented");
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function sendImage()
    {
        throw new StreamException("Not Implemented");
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function deleteFile()
    {
        throw new StreamException("Not Implemented");
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function deleteImage()
    {
        throw new StreamException("Not Implemented");
    }

}
