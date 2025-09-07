<?php

namespace App\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case FILE = 'file';
    case IMAGE = 'image';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text Message',
            self::FILE => 'File Attachment',
            self::IMAGE => 'Image',
        };
    }
}
