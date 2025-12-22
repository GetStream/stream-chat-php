<?php

declare(strict_types=0);

namespace GetStream\StreamChat;

/**
 * ChannelBatchUpdater - A class that provides convenience methods for batch channel operations
 */
class ChannelBatchUpdater
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    // Member operations

    /**
     * addMembers - Add members to channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Members to add (array of user IDs or array of member arrays with user_id and channel_role)
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function addMembers(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'addMembers',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    /**
     * removeMembers - Remove members from channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Member IDs to remove
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function removeMembers(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'removeMembers',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    /**
     * inviteMembers - Invite members to channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Members to invite (array of user IDs or array of member arrays with user_id and channel_role)
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function inviteMembers(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'invites',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    /**
     * addModerators - Add moderators to channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Member IDs to promote to moderator
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function addModerators(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'addModerators',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    /**
     * demoteModerators - Remove moderator role from members in channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Member IDs to demote
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function demoteModerators(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'demoteModerators',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    /**
     * assignRoles - Assign roles to members in channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $members Members with role assignments (array of arrays with user_id and channel_role)
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function assignRoles(array $filter, array $members): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'assignRoles',
            'filter' => $filter,
            'members' => $members,
        ]);
    }

    // Visibility operations

    /**
     * hide - Hide channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function hide(array $filter): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'hide',
            'filter' => $filter,
        ]);
    }

    /**
     * show - Show channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function show(array $filter): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'show',
            'filter' => $filter,
        ]);
    }

    /**
     * archive - Archive channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function archive(array $filter): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'archive',
            'filter' => $filter,
        ]);
    }

    /**
     * unarchive - Unarchive channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function unarchive(array $filter): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'unarchive',
            'filter' => $filter,
        ]);
    }

    // Data operations

    /**
     * updateData - Update data on channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $data Data to update (frozen, disabled, custom, team, config_overrides, auto_translation_enabled, auto_translation_language)
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function updateData(array $filter, array $data): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'updateData',
            'filter' => $filter,
            'data' => $data,
        ]);
    }

    /**
     * addFilterTags - Add filter tags to channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $tags Tags to add
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function addFilterTags(array $filter, array $tags): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'addFilterTags',
            'filter' => $filter,
            'filter_tags_update' => $tags,
        ]);
    }

    /**
     * removeFilterTags - Remove filter tags from channels matching the filter
     *
     * @param array $filter Filter to select channels
     * @param array $tags Tags to remove
     * @return StreamResponse The server response
     * @throws StreamException
     */
    public function removeFilterTags(array $filter, array $tags): StreamResponse
    {
        return $this->client->updateChannelsBatch([
            'operation' => 'removeFilterTags',
            'filter' => $filter,
            'filter_tags_update' => $tags,
        ]);
    }
}

