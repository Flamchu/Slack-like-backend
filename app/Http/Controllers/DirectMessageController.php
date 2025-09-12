<?php

namespace App\Http\Controllers;

use App\Services\DirectMessageService;
use App\Models\User;
use App\Http\Requests\SendDirectMessageRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DirectMessageController extends Controller
{
    protected DirectMessageService $directMessageService;

    public function __construct(DirectMessageService $directMessageService)
    {
        $this->directMessageService = $directMessageService;
    }

    /**
     * send a direct message
     */
    public function store(SendDirectMessageRequest $request): JsonResponse
    {
        $sender = Auth::user();
        $receiver = User::findOrFail($request->receiver_id);

        if (!$this->directMessageService->canMessage($sender, $receiver)) {
            return response()->json(['error' => 'cannot send message to this user'], 403);
        }

        // create base message
        $message = $this->directMessageService->sendMessage(
            $sender->id,
            $receiver->id,
            $request->input('content') ?? '',
            $request->hasFile('file') ? \App\Enums\MessageType::FILE : $request->get('type', 'text')
        );

        // handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->storePublicly('uploads', ['disk' => 'public']);

            $message->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $message->load(['sender', 'receiver', 'attachments']);

        return response()->json([
            'message' => 'message sent successfully',
            'data' => $message
        ], 201);
    }

    /**
     * get conversation between current user and another user
     */
    public function conversation(Request $request, int $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $otherUser = User::findOrFail($userId);

        $conversation = $this->directMessageService->getConversation(
            $currentUser->id,
            $otherUser->id,
            $request->get('per_page', 50)
        );

        // mark messages from other user as read
        $this->directMessageService->markMessagesAsRead($otherUser->id, $currentUser->id);

        return response()->json([
            'conversation' => $conversation,
            'other_user' => $otherUser->only(['id', 'name', 'first_name', 'last_name', 'avatar'])
        ]);
    }

    /**
     * get all conversations for current user
     */
    public function conversations(): JsonResponse
    {
        $currentUser = Auth::user();
        $conversations = $this->directMessageService->getUserConversations($currentUser->id);

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * mark messages as read
     */
    public function markAsRead(Request $request, int $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $markedCount = $this->directMessageService->markMessagesAsRead($userId, $currentUser->id);

        return response()->json([
            'message' => 'messages marked as read',
            'marked_count' => $markedCount
        ]);
    }

    /**
     * get unread message count
     */
    public function unreadCount(): JsonResponse
    {
        $currentUser = Auth::user();
        $count = $this->directMessageService->getUnreadCount($currentUser->id);

        return response()->json(['unread_count' => $count]);
    }

    /**
     * edit a message
     */
    public function update(Request $request, int $messageId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:4000'
        ]);

        $currentUser = Auth::user();

        try {
            $message = $this->directMessageService->editMessage(
                $messageId,
                $currentUser->id,
                $request->input('content')
            );

            return response()->json([
                'message' => 'message updated successfully',
                'data' => $message->load(['sender', 'receiver'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'message not found or unauthorized'], 404);
        }
    }

    /**
     * delete a message
     */
    public function destroy(int $messageId): JsonResponse
    {
        $currentUser = Auth::user();

        $deleted = $this->directMessageService->deleteMessage($messageId, $currentUser->id);

        if (!$deleted) {
            return response()->json(['error' => 'message not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'message deleted successfully']);
    }
}
