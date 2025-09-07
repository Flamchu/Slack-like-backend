<?php

namespace App\Exceptions;

use Exception;

class TeamException extends Exception
{
    public static function notFound(): self
    {
        return new self('Team not found', 404);
    }

    public static function notMember(): self
    {
        return new self('You are not a member of this team', 403);
    }

    public static function notAdmin(): self
    {
        return new self('You do not have admin rights for this team', 403);
    }

    public static function alreadyMember(): self
    {
        return new self('User is already a member of this team', 409);
    }

    public static function invitationExpired(): self
    {
        return new self('Team invitation has expired', 410);
    }

    public static function invitationAlreadyUsed(): self
    {
        return new self('Team invitation has already been used', 410);
    }
}
