<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\CreateChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Models\Channel;
use App\Models\Team;
use App\Services\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelService $channelService
    ) {
    }

    /**
     * display channels for a team
     */
    public function index(Request $request, string $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $user = $request->user();

        $channels = $this->channelService->getTeamChannels($team, $user);

        return response()->json([
            'message' => 'Channels retrieved successfully',
            'data' => $channels->map(function ($channel) {
                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'type' => $channel->type->value,
                    'is_private' => $channel->is_private,
                    'is_active' => $channel->is_active,
                    'created_at' => $channel->created_at,
                ];
            })
        ]);
    }

    /**
     * create a new channel
     */
    public function store(CreateChannelRequest $request, string $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $user = $request->user();

        $channel = $this->channelService->createChannel($team, $user, $request->validated());

        return response()->json([
            'message' => 'Channel created successfully',
            'data' => [
                'id' => $channel->id,
                'name' => $channel->name,
                'description' => $channel->description,
                'type' => $channel->type->value,
                'is_private' => $channel->is_private,
                'created_at' => $channel->created_at,
            ]
        ], 201);
    }

    /**
     * display the specified channel
     */
    public function show(Request $request, string $teamId, string $id): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $channel = Channel::where('team_id', $team->id)->findOrFail($id);
        $user = $request->user();

        // check if user can access this channel
        if (!$this->channelService->canUserAccessChannel($channel, $user)) {
            return response()->json([
                'error' => 'You do not have access to this channel'
            ], 403);
        }

        $channel->load(['creator', 'team']);

        return response()->json([
            'message' => 'Channel retrieved successfully',
            'data' => [
                'id' => $channel->id,
                'name' => $channel->name,
                'description' => $channel->description,
                'type' => $channel->type->value,
                'is_private' => $channel->is_private,
                'team' => $channel->team ? [
                    'id' => $channel->team->id,
                    'name' => $channel->team->name,
                ] : null,
                'creator' => $channel->creator ? [
                    'id' => $channel->creator->id,
                    'name' => $channel->creator->name,
                ] : null,
                'created_at' => $channel->created_at,
            ]
        ]);
    }

    /**
     * update the specified channel
     */
    public function update(UpdateChannelRequest $request, string $teamId, string $id): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $channel = Channel::where('team_id', $team->id)->findOrFail($id);
        $user = $request->user();

        // check if user can access this channel
        if (!$this->channelService->canUserAccessChannel($channel, $user)) {
            return response()->json([
                'error' => 'You do not have access to this channel'
            ], 403);
        }

        $updatedChannel = $this->channelService->updateChannel($channel, $user, $request->validated());

        return response()->json([
            'message' => 'Channel updated successfully',
            'data' => [
                'id' => $updatedChannel->id,
                'name' => $updatedChannel->name,
                'description' => $updatedChannel->description,
                'type' => $updatedChannel->type->value,
                'is_private' => $updatedChannel->is_private,
                'updated_at' => $updatedChannel->updated_at,
            ]
        ]);
    }

    /**
     * remove the specified channel
     */
    public function destroy(Request $request, string $teamId, string $id): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $channel = Channel::where('team_id', $team->id)->findOrFail($id);
        $user = $request->user();

        // check if user can access this channel
        if (!$this->channelService->canUserAccessChannel($channel, $user)) {
            return response()->json([
                'error' => 'You do not have access to this channel'
            ], 403);
        }

        $this->channelService->deleteChannel($channel, $user);

        return response()->json([
            'message' => 'Channel deleted successfully'
        ], 204);
    }
}
