<?php

namespace App\Exceptions;

use Exception;

class JwtException extends Exception
{
    public static function tokenExpired(): self
    {
        return new self('JWT token has expired', 401);
    }

    public static function tokenInvalid(): self
    {
        return new self('JWT token is invalid', 401);
    }

    public static function tokenNotProvided(): self
    {
        return new self('JWT token not provided', 401);
    }

    public static function tokenBlacklisted(): self
    {
        return new self('JWT token has been blacklisted', 401);
    }
}
