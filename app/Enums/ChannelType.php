<?php

namespace App\Enums;

enum ChannelType: string
{
    case TEXT = 'text';
    case VOICE = 'voice';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text Channel',
            self::VOICE => 'Voice Channel',
        };
    }
}
