<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case EDITOR = 'editor';
    case JOURNALIST = 'journalist';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::EDITOR => 'Editor',
            self::JOURNALIST => 'Jurnalis',
        };
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::EDITOR], true);
    }
}
