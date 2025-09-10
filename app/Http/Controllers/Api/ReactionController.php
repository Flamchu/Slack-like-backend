<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReactionController extends Controller
{
    /**
     * get message reactions
     */
    public function index(Team $team, Channel $channel, Message $message): JsonResponse
    {
        // verify message in channel
        if ($message->channel_id != $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        $reactions = $message->reactions()
            ->with('user:id,name,email')
            ->get()
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

        return response()->json([
            'message' => 'Reactions retrieved successfully',
            'data' => $reactions
        ]);
    }

    /**
     * add reaction
     */
    public function store(Request $request, Team $team, Channel $channel, Message $message): JsonResponse
    {
        // verify message in channel
        if ($message->channel_id != $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        $request->validate([
            'emoji' => 'required|string|max:50'
        ]);

        $user = Auth::user();

        // check if already reacted
        $existingReaction = Reaction::where([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $request->input('emoji')
        ])->first();

        if ($existingReaction) {
            return response()->json(['error' => 'You have already reacted with this emoji'], 409);
        }

        $reaction = Reaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $request->input('emoji')
        ]);

        return response()->json([
            'message' => 'Reaction added successfully',
            'data' => [
                'id' => $reaction->id,
                'emoji' => $reaction->emoji,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]
        ], 201);
    }

    /**
     * remove reaction
     */
    public function destroy(Team $team, Channel $channel, Message $message, Request $request): JsonResponse
    {
        // verify message in channel
        if ($message->channel_id != $channel->id) {
            return response()->json(['error' => 'Message not found in this channel'], 404);
        }

        $request->validate([
            'emoji' => 'required|string|max:50'
        ]);

        $user = Auth::user();

        $reaction = Reaction::where([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $request->input('emoji')
        ])->first();

        if (!$reaction) {
            return response()->json(['error' => 'Reaction not found'], 404);
        }

        $reaction->delete();

        return response()->json([
            'message' => 'Reaction removed successfully'
        ]);
    }
}
