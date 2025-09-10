<?php

namespace App\Services;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DirectMessageService
{
    /**
     * send a direct message
     */
    public function sendMessage(int $senderId, int $receiverId, string $content, string $type = 'text'): DirectMessage
    {
        return DirectMessage::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content,
            'type' => $type,
        ]);
    }

    /**
     * get conversation between two users
     */
    public function getConversation(int $userId1, int $userId2, int $perPage = 50): LengthAwarePaginator
    {
        return DirectMessage::conversation($userId1, $userId2)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * get all conversations for a user
     */
    public function getUserConversations(int $userId): Collection
    {
        $conversations = DirectMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($message) use ($userId) {
                return $message->sender_id === $userId ? $message->receiver_id : $message->sender_id;
            })
            ->map(function ($messages) {
                return $messages->first(); // latest message in each conversation
            });

        return $conversations;
    }

    /**
     * mark messages as read
     */
    public function markMessagesAsRead(int $senderId, int $receiverId): int
    {
        return DirectMessage::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * get unread message count for user
     */
    public function getUnreadCount(int $userId): int
    {
        return DirectMessage::where('receiver_id', $userId)
            ->unread()
            ->count();
    }

    /**
     * edit a message
     */
    public function editMessage(int $messageId, int $userId, string $content): DirectMessage
    {
        $message = DirectMessage::where('id', $messageId)
            ->where('sender_id', $userId)
            ->firstOrFail();

        $message->update([
            'content' => $content,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return $message;
    }

    /**
     * delete a message
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        return DirectMessage::where('id', $messageId)
            ->where('sender_id', $userId)
            ->delete() > 0;
    }

    /**
     * check if users can message each other
     */
    public function canMessage(User $sender, User $receiver): bool
    {
        // basic check - both users must be active
        return $sender->is_active && $receiver->is_active;
    }
}
