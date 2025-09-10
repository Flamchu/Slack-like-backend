<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ChannelService
{
    /**
     * get team channels
     */
    public function getTeamChannels(Team $team, User $user, bool $includePrivate = false): Collection
    {
        $query = $team->channels()->active();

        if (!$includePrivate) {
            $query->public();
        }

        return $query->with(['creator', 'recentMessages.user'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * create new channel
     */
    public function createChannel(Team $team, User $user, array $data): Channel
    {
        $data['team_id'] = $team->id;
        $data['created_by'] = $user->id;
        $data['type'] = $data['type'] ?? ChannelType::TEXT;

        $channel = Channel::create($data);

        // Log the activity
        app(ActivityLogService::class)->log(
            'channel_created',
            "Created channel: {$channel->name}",
            $user,
            $team,
            ['channel_id' => $channel->id, 'channel_name' => $channel->name]
        );

        return $channel->load(['creator', 'team']);
    }

    /**
     * update channel
     */
    public function updateChannel(Channel $channel, User $user, array $data): Channel
    {
        $originalName = $channel->name;
        $channel->update($data);

        // Log the activity if name changed
        if (isset($data['name']) && $data['name'] !== $originalName) {
            app(ActivityLogService::class)->log(
                'channel_renamed',
                "Renamed channel from '{$originalName}' to '{$channel->name}'",
                $user,
                $channel->team,
                ['channel_id' => $channel->id, 'old_name' => $originalName, 'new_name' => $channel->name]
            );
        }

        return $channel->fresh(['creator', 'team']);
    }

    /**
     * delete channel
     */
    public function deleteChannel(Channel $channel, User $user): bool
    {
        $channelName = $channel->name;
        $team = $channel->team;

        $deleted = $channel->delete();

        if ($deleted) {
            // Log the activity
            app(ActivityLogService::class)->log(
                'channel_deleted',
                "Deleted channel: {$channelName}",
                $user,
                $team,
                ['channel_name' => $channelName]
            );
        }

        return $deleted;
    }

    /**
     * get channel with messages
     */
    public function getChannelWithMessages(Channel $channel, int $page = 1, int $perPage = 50): array
    {
        $messages = $channel->messages()
            ->topLevel() // top-level messages only
            ->with(['user', 'replies.user'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'channel' => $channel->load(['creator', 'team']),
            'messages' => $messages
        ];
    }

    /**
     * check channel access
     */
    public function canUserAccessChannel(Channel $channel, User $user): bool
    {
        return $channel->isAccessibleBy($user);
    }

    /**
     * create default channels
     */
    public function createDefaultChannels(Team $team, User $creator): Collection
    {
        $defaultChannels = [
            [
                'name' => 'general',
                'description' => 'General team discussion',
                'type' => ChannelType::TEXT,
                'is_private' => false,
            ],
            [
                'name' => 'random',
                'description' => 'Random conversations and fun',
                'type' => ChannelType::TEXT,
                'is_private' => false,
            ]
        ];

        $channels = collect();

        foreach ($defaultChannels as $channelData) {
            $channel = $this->createChannel($team, $creator, $channelData);
            $channels->push($channel);
        }

        return $channels;
    }

    /**
     * search channels
     */
    public function searchChannels(Team $team, User $user, string $search): Collection
    {
        return $team->channels()
            ->active()
            ->public() // public channels only
            ->where('name', 'like', "%{$search}%")
            ->with(['creator'])
            ->orderBy('name')
            ->get();
    }
}
