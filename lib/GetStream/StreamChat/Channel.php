<?php

namespace GetStream\StreamChat;

/**
 * Class for handling Stream Chat Channels
 */
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
        if ($data === null) {
            $data = [];
        }
        $this->client = $client;
        $this->channelType = $channelTypeName;
        $this->id = $channelId;
        $this->customData = $data;
    }

    /**
      * @return string
      * @throws StreamException
      */
    private function getUrl()
    {
        if (!$this->id) {
            throw new StreamException("Channel does not (yet) have an id");
        }
        return "channels/" . $this->channelType . '/' . $this->id;
    }

    /**
     * @return string
     */
    public function getCID()
    {
        return "{$this->channelType}:{$this->id}";
    }

    /**
      * @param array $payload
      * @param string $userId
      * @return array
      * @throws StreamException
      */
    private static function addUser($payload, $userId)
    {
        $payload["user"] = ["id" => $userId];
        return $payload;
    }

    /**
      * @param array $message
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendMessage($message, $userId, $parentId=null)
    {
        if ($parentId !== null) {
            $message['parent_id'] = $parentId;
        }
        $payload = [
            "message" => Channel::addUser($message, $userId)
        ];
        return $this->client->post($this->getUrl() . "/message", $payload);
    }

    /** Returns multiple messages.
      * @param array $messageIds
      * @return StreamResponse
      * @throws StreamException
      */
    public function getManyMessages($messageIds)
    {
        return $this->client->get($this->getUrl() . "/messages", ["ids" => implode(",", $messageIds)]);
    }

    /**
      * @param array $event
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendEvent($event, $userId)
    {
        $payload = [
            "event" => Channel::addUser($event, $userId)
        ];
        return $this->client->post($this->getUrl() . "/event", $payload);
    }

    /**
      * @param string $messageId
      * @param array $reaction
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendReaction($messageId, $reaction, $userId)
    {
        $payload = [
            "reaction" => Channel::addUser($reaction, $userId)
        ];
        return $this->client->post(
            "messages/" . $messageId . "/reaction",
            $payload
        );
    }

    /**
      * @param string $messageId
      * @param string $reactionType
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteReaction($messageId, $reactionType, $userId)
    {
        $payload = [
            "user_id" => $userId
        ];
        return $this->client->delete(
            "messages/" . $messageId . "/reaction/" . $reactionType,
            $payload
        );
    }

    /**
      * @param string $userId
      * @return StreamResponse
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
        if ($members !== null) {
            $this->addMembers($members);
        }
        return $response;
    }

    /**
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function query($options)
    {
        if (!array_key_exists("state", $options)) {
            $options["state"] = true;
        }
        if (!array_key_exists("data", $options)) {
            $options["data"] = $this->customData;
        }

        $url = "channels/" . $this->channelType;

        if ($this->id !== null) {
            $url .= '/' . $this->id;
        }

        $state = $this->client->post($url . "/query", $options);

        if ($this->id === null) {
            $this->id = $state["channel"]["id"];
        }

        return $state;
    }

    /**
     * @param array $filterConditions
     * @param array $sort
     * @param array $options
     * @return StreamResponse
     * @throws StreamException
     */
    public function queryMembers($filterConditions = null, $sort = null, $options = null)
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

        if ($this->id !== null) {
            $options["id"] = $this->id;
        } elseif ($this->customData !== null && is_array($this->customData["members"])) {
            // member based channel aka distinct channel
            $options["members"] = $this->customData["members"];
        }
        $options["type"] = $this->channelType;
        if ($filterConditions === null) {
            $filterConditions = (object)[];
        }
        $options["filter_conditions"] = $filterConditions;
        $options["sort"] = $sortFields;
        return $this->client->get("members", ["payload" => json_encode($options)]);
    }

    /**
      * @param array|null $channelData
      * @param array|null $updateMessage
      * @param array|null $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function update($channelData, $updateMessage=null, $options=null)
    {
        $payload = [
            "data" => $channelData,
            "message" => $updateMessage
        ];

        if ($options !== null) {
            $payload = array_merge($payload, $options);
        }

        return $this->client->post($this->getUrl(), $payload);
    }

    /**
     * @param array $set
     * @param array $unset
     * @return StreamResponse
     * @throws StreamException
     */
    public function updatePartial($set = null, $unset = null)
    {
        if ($set === null && $unset === null) {
            throw new StreamException("set or unset is required");
        }
        $update = [
            "set" => $set,
            "unset" => $unset
        ];

        return $this->client->patch($this->getUrl(), $update);
    }

    /**
      * @return StreamResponse
      * @throws StreamException
      */
    public function delete()
    {
        return $this->client->delete($this->getUrl());
    }

    /**
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function truncate($options=null)
    {
        if ($options === null) {
            $options = (object)[];
        }
        return $this->client->post($this->getUrl() . "/truncate", $options);
    }

    /**
      * @param array $userIds
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function addMembers($userIds, $options=null)
    {
        $payload = [
            "add_members" => $userIds
        ];
        if ($options !== null) {
            $payload = array_merge($payload, $options);
        }
        return $this->update(null, null, $payload);
    }

    /**
      * @param array $userIds
      * @return StreamResponse
      * @throws StreamException
      */
    public function addModerators($userIds)
    {
        $payload = [
            "add_moderators" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /**
      * @param array $userIds
      * @return StreamResponse
      * @throws StreamException
      */
    public function removeMembers($userIds)
    {
        $payload = [
            "remove_members" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /**
      * @param array $userIds
      * @return StreamResponse
      * @throws StreamException
      */
    public function demoteModerators($userIds)
    {
        $payload = [
            "demote_moderators" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /**
      * @param string $userId
      * @param array $data
      * @return StreamResponse
      * @throws StreamException
      */
    public function markRead($userId, $data=null)
    {
        if ($data === null) {
            $data = [];
        }
        $payload = Channel::addUser($data, $userId);
        return $this->client->post($this->getUrl() . "/read", $payload);
    }

    /**
      * @param string $parentId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function getReplies($parentId, $options=null)
    {
        return $this->client->get("messages/" . $parentId . "/replies", $options);
    }

    /**
      * @param string $messageId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function getReactions($messageId, $options=null)
    {
        return $this->client->get("messages/" . $messageId . "/reactions", $options);
    }

    /**
      * @param string $targetId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function banUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->banUser($targetId, $options);
    }

    /**
      * @param string $targetId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function unbanUser($targetId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->unbanUser($targetId, $options);
    }

    /**
      * @param array $userIds
      * @param array $message
      * @return StreamResponse
      * @throws StreamException
      */
    public function inviteMembers($userIds, $message = null)
    {
        $payload = [
            "invites" => $userIds,
            "message" => $message
        ];

        return $this->update(null, $message, $payload);
    }

    /**
      * @param string $userId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function acceptInvite($userId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["user_id"] = $userId;
        $options['accept_invite'] = true;
        $response = $this->update(null, null, $options);
        $this->customData = $response['channel'];
        return $response;
    }

    /**
      * @param string $userId
      * @param array $options
      * @return StreamResponse
      * @throws StreamException
      */
    public function rejectInvite($userId, $options=null)
    {
        if ($options === null) {
            $options = [];
        }
        $options["user_id"] = $userId;
        $options['reject_invite'] = true;
        $response = $this->update(null, null, $options);
        $this->customData = $response['channel'];
        return $response;
    }

    /**
      * @param string $url
      * @param string $name
      * @param string|array $user
      * @param string $contentType
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendFile($url, $name, $user, $contentType=null)
    {
        return $this->client->sendFile($this->getUrl() . '/file', $url, $name, $user, $contentType);
    }

    /**
      * @param string $url
      * @param string $name
      * @param string|array $user
      * @param string $contentType
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendImage($url, $name, $user, $contentType=null)
    {
        return $this->client->sendFile($this->getUrl() . '/image', $url, $name, $user, $contentType);
    }

    /**
      * @param string $url
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteFile($url)
    {
        return $this->client->delete($this->getUrl() . '/file', ["url" => $url]);
    }

    /**
      * @param string $url
      * @return StreamResponse
      * @throws StreamException
      */
    public function deleteImage($url)
    {
        return $this->client->delete($this->getUrl() . '/image', ["url" => $url]);
    }

    /**
      * @param array $event
      * @return StreamResponse
      * @throws StreamException
      */
    public function sendCustomEvent($event)
    {
        return $this->client->post($this->getUrl() . '/event', $event);
    }

    /**
      * hides the channel from queryChannels for the user until a message is added
      *
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function hide($userId, $clearHistory=false)
    {
        return $this->client->post(
            $this->getUrl() . '/hide',
            [
                "user_id" => $userId,
                "clear_history" => $clearHistory
            ]
        );
    }

    /**
      * removes the hidden status for a channel
      *
      * @param string $userId
      * @return StreamResponse
      * @throws StreamException
      */
    public function show($userId)
    {
        return $this->client->post($this->getUrl() . '/show', ["user_id" => $userId]);
    }

    /**
     * mutes the channel for the given user
     *
     * @param string $userId
     * @param int $expirationInMilliSeconds
     * @return StreamResponse
     * @throws StreamException
     */
    public function mute($userId, $expirationInMilliSeconds = null)
    {
        $postData = [
            "user_id" => $userId,
            "channel_cid" => $this->getCID(),
        ];
        if ($expirationInMilliSeconds != null) {
            $postData["expiration"] = $expirationInMilliSeconds;
        }
        return $this->client->post("moderation/mute/channel", $postData);
    }

    /**
     * unmutes the channel for the given user
     *
     * @param string $userId
     * @return StreamResponse
     * @throws StreamException
     */
    public function unmute($userId)
    {
        $postData = [
            "user_id" => $userId,
            "channel_cid" => $this->getCID(),
        ];
        return $this->client->post("moderation/unmute/channel", $postData);
    }
}
