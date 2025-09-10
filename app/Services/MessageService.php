<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class MessageService
{
    /**
     * get channel messages
     */
    public function getChannelMessages(Channel $channel, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        return $channel->messages()
            ->topLevel() // top-level messages only
            ->with(['user', 'replies.user'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * create new message
     */
    public function createMessage(Channel $channel, User $user, array $data): Message
    {
        $data['channel_id'] = $channel->id;
        $data['user_id'] = $user->id;
        $data['type'] = $data['type'] ?? MessageType::TEXT;

        $message = Message::create($data);

        // log the activity
        app(ActivityLogService::class)->log(
            'message_sent',
            "Sent a message in #{$channel->name}",
            $user,
            $channel->team,
            [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'message_id' => $message->id,
                'is_reply' => !is_null($data['parent_id'] ?? null)
            ]
        );

        return $message->load(['user', 'channel', 'parent']);
    }

    /**
     * update message
     */
    public function updateMessage(Message $message, User $user, array $data): Message
    {
        // only update content
        $message->update([
            'content' => $data['content']
        ]);

        // mark as edited
        $message->markAsEdited();

        $message->save();

        // log the activity
        app(ActivityLogService::class)->log(
            'message_edited',
            "Edited a message in #{$message->channel->name}",
            $user,
            $message->channel->team,
            [
                'channel_id' => $message->channel->id,
                'channel_name' => $message->channel->name,
                'message_id' => $message->id
            ]
        );

        return $message->fresh(['user', 'channel', 'parent']);
    }

    /**
     * delete message
     */
    public function deleteMessage(Message $message, User $user): bool
    {
        $channelName = $message->channel->name;
        $team = $message->channel->team;
        $messageId = $message->id;

        $deleted = $message->delete();

        if ($deleted) {
            // Log the activity
            app(ActivityLogService::class)->log(
                'message_deleted',
                "Deleted a message in #{$channelName}",
                $user,
                $team,
                [
                    'channel_name' => $channelName,
                    'message_id' => $messageId
                ]
            );
        }

        return $deleted;
    }

    /**
     * get message replies
     */
    public function getMessageReplies(Message $message, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $message->replies()
            ->with(['user'])
            ->oldest() // chronological order
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * reply to message
     */
    public function replyToMessage(Message $parentMessage, User $user, array $data): Message
    {
        $data['parent_id'] = $parentMessage->id;
        return $this->createMessage($parentMessage->channel, $user, $data);
    }

    /**
     * check if user can modify
     */
    public function canUserModifyMessage(Message $message, User $user): bool
    {
        // user can modify own messages
        if ($message->user_id === $user->id) {
            return true;
        }

        // admins can modify any message
        $teamMember = $message->channel->team->teamMembers()
            ->where('user_id', $user->id)
            ->first();

        return $teamMember && in_array($teamMember->role->value, ['admin', 'owner']);
    }

    /**
     * search channel messages
     */
    public function searchChannelMessages(Channel $channel, string $search, int $perPage = 20): LengthAwarePaginator
    {
        return $channel->messages()
            ->where('content', 'like', "%{$search}%")
            ->with(['user'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * get user recent messages
     */
    public function getUserRecentMessages(User $user, int $limit = 10)
    {
        $teamIds = $user->teams()->pluck('teams.id');

        return Message::whereHas('channel', function ($query) use ($teamIds) {
            $query->whereIn('team_id', $teamIds)
                ->where('is_active', true)
                ->where('is_private', false);
        })
            ->with(['user', 'channel.team'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
