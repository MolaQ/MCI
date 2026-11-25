<?php

namespace App\Enums;

/**
 * One row of an audience (docs/ARCHITECTURE.md §3.3). Recipients are the
 * deduplicated union of all rows.
 */
enum AudienceTargetType: string
{
    case Everyone = 'everyone';
    case Role = 'role';
    case Group = 'group';
    case User = 'user';
}
