<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use App\Support\Mentions;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public, read-only pages for one event / announcement, plus an iCal file.
 * Anything not public (or from an unlisted school) is a plain 404.
 */
class PublicContentController
{
    public function event(int $event): View
    {
        $model = Event::query()->publiclyVisible()->with('school:id,name,short_name,slug,color,logo_media_id,address,city')->findOrFail($event);

        return view('public.event', ['event' => $model]);
    }

    public function ics(int $event): Response
    {
        $e = Event::query()->publiclyVisible()->with('school:id,name')->findOrFail($event);

        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $escape = fn (?string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $s);
        $end = $e->ends_at ?? ($e->all_day ? $e->starts_at : $e->starts_at->copy()->addHour());

        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//MCI//PL', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
            'UID:event-'.$e->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()),
            $e->all_day ? 'DTSTART;VALUE=DATE:'.$e->starts_at->format('Ymd') : 'DTSTART:'.$fmt($e->starts_at),
            // All-day DTEND is exclusive: the day after the last day.
            $e->all_day ? 'DTEND;VALUE=DATE:'.$end->copy()->addDay()->format('Ymd') : 'DTEND:'.$fmt($end),
            'SUMMARY:'.$escape($e->title),
            'LOCATION:'.$escape(trim(($e->location ? $e->location.', ' : '').$e->school->name)),
            'DESCRIPTION:'.$escape($e->public_description),
            'URL:'.route('public.event', $e),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($e->title).'.ics"',
        ]);
    }

    public function announcement(int $announcement): View
    {
        $model = Announcement::query()->publiclyVisible()->with('school:id,name,short_name,slug,color,logo_media_id')
            ->findOrFail($announcement, ['id', 'school_id', 'title', 'body', 'priority', 'published_at']);

        return view('public.announcement', ['announcement' => $model, 'body' => Mentions::forPublic($model->body)]);
    }
}
