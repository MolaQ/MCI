<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, ?Model $subject = null, array $properties = [], ?Model $actor = null): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
