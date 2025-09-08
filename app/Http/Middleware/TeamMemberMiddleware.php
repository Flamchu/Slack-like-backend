<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Services\TeamService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamMemberMiddleware
{
    public function __construct(private TeamService $teamService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        // get team ID from route parameters
        $teamId = $request->route('team');

        if (!$teamId) {
            return response()->json([
                'error' => 'Team ID not provided'
            ], 400);
        }

        // find the team
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json([
                'error' => 'Team not found'
            ], 404);
        }

        // check if user is a member of the team
        if (!$this->teamService->isTeamMember($team, $user)) {
            return response()->json([
                'error' => 'Access denied. You are not a member of this team.'
            ], 403);
        }

        $request->merge(['teamModel' => $team]);

        return $next($request);
    }
}
