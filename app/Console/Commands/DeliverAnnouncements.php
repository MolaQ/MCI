<?php

namespace App\Console\Commands;

use App\Jobs\DeliverAnnouncement;
use App\Models\Announcement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled every minute: delivers announcements whose publication time has
 * come (scheduled posts) and retries anything left undelivered.
 */
#[Signature('mci:deliver-announcements')]
#[Description('Deliver scheduled or pending announcements')]
class DeliverAnnouncements extends Command
{
    public function handle(): int
    {
        Announcement::query()->live()
            ->where('published_at', '>=', now()->subDays(2))
            ->whereExists(fn ($q) => $q->from('announcement_recipients')
                ->whereColumn('announcement_recipients.announcement_id', 'announcements.id')
                ->whereNull('announcement_recipients.notified_at'))
            ->pluck('id')
            ->each(fn ($id) => DeliverAnnouncement::dispatch($id));

        return self::SUCCESS;
    }
}
