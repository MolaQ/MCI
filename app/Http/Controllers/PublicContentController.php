<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Support\Features;
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
        abort_if(app(Features::class)->disabled(Feature::EventIcs, $e->school_id), 404);

        return $this->calendar([$e], Str::slug($e->title));
    }

    /**
     * Subscribable feed: upcoming public events of one listed school.
     */
    public function schoolIcs(string $slug): Response
    {
        $school = School::query()->publiclyListed()->where('slug', $slug)->firstOrFail(['id', 'name', 'slug', 'settings', 'updated_at']);
        abort_if(app(Features::class)->disabled(Feature::EventIcs, $school), 404);

        $events = Event::query()->publiclyVisible()->with('school:id,name')
            ->where('events.school_id', $school->id)->upcoming()
            ->orderBy('events.starts_at')->limit(200)->get();

        return $this->calendar($events, $school->slug.'-wydarzenia', $school->name);
    }

    /**
     * @param  iterable<Event>  $events
     */
    private function calendar(iterable $events, string $filename, ?string $calendarName = null): Response
    {
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $escape = fn (?string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $s);
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//MCI//PL', 'CALSCALE:GREGORIAN'];
        if ($calendarName) {
            $lines[] = 'X-WR-CALNAME:'.$escape($calendarName);
        }

        foreach ($events as $e) {
            $end = $e->ends_at ?? ($e->all_day ? $e->starts_at : $e->starts_at->copy()->addHour());
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:event-'.$e->id.'@'.$host,
                'DTSTAMP:'.$fmt(now()),
                $e->all_day ? 'DTSTART;VALUE=DATE:'.$e->starts_at->format('Ymd') : 'DTSTART:'.$fmt($e->starts_at),
                // All-day DTEND is exclusive: the day after the last day.
                $e->all_day ? 'DTEND;VALUE=DATE:'.$end->copy()->addDay()->format('Ymd') : 'DTEND:'.$fmt($end),
                'SUMMARY:'.$escape($e->title),
                'LOCATION:'.$escape(trim(($e->location ? $e->location.', ' : '').$e->school->name)),
                'DESCRIPTION:'.$escape($e->public_description),
                'URL:'.route('public.event', $e),
                'END:VEVENT',
            );
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.($filename ?: 'kalendarz').'.ics"',
        ]);
    }

    public function announcement(int $announcement): View
    {
        $model = Announcement::query()->publiclyVisible()->with('school:id,name,short_name,slug,color,logo_media_id')
            ->findOrFail($announcement, ['id', 'school_id', 'title', 'body', 'priority', 'published_at']);

        return view('public.announcement', ['announcement' => $model, 'body' => Mentions::forPublic($model->body)]);
    }
}
