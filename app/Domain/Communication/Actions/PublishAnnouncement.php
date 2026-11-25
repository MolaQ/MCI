<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Audience;
use App\Domain\Communication\AudienceGuard;
use App\Domain\Media\Actions\StoreImage;
use App\Enums\AnnouncementPriority;
use App\Enums\Permission;
use App\Jobs\DeliverAnnouncement;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Support\Audit;
use App\Support\Mentions;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Publishes (or schedules) an announcement to the union of the selected
 * targets plus the people @mentioned in the text.
 *
 * Every rule is enforced here, not in the form:
 *  - the author may address each target (AudienceGuard);
 *  - the body is sanitized and its mentions are re-resolved in the school;
 *  - a mentioned group is notified only if the author could target it;
 *  - "public" needs events.publish_public.
 */
class PublishAnnouncement
{
    public function __construct(private AudienceGuard $guard, private StoreImage $storeImage) {}

    /**
     * @param  array{title: string, body: string, priority?: string, requires_confirmation?: bool,
     *               is_public?: bool, publish_at?: mixed, expires_at?: mixed, pinned?: bool}  $data
     * @param  iterable<string>  $audienceKeys
     * @param  list<UploadedFile>  $images  shown under the text, in this order
     */
    public function handle(User $author, School $school, array $data, iterable $audienceKeys, array $images = []): Announcement
    {
        if (! $this->guard->canPublish($author)) {
            throw new AuthorizationException(__('Nie masz uprawnień do wysyłania komunikatów.'));
        }

        $isPublic = (bool) ($data['is_public'] ?? false);
        if ($isPublic && ! $author->can(Permission::EventsPublishPublic->value)) {
            throw new AuthorizationException(__('Nie możesz publikować treści publicznych.'));
        }

        $audience = Audience::fromKeys($audienceKeys);
        $audience->authorize($author, $school, $this->guard);

        $body = RichText::sanitize($data['body']);
        if (RichText::isBlank($body)) {
            throw ValidationException::withMessages(['body' => __('Treść komunikatu jest wymagana.')]);
        }
        $mentions = Mentions::normalize($body, $school);

        // Mentioned groups notify their members only if the author may address them.
        $notifiableGroups = Group::query()->whereKey($mentions['groups'])->get()
            ->filter(fn (Group $g) => $this->guard->canTargetGroup($author, $g))
            ->map(fn (Group $g) => 'group:'.$g->id);

        $audienceIds = $audience->resolve($school);
        $mentionIds = Audience::fromKeys([
            ...array_map(fn ($id) => 'user:'.$id, $mentions['users']),
            ...$notifiableGroups,
        ])->resolve($school);

        $recipients = $audienceIds->merge($mentionIds)->unique()->reject(fn ($id) => $id === $author->id)->values();

        if ($recipients->isEmpty() && ! $isPublic) {
            throw ValidationException::withMessages(['audience' => __('Wybierz co najmniej jednego odbiorcę.')]);
        }

        $publishAt = isset($data['publish_at']) && $data['publish_at'] ? CarbonImmutable::parse($data['publish_at']) : now();
        $expiresAt = isset($data['expires_at']) && $data['expires_at'] ? CarbonImmutable::parse($data['expires_at']) : null;
        if ($expiresAt && $expiresAt->lte($publishAt)) {
            throw ValidationException::withMessages(['expires_at' => __('Data wygaśnięcia musi być późniejsza niż publikacja.')]);
        }

        if (count($images) > config('mci.media.max_post_images')) {
            throw ValidationException::withMessages(['images' => __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')])]);
        }

        $stored = [];
        try {
            $announcement = DB::transaction(function () use ($author, $school, $data, $mentions, $audience, $recipients, $mentionIds, $isPublic, $publishAt, $expiresAt, $images, &$stored) {
                $announcement = Announcement::query()->create([
                    'school_id' => $school->id,
                    'author_id' => $author->id,
                    'title' => trim($data['title']),
                    'body' => $mentions['html'],
                    'priority' => AnnouncementPriority::tryFrom($data['priority'] ?? 'normal') ?? AnnouncementPriority::Normal,
                    'requires_confirmation' => (bool) ($data['requires_confirmation'] ?? false),
                    'is_public' => $isPublic,
                    'published_at' => $publishAt,
                    'expires_at' => $expiresAt,
                    'pinned_until' => ($data['pinned'] ?? false) ? ($expiresAt ?? $publishAt->addDays(7)) : null,
                    'recipients_count' => $recipients->count(),
                ]);

                $announcement->audience()->createMany($audience->rows());

                foreach (array_values($images) as $i => $file) {
                    $media = $this->storeImage->handle($file, 'image', $school->id, $author, $announcement, $isPublic, 'images.'.$i);
                    $media->update(['position' => $i]);
                    $stored[] = $media->path;
                }
                Mentions::sync($announcement, $mentions['users'], $mentions['groups'], $school, $author);

                $mentioned = $mentionIds->flip();
                foreach ($recipients->chunk(1000) as $chunk) {
                    DB::table('announcement_recipients')->insert($chunk->map(fn ($id) => [
                        'announcement_id' => $announcement->id,
                        'user_id' => $id,
                        'reason' => $mentioned->has($id) ? 'mention' : 'audience',
                    ])->all());
                }

                Audit::log('announcement.published', $announcement, [
                    'title' => $announcement->title,
                    'recipients' => $recipients->count(),
                    'targets' => $audience->rows(),
                    'public' => $isPublic,
                    'scheduled' => $publishAt->isFuture() ? $publishAt->toIso8601String() : null,
                    'images' => count($stored),
                ], $author, $school->id);

                return $announcement;
            });
        } catch (\Throwable $e) {
            // The rows rolled back with the transaction; remove the files too.
            Storage::disk('media')->delete($stored);

            throw $e;
        }

        if (! $publishAt->isFuture()) {
            DeliverAnnouncement::dispatch($announcement->id)->afterCommit();
        }

        return $announcement;
    }
}
