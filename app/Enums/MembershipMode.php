<?php

namespace App\Enums;

enum MembershipMode: string
{
    /** Only administrators / group leaders add members. */
    case Managed = 'managed';

    /** Members join with an invite link or QR code (Step 2). */
    case Invite = 'invite';

    /** Any signed-in user may join on their own. */
    case Open = 'open';

    public function label(): string
    {
        return __("enums.membership_mode.{$this->value}");
    }
}
