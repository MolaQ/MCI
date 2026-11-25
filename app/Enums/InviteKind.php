<?php

namespace App\Enums;

enum InviteKind: string
{
    /** Bound to one e-mail address, single use, delivered by e-mail. */
    case Personal = 'personal';

    /** Multi-use link / QR poster for one or more groups. */
    case GroupLink = 'group_link';

    public function label(): string
    {
        return __("enums.invite_kind.{$this->value}");
    }
}
