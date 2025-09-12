<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Team;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    protected MessageService $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * display messages for a channel
     */
    public function index(Request $request, Team $team, Channel $channel): JsonResponse
    {
        $messages = $channel->messages()
            ->with([
                'user:id,name,email',
                'parent.user:id,name,email',
                'replies.user:id,name,email',
                'reactions.user:id,name,email'
            ])
            ->topLevel() // only get top-level messages, not replies
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $formattedMessages = $messages->getCollection()->map(function ($message) {
            return $this->formatMessage($message);
        });

        return response()->json([
            'message' => 'Messages retrieved successfully',
            'data' => $formattedMessages,
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ]);
    }

    /**
     * store a new message
     */
    public function store(Request $request, Team $team, Channel $channel): JsonResponse
    {
        // validate either content or file
        $request->validate([
            'content' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:messages,id',
            'file' => 'sometimes|file|max:20480',
        ]);

        // create message record
        $message = $this->messageService->createMessage(
            $channel,
            Auth::user(),
            [
                'content' => $request->input('content') ?? '',
                'parent_id' => $request->input('parent_id'),
                'type' => $request->hasFile('file') ? \App\Enums\MessageType::FILE : \App\Enums\MessageType::TEXT,
            ]
        );

        // handle file upload if present
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->storePublicly('uploads', ['disk' => 'public']);

            $attachment = $message->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $message->load(['user:id,name,email', 'reactions.user:id,name,email', 'attachments']);

        return response()->json([
            'message' => 'message created successfully',
            'data' => $this->formatMessage($message)
        ], 201);
    }

    /**
     * display a specific message
     */
    public function show(Team $team, Channel $channel, Message $message): JsonResponse
    {
        // verify message belongs to the channel
        if ($message->channel_id !== $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        $message->load([
            'user:id,name,email',
            'parent.user:id,name,email',
            'replies.user:id,name,email',
            'reactions.user:id,name,email'
        ]);

        return response()->json([
            'message' => 'Message retrieved successfully',
            'data' => $this->formatMessage($message)
        ]);
    }

    /**
     * update a message
     */
    public function update(Request $request, Team $team, Channel $channel, Message $message): JsonResponse
    {
        // verify message belongs to the channel
        if ($message->channel_id !== $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        // verify user owns the message
        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'You can only edit your own messages'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:2000'
        ]);

        $updatedMessage = $this->messageService->updateMessage(
            $message,
            Auth::user(),
            ['content' => $request->input('content')]
        );

        $updatedMessage->load(['user:id,name,email', 'reactions.user:id,name,email']);

        return response()->json([
            'message' => 'Message updated successfully',
            'data' => $this->formatMessage($updatedMessage)
        ]);
    }

    /**
     * delete a message
     */
    public function destroy(Team $team, Channel $channel, Message $message): JsonResponse
    {
        // verify message belongs to the channel
        if ($message->channel_id !== $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        // verify user owns the message
        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'You can only delete your own messages'], 403);
        }

        $this->messageService->deleteMessage($message, Auth::user());

        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }

    /**
     * format message for api response
     */
    private function formatMessage(Message $message): array
    {
        $reactions = $message->reactions
            ->groupBy('emoji')
            ->map(function ($reactions, $emoji) {
                return [
                    'emoji' => $emoji,
                    'count' => $reactions->count(),
                    'users' => $reactions->map(function ($reaction) {
                        return [
                            'id' => $reaction->user->id,
                            'name' => $reaction->user->name,
                            'email' => $reaction->user->email,
                        ];
                    })
                ];
            })->values();

        return [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type->value,
            'is_edited' => $message->is_edited,
            'edited_at' => $message->edited_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
            'updated_at' => $message->updated_at->toISOString(),
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'email' => $message->user->email,
            ],
            'parent' => $message->parent ? [
                'id' => $message->parent->id,
                'content' => $message->parent->content,
                'user' => [
                    'id' => $message->parent->user->id,
                    'name' => $message->parent->user->name,
                    'email' => $message->parent->user->email,
                ]
            ] : null,
            'replies_count' => $message->replies->count(),
            'reactions' => $reactions,
            'attachments' => $message->attachments->map(function ($att) {
                return [
                    'id' => $att->id,
                    'file_name' => $att->file_name,
                    'url' => Storage::url($att->file_path),
                    'mime_type' => $att->mime_type,
                    'size' => $att->size,
                ];
            })->values(),
        ];
    }
}
