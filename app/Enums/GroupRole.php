<?php

namespace App\Enums;

enum GroupRole: string
{
    case Member = 'member';
    case Leader = 'leader';

    public function label(): string
    {
        return __("enums.group_role.{$this->value}");
    }
}
