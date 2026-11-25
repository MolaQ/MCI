<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Bell notifications for a published announcement. Idempotent: only
 * recipients with notified_at = null are handled, so the scheduler sweep
 * (mci:deliver-announcements) can safely run it again.
 *
 * In-app delivery is the feed itself (recipient rows); the bell is for
 * what deserves interruption: mentions, important/urgent posts and posts
 * that ask for confirmation. Web Push follows each person's settings
 * (AnnouncementNotification::via).
 */
class DeliverAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $announcement = Announcement::query()->with(['author', 'school'])->find($this->announcementId);

        if (! $announcement || ! $announcement->isLive()) {
            return;
        }

        $loud = $announcement->priority->notifiesInBell() || $announcement->requires_confirmation;

        DB::table('announcement_recipients')
            ->where('announcement_id', $announcement->id)
            ->whereNull('notified_at')
            // chunkById: rows leave the whereNull filter as they are handled.
            ->chunkById(500, function ($rows) use ($announcement, $loud) {
                $users = User::query()->with('pushSubscriptions')->whereKey($rows->pluck('user_id'))->get()->keyBy('id');

                foreach ($rows as $row) {
                    $user = $users->get($row->user_id);
                    if (! $user) {
                        continue;
                    }
                    $mentioned = $row->reason === 'mention';
                    // The bell only for what deserves interruption; push follows each person's settings.
                    try {
                        $user->notify(new AnnouncementNotification($announcement, mentioned: $mentioned, bell: $mentioned || $loud));
                    } catch (\Throwable $e) {
                        // One unreachable push service must not stop (or repeat) delivery to the others.
                        report($e);
                    }
                }

                DB::table('announcement_recipients')
                    ->where('announcement_id', $announcement->id)
                    ->whereIn('user_id', $rows->pluck('user_id'))
                    ->update(['notified_at' => now()]);
            }, 'user_id');
    }
}
