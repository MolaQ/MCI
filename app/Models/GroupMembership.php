<?php

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GroupMembership extends Pivot
{
    protected $table = 'group_user';

    protected function casts(): array
    {
        return [
            'role' => GroupRole::class,
        ];
    }
}
