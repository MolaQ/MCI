<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One table, two journals: entries with a school_id form that school's log
 * ("Logi szkoły"); entries without one form the site journal ("Dziennik witryny").
 */
class Audit
{
    /**
     * School log entry (active school).
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, ?Model $subject = null, array $properties = [], ?Model $actor = null, ?int $schoolId = null): AuditLog
    {
        return static::write($action, $subject, $properties, $actor, $schoolId ?? app(CurrentSchool::class)->id());
    }

    /**
     * Site journal entry (platform-level decisions).
     *
     * @param  array<string, mixed>  $properties
     */
    public static function site(string $action, ?Model $subject = null, array $properties = [], ?Model $actor = null): AuditLog
    {
        return static::write($action, $subject, $properties, $actor, null);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function write(string $action, ?Model $subject, array $properties, ?Model $actor, ?int $schoolId): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'school_id' => $schoolId,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
