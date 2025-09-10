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

        // get team from route parameters (could be ID or Team model depending on route model binding)
        $teamParam = $request->route('team');

        if (!$teamParam) {
            return response()->json([
                'error' => 'Team parameter not provided'
            ], 400);
        }

        // if the parameter is already a Team model instance, use it directly
        // otherwise, treat it as an ID and find the team
        if ($teamParam instanceof Team) {
            $team = $teamParam;
        } else {
            $team = Team::find($teamParam);

            if (!$team) {
                return response()->json([
                    'error' => 'Team not found'
                ], 404);
            }
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
