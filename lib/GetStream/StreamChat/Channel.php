<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

/**
 * Class for handling Stream Chat Channels
 */
class Channel
{
    /** Channel type of the channel.
     * @var string
     */
    public $channelType;

    /** The id of the channel.
     * @var string|null
     */
    public $id;

    /**
     * @var array
     */
    private $customData;

    /**
     * @var Client
     */
    private $client;

    /** @internal */
    public function __construct(Client $client, string $channelTypeName, string $channelId = null, array $data = null)
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
     * @throws StreamException if channel id is not set.
     */
    private function getUrl(): string
    {
        if (!$this->id) {
            throw new StreamException("Channel does not (yet) have an id");
        }
        return "channels/" . $this->channelType . '/' . $this->id;
    }

    /** Returns the cid of the channel.
     */
    public function getCID(): string
    {
        return "{$this->channelType}:{$this->id}";
    }

    /**
     * @return string[]
     */
    private static function addUser(array $payload, string $userId)
    {
        $payload["user_id"] = $userId;
        return $payload;
    }

    /** Sends a message to the channel.
     * @link https://getstream.io/chat/docs/php/send_message/?language=php
     * @throws StreamException
     */
    public function sendMessage(array $message, string $userId, string $parentId = null, array $options = null): StreamResponse
    {
        if ($options === null) {
            $options = [];
        }
        if ($parentId !== null) {
            $message['parent_id'] = $parentId;
        }
        $options["message"] = Channel::addUser($message, $userId);
        return $this->client->post($this->getUrl() . "/message", $options);
    }

    /** Returns multiple messages.
     * @link https://getstream.io/chat/docs/php/send_message/?language=php#get-a-message
     * @throws StreamException
     */
    public function getManyMessages(array $messageIds): StreamResponse
    {
        return $this->client->get($this->getUrl() . "/messages", ["ids" => implode(",", $messageIds)]);
    }

    /** Send an event on this channel.
     * @link https://getstream.io/chat/docs/php/custom_events/?language=php
     * @throws StreamException
     */
    public function sendEvent(array $event, string $userId): StreamResponse
    {
        $payload = [
            "event" => Channel::addUser($event, $userId)
        ];
        return $this->client->post($this->getUrl() . "/event", $payload);
    }

    /** Send a reaction about a message.
     * @link https://getstream.io/chat/docs/php/send_reaction/?language=php
     * @throws StreamException
     */
    public function sendReaction(string $messageId, array $reaction, string $userId): StreamResponse
    {
        $payload = [
            "reaction" => Channel::addUser($reaction, $userId)
        ];
        return $this->client->post(
            "messages/" . $messageId . "/reaction",
            $payload
        );
    }

    /** Deletes a reaction from a message.
     * @link https://getstream.io/chat/docs/php/send_reaction/?language=php
     * @throws StreamException
     */
    public function deleteReaction(string $messageId, string $reactionType, string $userId): StreamResponse
    {
        $payload = [
            "user_id" => $userId
        ];
        return $this->client->delete(
            "messages/" . $messageId . "/reaction/" . $reactionType,
            $payload
        );
    }

    /** Creates or returns an existing channel.
     * @link https://getstream.io/chat/docs/php/creating_channels/?language=php
     * @throws StreamException
     */
    public function create(string $userId, array $members = null): StreamResponse
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

    /** Creates or returns an existing channel.
     * @link https://getstream.io/chat/docs/php/query_channels/?language=php
     * @throws StreamException
     */
    public function query(array $options): StreamResponse
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

    /** Query the API for this channel to filter, sort and paginate its members efficiently.
     * @link https://getstream.io/chat/docs/php/query_members/?language=php
     * @throws StreamException
     */
    public function queryMembers(array $filterConditions = null, array $sort = null, array $options = null): StreamResponse
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

    /** Updates a channel.
     * @link https://getstream.io/chat/docs/php/channel_update/?language=php
     * @throws StreamException
     */
    public function update(array $channelData = null, array $updateMessage = null, array $options = null): StreamResponse
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

    /** Update channel partially.
     * @link https://getstream.io/chat/docs/php/channel_update/?language=php
     * @throws StreamException
     */
    public function updatePartial(array $set = null, array $unset = null): StreamResponse
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

    /** Deletes a channel.
     * @link https://getstream.io/chat/docs/php/channel_delete/?language=php
     * @throws StreamException
     */
    public function delete(): StreamResponse
    {
        return $this->client->delete($this->getUrl());
    }

    /** Removes all messages from the channel.
     * @link https://getstream.io/chat/docs/php/truncate_channel/?language=php
     * @throws StreamException
     */
    public function truncate(array $options = null): StreamResponse
    {
        if ($options === null) {
            $options = (object)[];
        }
        return $this->client->post($this->getUrl() . "/truncate", $options);
    }

    /** assignRoles - sets member roles in a channel
     * @link https://getstream.io/chat/docs/php/channel_members/?language=php
     * @throws StreamException
     * @param array $roles
     * @param array|null $message
     * @param array|null $options
     * @return StreamResponse
     */
    public function assignRoles(array $roles, array $message = null, array $options = null): StreamResponse
    {
        $opts = [
            "assign_roles" => $roles
        ];

        if ($options !== null) {
            $opts = array_merge($opts, $options);
        }

        return $this->update(null, $message, $opts);
    }

    /** Adds members to the channel.
     * @link https://getstream.io/chat/docs/php/channel_members/?language=php
     * @throws StreamException
     */
    public function addMembers(array $userIds, array $options = null): StreamResponse
    {
        $payload = [
            "add_members" => $userIds
        ];
        if ($options !== null) {
            $payload = array_merge($payload, $options);
        }
        return $this->update(null, null, $payload);
    }

    /** Remove members from the channel.
     * @link https://getstream.io/chat/docs/php/channel_members/?language=php
     * @throws StreamException
     */
    public function removeMembers(array $userIds): StreamResponse
    {
        $payload = [
            "remove_members" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /** Adds moderators to the channel.
     * @link https://getstream.io/chat/docs/php/moderation/?language=php
     * @throws StreamException
     */
    public function addModerators(array $userIds): StreamResponse
    {
        $payload = [
            "add_moderators" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /** Demotes moderators from the channel.
     * @link https://getstream.io/chat/docs/php/moderation/?language=php
     * @throws StreamException
     */
    public function demoteModerators(array $userIds): StreamResponse
    {
        $payload = [
            "demote_moderators" => $userIds
        ];
        return $this->update(null, null, $payload);
    }

    /** Send the mark read event for this user, only works if
     * the `read_events` setting is enabled.
     * @link https://getstream.io/chat/docs/php/send_message/?language=php
     * @throws StreamException
     */
    public function markRead(string $userId, array $data = null): StreamResponse
    {
        if ($data === null) {
            $data = [];
        }
        $payload = Channel::addUser($data, $userId);
        return $this->client->post($this->getUrl() . "/read", $payload);
    }

    /** List the message replies for a parent message.
     * @link https://getstream.io/chat/docs/php/threads/?language=php
     * @throws StreamException
     */
    public function getReplies(string $parentId, array $options = []): StreamResponse
    {
        return $this->client->get("messages/" . $parentId . "/replies", $options);
    }

    /** List the reactions, supports pagination.
     * @link https://getstream.io/chat/docs/php/send_reaction/?language=php
     * @throws StreamException
     */
    public function getReactions(string $messageId, array $options = []): StreamResponse
    {
        return $this->client->get("messages/" . $messageId . "/reactions", $options);
    }

    /** ans a user from this channel.
     * @link https://getstream.io/chat/docs/php/moderation/?language=php
     * @throws StreamException
     */
    public function banUser(string $targetId, array $options = null): StreamResponse
    {
        if ($options === null) {
            $options = [];
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->banUser($targetId, $options);
    }

    /** Removes the ban for a user on this channel.
     * @link https://getstream.io/chat/docs/php/moderation/?language=php
     * @throws StreamException
     */
    public function unbanUser(string $targetId, array $options = null): StreamResponse
    {
        if ($options === null) {
            $options = [];
        }
        $options["type"] = $this->channelType;
        $options["id"] = $this->id;
        return $this->client->unbanUser($targetId, $options);
    }

    /** Invite members to the channel.
     * @link https://getstream.io/chat/docs/php/channel_invites/?language=php
     * @throws StreamException
     */
    public function inviteMembers(array $userIds, array $message = null): StreamResponse
    {
        $payload = [
            "invites" => $userIds,
            "message" => $message
        ];

        return $this->update(null, $message, $payload);
    }

    /** Accepts an invitation to this channel.
     * @link https://getstream.io/chat/docs/php/channel_invites/?language=php
     * @throws StreamException
     */
    public function acceptInvite(string $userId, array $options = null): StreamResponse
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

    /** Rejects an invitation to this channel.
     * @link https://getstream.io/chat/docs/php/channel_invites/?language=php
     * @throws StreamException
     */
    public function rejectInvite(string $userId, array $options = null): StreamResponse
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

    /** Uploads a file.
     * This functionality defaults to using the Stream CDN. If you would like, you can
     * easily change the logic to upload to your own CDN of choice.
     * @link https://getstream.io/chat/docs/php/file_uploads/?language=php
     * @throws StreamException
     */
    public function sendFile(string $url, string $name, array $user, string $contentType = null): StreamResponse
    {
        return $this->client->sendFile($this->getUrl() . '/file', $url, $name, $user, $contentType);
    }

    /** Uploads an image.
     * Stream supported image types are: image/bmp, image/gif, image/jpeg, image/png, image/webp,
     * image/heic, image/heic-sequence, image/heif, image/heif-sequence, image/svg+xml.
     * You can set a more restrictive list for your application if needed.
     * The maximum file size is 100MB.
     * @link https://getstream.io/chat/docs/php/file_uploads/?language=php
     * @throws StreamException
     */
    public function sendImage(string $url, string $name, array $user, string $contentType = null): StreamResponse
    {
        return $this->client->sendFile($this->getUrl() . '/image', $url, $name, $user, $contentType);
    }

    /** Deletes a file by file url.
     * @link https://getstream.io/chat/docs/php/file_uploads/?language=php
     * @throws StreamException
     */
    public function deleteFile(string $url): StreamResponse
    {
        return $this->client->delete($this->getUrl() . '/file', ["url" => $url]);
    }

    /** Deletes an image by image url.
     * @link https://getstream.io/chat/docs/php/file_uploads/?language=php
     * @throws StreamException
     */
    public function deleteImage(string $url): StreamResponse
    {
        return $this->client->delete($this->getUrl() . '/image', ["url" => $url]);
    }

    /** Removes a channel from query channel requests for that user until a new message is added.
     * Use `show` to cancel this operation.
     * @link https://getstream.io/chat/docs/php/muting_channels/?language=php
     * hides the channel from queryChannels for the user until a message is added
     * @throws StreamException
     */
    public function hide(string $userId, bool $clearHistory = false): StreamResponse
    {
        return $this->client->post(
            $this->getUrl() . '/hide',
            [
                "user_id" => $userId,
                "clear_history" => $clearHistory
            ]
        );
    }

    /** Shows a previously hidden channel.
     * Use `hide` to hide a channel.
     * @link https://getstream.io/chat/docs/php/muting_channels/?language=php
     * @throws StreamException
     */
    public function show(string $userId): StreamResponse
    {
        return $this->client->post($this->getUrl() . '/show', ["user_id" => $userId]);
    }

    /**
     * Mutes the channel for the given user.
     * @link https://getstream.io/chat/docs/php/muting_channels/?language=php
     * @throws StreamException
     */
    public function mute(string $userId, int $expirationInMilliSeconds = null): StreamResponse
    {
        $postData = [
            "user_id" => $userId,
            "channel_cid" => $this->getCID(),
        ];
        if ($expirationInMilliSeconds !== null) {
            $postData["expiration"] = $expirationInMilliSeconds;
        }
        return $this->client->post("moderation/mute/channel", $postData);
    }

    /** Unmutes the channel for the given user.
     * @link https://getstream.io/chat/docs/php/muting_channels/?language=php
     * @throws StreamException
     */
    public function unmute(string $userId): StreamResponse
    {
        $postData = [
            "user_id" => $userId,
            "channel_cid" => $this->getCID(),
        ];
        return $this->client->post("moderation/unmute/channel", $postData);
    }
}
