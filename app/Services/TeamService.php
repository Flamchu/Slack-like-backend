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
     * Create a new team
     */
    public function createTeam(array $data, User $owner): Team
    {
        return DB::transaction(function () use ($data, $owner) {
            // create the team
            $team = Team::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'owner_id' => $owner->id,
                'is_active' => true,
            ]);

            // add owner as team member
            $this->addUserToTeam($team, $owner, TeamMemberRole::OWNER->value);

            return $team;
        });
    }

    /**
     * Invite user to team
     */
    public function inviteUser(Team $team, string $email, User $invitedBy): TeamInvitation
    {
        // check if invitation already exists and is valid
        $existingInvitation = TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return $existingInvitation;
        }

        // new invitation
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
     * Process team invitation acceptance
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

            // mark invitation as used
            $invitation->update([
                'is_used' => true,
                'accepted_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Add user to team
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
     * Remove user from team
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

        // don't allow owner to leave the team
        if ($membership->role === TeamMemberRole::OWNER) {
            return false;
        }

        $membership->update(['is_active' => false]);

        return true;
    }

    /**
     * Check if user is team member
     */
    public function isTeamMember(Team $team, User $user): bool
    {
        return TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if user is team admin
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

        // $membership->role is already a TeamMemberRole enum due to casting
        $role = $membership->role;
        return $role->hasAdminRights();
    }
}