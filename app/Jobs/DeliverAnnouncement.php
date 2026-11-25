<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Bell notifications for a published announcement. Idempotent: only
 * recipients with notified_at = null are handled, so the scheduler sweep
 * (mci:deliver-announcements) can safely run it again.
 *
 * In-app delivery is the feed itself (recipient rows); the bell is for
 * what deserves interruption: mentions, important/urgent posts and posts
 * that ask for confirmation. Web Push hooks in here in Step 4.
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
                $mentioned = $rows->where('reason', 'mention')->pluck('user_id');
                $others = $loud ? $rows->where('reason', '!=', 'mention')->pluck('user_id') : collect();

                if ($mentioned->isNotEmpty()) {
                    Notification::send(User::query()->whereKey($mentioned)->get(), new AnnouncementNotification($announcement, mentioned: true));
                }
                if ($others->isNotEmpty()) {
                    Notification::send(User::query()->whereKey($others)->get(), new AnnouncementNotification($announcement));
                }

                DB::table('announcement_recipients')
                    ->where('announcement_id', $announcement->id)
                    ->whereIn('user_id', $rows->pluck('user_id'))
                    ->update(['notified_at' => now()]);
            }, 'user_id');
    }
}
