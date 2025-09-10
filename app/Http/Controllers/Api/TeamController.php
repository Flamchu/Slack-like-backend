<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateTeamRequest;
use App\Http\Requests\Team\InviteUserRequest;
use App\Http\Requests\Team\JoinTeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function __construct(
        private TeamService $teamService,
        private ActivityLogService $activityLogService
    ) {
    }

    /**
     * display teams the user belongs to
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teams = $user->teams()
            ->with(['owner', 'members'])
            ->withCount('members')
            ->get();

        return response()->json([
            'message' => 'Teams retrieved successfully',
            'data' => $teams
        ]);
    }

    /**
     * create a new team
     */
    public function store(CreateTeamRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $team = $this->teamService->createTeam($request->validated(), $user);

            $this->activityLogService->log(
                'team_created',
                "Team '{$team->name}' was created",
                $user,
                $team,
                ['team_name' => $team->name],
                $request
            );

            return response()->json([
                'message' => 'Team created successfully',
                'data' => $team->load(['owner', 'members'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create team',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * display the specified team
     */
    public function show(string $id): JsonResponse
    {
        try {
            $team = Team::with(['owner', 'members', 'channels'])
                ->withCount('members')
                ->findOrFail($id);

            return response()->json([
                'message' => 'Team retrieved successfully',
                'data' => $team
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Team not found'
            ], 404);
        }
    }

    /**
     * update the specified team
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            $user = $request->user();

            // check if user is team admin
            if (!$this->teamService->isTeamAdmin($team, $user)) {
                return response()->json([
                    'error' => 'Unauthorized. Only team admins can update team details.'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|nullable|string|max:1000',
                'avatar' => 'sometimes|nullable|string|max:255',
            ]);

            $team->update($validated);

            $this->activityLogService->log(
                'team_updated',
                "Team '{$team->name}' was updated",
                $user,
                $team,
                ['updated_fields' => array_keys($validated)],
                $request
            );

            return response()->json([
                'message' => 'Team updated successfully',
                'data' => $team->load(['owner', 'members'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update team',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * remove the specified team
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            $user = auth()->user();

            // only team owner can delete the team
            if ($team->owner_id !== $user->id) {
                return response()->json([
                    'error' => 'Unauthorized. Only team owner can delete the team.'
                ], 403);
            }

            $this->activityLogService->log(
                'team_deleted',
                "Team '{$team->name}' was deleted",
                $user,
                $team,
                ['team_name' => $team->name],
                request()
            );

            $team->delete();

            return response()->json([
                'message' => 'Team deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete team',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * get team members
     */
    public function members(string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            $members = $team->members()
                ->withPivot(['role', 'joined_at'])
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar,
                        'role' => $member->pivot->role,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                });

            return response()->json([
                'message' => 'Team members retrieved successfully',
                'data' => $members
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Team not found'
            ], 404);
        }
    }

    /**
     * invite user to team
     */
    public function invite(InviteUserRequest $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            $user = $request->user();

            // check if user is team admin
            if (!$this->teamService->isTeamAdmin($team, $user)) {
                return response()->json([
                    'error' => 'Unauthorized. Only team admins can invite users.'
                ], 403);
            }

            $email = $request->validated()['email'];

            // check if user is already a member
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $this->teamService->isTeamMember($team, $existingUser)) {
                return response()->json([
                    'error' => 'User is already a member of this team'
                ], 409);
            }

            $invitation = $this->teamService->inviteUser($team, $email, $user);

            $this->activityLogService->log(
                'user_invited',
                "User with email '{$email}' was invited to team '{$team->name}'",
                $user,
                $team,
                ['invited_email' => $email],
                $request
            );

            return response()->json([
                'message' => 'User invited successfully',
                'data' => [
                    'invitation_token' => $invitation->token,
                    'expires_at' => $invitation->expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to invite user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * join team via invitation
     */
    public function join(JoinTeamRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $request->validated()['token'];

            $success = $this->teamService->acceptInvitation($token, $user);

            if (!$success) {
                return response()->json([
                    'error' => 'Invalid or expired invitation token'
                ], 400);
            }

            // get the team info for response
            $invitation = \App\Models\TeamInvitation::where('token', $token)->first();
            $team = $invitation->team;

            $this->activityLogService->log(
                'user_joined',
                "User '{$user->name}' joined team '{$team->name}'",
                $user,
                $team,
                ['via' => 'invitation'],
                $request
            );

            return response()->json([
                'message' => 'Successfully joined the team',
                'data' => $team->load(['owner', 'members'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to join team',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * leave team
     */
    public function leave(string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            $user = auth()->user();

            $success = $this->teamService->removeUserFromTeam($team, $user);

            if (!$success) {
                return response()->json([
                    'error' => 'Cannot leave team. You might be the owner or not a member.'
                ], 400);
            }

            $this->activityLogService->log(
                'user_left',
                "User '{$user->name}' left team '{$team->name}'",
                $user,
                $team,
                [],
                request()
            );

            return response()->json([
                'message' => 'Successfully left the team'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to leave team',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
