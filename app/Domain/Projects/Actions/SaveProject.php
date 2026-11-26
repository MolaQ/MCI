<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Media\Actions\StoreImage;
use App\Domain\Projects\ProjectGuard;
use App\Enums\Feature;
use App\Enums\ProjectKind;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Jobs\NotifyProject;
use App\Models\Group;
use App\Models\Media;
use App\Models\Project;
use App\Models\School;
use App\Models\User;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a project: details, partner schools, coordinator,
 * team, rich description with @mentions and the cover image. Newly added
 * people are told in the bell (and by push); everything is audited in the
 * log of each participating school.
 */
class SaveProject
{
    public const MAX_MEMBERS = 60;

    public function __construct(private ProjectGuard $guard, private Features $features, private StoreImage $storeImage) {}

    /**
     * @param  array<string, mixed>  $data  name, kind, status, visibility, starts_on, ends_on, coordinator_id, budget_notes, description
     * @param  list<string>  $memberKeys  user:1, group:2, role:teacher
     * @param  list<int>  $partnerIds
     */
    public function handle(User $actor, School $school, array $data, array $memberKeys = [], array $partnerIds = [], ?UploadedFile $cover = null, bool $removeCover = false, ?Project $project = null): Project
    {
        if (! $project && ! $this->guard->canCreate($actor, $school)) {
            throw new AuthorizationException(__('Nie możesz tworzyć projektów w tej szkole.'));
        }
        if ($project && $project->school_id !== $school->id) {
            throw new AuthorizationException(__('Projekt należy do innej szkoły.'));
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            throw ValidationException::withMessages(['name' => __('Podaj nazwę projektu (do 200 znaków).')]);
        }
        $kind = ProjectKind::tryFrom((string) ($data['kind'] ?? '')) ?? ProjectKind::Other;
        $status = ProjectStatus::tryFrom((string) ($data['status'] ?? '')) ?? ProjectStatus::Planned;
        $visibility = in_array($data['visibility'] ?? null, ['school', 'team', 'public'], true) ? $data['visibility'] : 'school';
        $startsOn = $this->date($data['starts_on'] ?? null, 'starts_on');
        $endsOn = $this->date($data['ends_on'] ?? null, 'ends_on');
        if ($startsOn && $endsOn && $endsOn->lt($startsOn)) {
            throw ValidationException::withMessages(['ends_on' => __('Koniec nie może być przed początkiem.')]);
        }

        // Partner schools: only with the cross-school switch, only active schools.
        $partnerIds = array_values(array_unique(array_map('intval', $partnerIds)));
        $partnerIds = array_values(array_diff($partnerIds, [$school->id]));
        if ($partnerIds) {
            if (! $this->guard->crossSchool($school)) {
                throw ValidationException::withMessages(['partners' => __('Projekty międzyszkolne są wyłączone.')]);
            }
            $partnerIds = School::query()->where('is_active', true)->whereKey($partnerIds)->pluck('id')->all();
        }
        $schoolIds = [$school->id, ...$partnerIds];

        $coordinatorId = (int) ($data['coordinator_id'] ?? 0) ?: $actor->id;
        $coordinator = User::query()->active()->find($coordinatorId);
        if (! $coordinator || ! collect($schoolIds)->contains(fn ($id) => $coordinator->belongsToSchool($id))) {
            throw ValidationException::withMessages(['coordinator_id' => __('Koordynator musi należeć do jednej ze szkół projektu.')]);
        }

        $members = $this->members($actor, $school, $schoolIds, $memberKeys);

        $description = RichText::sanitize((string) ($data['description'] ?? ''));
        $description = RichText::isBlank($description) ? null : $description;
        $mentions = ['html' => $description, 'users' => [], 'groups' => []];
        if ($description !== null) {
            $mentions = $this->features->disabled(Feature::Mentions, $school)
                ? ['html' => Mentions::strip($description), 'users' => [], 'groups' => []]
                : Mentions::normalize($description, $school);
        }

        if ($cover && $this->features->disabled(Feature::ProjectCovers, $school)) {
            throw ValidationException::withMessages(['cover' => __('Okładki projektów są wyłączone.')]);
        }

        $before = $project ? [
            'team' => $project->teamIds()->all(),
            'mentions' => $project->mentions()->where('mentionable_type', 'user')->pluck('mentionable_id')->all(),
            'status' => $project->status,
            'coordinator' => $project->coordinator_id,
            'members' => $project->members()->get()->map->key()->all(),
            'partners' => $project->partnerSchools()->pluck('schools.id')->all(),
        ] : ['team' => [], 'mentions' => [], 'status' => null, 'coordinator' => null, 'members' => [], 'partners' => []];

        $oldCover = $project?->cover;
        $creating = $project === null;

        $project = DB::transaction(function () use ($actor, $school, $project, $name, $kind, $status, $visibility, $startsOn, $endsOn, $coordinator, $data, $mentions, $members, $partnerIds) {
            $project ??= new Project(['school_id' => $school->id, 'created_by' => $actor->id]);
            $project->fill([
                'name' => $name,
                'kind' => $kind,
                'status' => $status,
                'visibility' => $visibility,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'coordinator_id' => $coordinator->id,
                'budget_notes' => trim((string) ($data['budget_notes'] ?? '')) ?: null,
                'description' => $mentions['html'],
            ]);
            if ($project->isDirty('status')) {
                $project->completed_at = $status === ProjectStatus::Completed ? now() : null;
            }
            $project->save();

            $project->partnerSchools()->sync($partnerIds);
            $project->members()->delete();
            foreach ($members as $row) {
                $project->members()->create($row);
            }
            Mentions::sync($project, $mentions['users'], $mentions['groups'], $school, $actor);

            return $project;
        });

        if ($cover) {
            $media = $this->storeImage->handle($cover, 'cover', $school->id, $actor, $project, false, 'cover');
            $project->update(['cover_media_id' => $media->id]);
        } elseif ($removeCover) {
            $project->update(['cover_media_id' => null]);
        }
        if (($cover || $removeCover) && $oldCover instanceof Media) {
            $oldCover->purge();
        }

        $project->unsetRelation('partnerSchools');
        $afterKeys = array_map(fn ($m) => $this->key($m), $members);
        ProjectGuard::audit($project, $creating ? 'project.created' : 'project.updated', array_filter([
            'status' => $before['status'] !== $status ? $status->value : null,
            'coordinator' => $before['coordinator'] !== $coordinator->id ? $coordinator->id : null,
            'members_added' => array_values(array_diff($afterKeys, $before['members'])) ?: null,
            'members_removed' => array_values(array_diff($before['members'], $afterKeys)) ?: null,
            'partners' => $partnerIds !== $before['partners'] ? ($partnerIds ?: []) : null,
            'cover' => $cover ? 'changed' : ($removeCover ? 'removed' : null),
        ], fn ($v) => $v !== null), $actor);

        // Bell + push: new coordinator, newly added team members, new mentions.
        if ($coordinator->id !== $actor->id && $before['coordinator'] !== $coordinator->id) {
            NotifyProject::dispatch($project->id, [$coordinator->id], 'coordinator', $actor->id);
        }
        $added = $project->teamIds()->diff($before['team'])->reject(fn ($id) => in_array($id, [$actor->id, $coordinator->id], true))->values()->all();
        if ($added) {
            NotifyProject::dispatch($project->id, $added, 'assigned', $actor->id);
        }
        $mentioned = array_values(array_diff($mentions['users'], $before['mentions'], [$actor->id], $added));
        if ($mentioned) {
            NotifyProject::dispatch($project->id, $mentioned, 'mention', $actor->id);
        }

        return $project;
    }

    private function date(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', (string) $value, config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => __('Nieprawidłowa data.')]);
        }
    }

    /**
     * @param  list<int>  $schoolIds
     * @param  list<string>  $keys
     * @return list<array{school_id: int, user_id: ?int, group_id: ?int, role: ?string}>
     */
    private function members(User $actor, School $school, array $schoolIds, array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        if (count($keys) > self::MAX_MEMBERS) {
            throw ValidationException::withMessages(['members' => __('Zespół może mieć najwyżej :count pozycji.', ['count' => self::MAX_MEMBERS])]);
        }

        $rows = [];
        $allowedGroups = null;
        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, '');
            $row = ['school_id' => $school->id, 'user_id' => null, 'group_id' => null, 'role' => null];

            if ($type === 'user' && ctype_digit($id)) {
                $user = User::query()->active()->find((int) $id);
                $home = $user ? collect($schoolIds)->first(fn ($sid) => $user->belongsToSchool($sid)) : null;
                if (! $home) {
                    throw ValidationException::withMessages(['members' => __('Do zespołu można dodać tylko osoby ze szkół projektu.')]);
                }
                $rows[] = ['school_id' => $home, 'user_id' => $user->id] + $row;
            } elseif ($type === 'group' && ctype_digit($id)) {
                $allowedGroups ??= $this->guard->groupOptions($actor, $schoolIds)->pluck('school_id', 'id');
                if (! $allowedGroups->has((int) $id)) {
                    throw ValidationException::withMessages(['members' => __('Nie możesz dodać tej grupy do projektu.')]);
                }
                $rows[] = ['school_id' => (int) $allowedGroups[(int) $id], 'group_id' => (int) $id] + $row;
            } elseif ($type === 'role' && Role::tryFrom($id) && Role::tryFrom($id) !== Role::Admin) {
                if (! $this->guard->manages($actor)) {
                    throw ValidationException::withMessages(['members' => __('Całe role może dodawać tylko osoba zarządzająca projektami.')]);
                }
                $rows[] = ['role' => $id] + $row;
            } else {
                throw ValidationException::withMessages(['members' => __('Nieprawidłowy członek zespołu.')]);
            }
        }

        return $rows;
    }

    /** @param  array{user_id: ?int, group_id: ?int, role: ?string}  $row */
    private function key(array $row): string
    {
        return match (true) {
            $row['user_id'] !== null => 'user:'.$row['user_id'],
            $row['group_id'] !== null => 'group:'.$row['group_id'],
            default => 'role:'.$row['role'],
        };
    }
}
