<?php

namespace App\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __("enums.user_status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Invited => 'amber',
            self::Active => 'green',
            self::Suspended => 'red',
        };
    }

    public function canLogIn(): bool
    {
        // Invited accounts must first set a password / confirm their e-mail.
        return $this === self::Active;
    }
}
