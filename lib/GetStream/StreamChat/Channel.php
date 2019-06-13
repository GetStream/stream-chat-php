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

class Channel
{

    /**
     * @var string
     */
    protected $channelType;

    /**
     * @var string
     */
    protected $id;

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
    }

   /**
     * @param array $message
     * @param string $userId
     * @return mixed
     * @throws StreamException
     */
    public function sendMessage($message, $userId)
    {
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
    public function deleteReaction($messageId, $reaction, $userId)
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
    public function create($userId)
    {
        $this->customData['created_by'] = ["id" => $userId];
        return $this->query([
            "watch" => false,
            "state" => false,
            "presence" => false
        ]);
    }

   /**
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function query($options)
    {
        if(!in_array("state", $options)){
            $options["state"] = true;
        }
        if(!in_array("data", $options)){
            $options["data"] = $this->customData;
        }

        $url = "channels/" . $this->channelType;
        if($this->id !== null){
            $url .= '/' . $this->id;
        }

        $state = $this->client->post($url . "/query", $payload);

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
    public function update($channelData, $updateMessage)
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
        return $this->client->post($this->getUrl() . "/truncate");
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
        return $this->client->get($this->getUrl() . "/" . $parentId . "/replies", $options);
    }

   /**
     * @param string $messageId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function getReactions($messageId, $options=null)
    {
        return $this->client->get($this->getUrl() . "/" . $messageId . "/reactions", $options);
    }

   /**
     * @param string $userId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function banUser($targetId, $options=null)
    {
        return $this->client->banUser($targetId, $this->channelType, $this->id, $options);
    }

   /**
     * @param string $userId
     * @param array $options
     * @return mixed
     * @throws StreamException
     */
    public function unbanUser($targetId, $options=null)
    {
        return $this->client->unbanUser($targetId, $this->channelType, $this->id, $options);
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function acceptInvite()
    {
        throw new StreamException("Not Implemented");
    }

   /**
     * @return mixed
     * @throws StreamException
     */
    public function rejectInvite()
    {
        throw new StreamException("Not Implemented");
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
