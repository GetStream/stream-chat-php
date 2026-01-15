<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

/**
 * Provides convenience methods for batch channel operations.
 */
class ChannelBatchUpdater
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Adds members to channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function addMembers(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "addMembers",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Removes members from channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function removeMembers(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "removeMembers",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Invites members to channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function inviteMembers(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "inviteMembers",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Assigns roles to members in channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function assignRoles(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "assignRoles",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Adds moderators to channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function addModerators(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "addModerators",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Removes moderator role from members in channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function demoteModerators(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "demoteModerators",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Hides channels matching the filter for the specified members.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function hide(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "hide",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Shows channels matching the filter for the specified members.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function show(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "show",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Archives channels matching the filter for the specified members.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function archive(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "archive",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Unarchives channels matching the filter for the specified members.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $members Array of member arrays, each with user_id (required) and optional channel_role
     * @return StreamResponse
     * @throws StreamException
     */
    public function unarchive(array $filter, array $members): StreamResponse
    {
        $options = [
            "operation" => "unarchive",
            "filter" => $filter,
            "members" => $members,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Updates data on channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $data Data to update (keys: frozen, disabled, custom, team, config_overrides, auto_translation_enabled, auto_translation_language)
     * @return StreamResponse
     * @throws StreamException
     */
    public function updateData(array $filter, array $data): StreamResponse
    {
        $options = [
            "operation" => "updateData",
            "filter" => $filter,
            "data" => $data,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Adds filter tags to channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $tags Array of filter tag strings
     * @return StreamResponse
     * @throws StreamException
     */
    public function addFilterTags(array $filter, array $tags): StreamResponse
    {
        $options = [
            "operation" => "addFilterTags",
            "filter" => $filter,
            "filter_tags_update" => $tags,
        ];
        return $this->client->updateChannelsBatch($options);
    }

    /**
     * Removes filter tags from channels matching the filter.
     * @param array $filter Filter to match channels (keys: cids, types, filter_tags)
     * @param array $tags Array of filter tag strings
     * @return StreamResponse
     * @throws StreamException
     */
    public function removeFilterTags(array $filter, array $tags): StreamResponse
    {
        $options = [
            "operation" => "removeFilterTags",
            "filter" => $filter,
            "filter_tags_update" => $tags,
        ];
        return $this->client->updateChannelsBatch($options);
    }
}
