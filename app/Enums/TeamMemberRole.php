<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case MEMBER = 'member';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::MEMBER => 'Member',
            self::ADMIN => 'Administrator',
            self::OWNER => 'Owner',
        };
    }

    public function hasAdminRights(): bool
    {
        return in_array($this, [self::ADMIN, self::OWNER]);
    }
}
