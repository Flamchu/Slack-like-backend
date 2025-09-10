<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Enums\TeamMemberRole;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TeamService
{
    /**
     * create new team
     */
    public function createTeam(array $data, User $owner): Team
    {
        return DB::transaction(function () use ($data, $owner) {
            // create team
            $team = Team::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'owner_id' => $owner->id,
                'is_active' => true,
            ]);

            // add owner as member
            $this->addUserToTeam($team, $owner, TeamMemberRole::OWNER->value);

            return $team;
        });
    }

    /**
     * invite user to team
     */
    public function inviteUser(Team $team, string $email, User $invitedBy): TeamInvitation
    {
        // check if invitation exists
        $existingInvitation = TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return $existingInvitation;
        }

        // create invitation
        return TeamInvitation::create([
            'team_id' => $team->id,
            'email' => $email,
            'invited_by' => $invitedBy->id,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'is_used' => false,
        ]);
    }

    /**
     * accept team invitation
     */
    public function acceptInvitation(string $token, User $user): bool
    {
        $invitation = TeamInvitation::where('token', $token)
            ->where('email', $user->email)
            ->first();

        if (!$invitation || !$invitation->isValid()) {
            return false;
        }

        return DB::transaction(function () use ($invitation, $user) {
            $this->addUserToTeam($invitation->team, $user, TeamMemberRole::MEMBER->value, $invitation->invited_by);

            // mark as used
            $invitation->update([
                'is_used' => true,
                'accepted_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * add user to team
     */
    public function addUserToTeam(Team $team, User $user, string $role = 'member', ?int $invitedBy = null): bool
    {
        if ($this->isTeamMember($team, $user)) {
            return true;
        }

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'invited_by' => $invitedBy,
            'is_active' => true,
        ]);

        return true;
    }

    /**
     * remove user from team
     */
    public function removeUserFromTeam(Team $team, User $user): bool
    {
        $membership = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return false;
        }

        // owner cannot leave
        if ($membership->role === TeamMemberRole::OWNER) {
            return false;
        }

        $membership->update(['is_active' => false]);

        return true;
    }

    /**
     * check if user is member
     */
    public function isTeamMember(Team $team, User $user): bool
    {
        return TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * check if user is admin
     */
    public function isTeamAdmin(Team $team, User $user): bool
    {
        $membership = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return false;
        }

        $role = $membership->role;
        return $role->hasAdminRights();
    }
}