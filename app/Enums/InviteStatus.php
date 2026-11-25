<?php

namespace App\Enums;

enum InviteStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case UsedUp = 'used_up';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Aktywne'),
            self::Expired => __('Wygasło'),
            self::UsedUp => __('Wykorzystane'),
            self::Revoked => __('Unieważnione'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Expired => 'zinc',
            self::UsedUp => 'blue',
            self::Revoked => 'red',
        };
    }
}
