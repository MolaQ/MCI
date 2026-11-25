<?php

namespace App\Domain\Communication;

use App\Enums\AudienceTargetType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A parsed audience: the union of targets written as compact keys, which
 * is also how the composer keeps them in Livewire state:
 *   "everyone", "role:teacher", "group:5", "group+:5" (with subgroups), "user:9".
 */
final class Audience
{
    /**
     * @param  list<array{type: AudienceTargetType, id: ?string, subgroups: bool}>  $targets
     */
    private function __construct(public readonly array $targets) {}

    /**
     * @param  iterable<string>  $keys
     */
    public static function fromKeys(iterable $keys): self
    {
        $targets = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $target = match (true) {
                $key === 'everyone' => ['type' => AudienceTargetType::Everyone, 'id' => null, 'subgroups' => false],
                (bool) preg_match('/^role:([a-z_]+)$/', $key, $m) && Role::tryFrom($m[1]) !== null => ['type' => AudienceTargetType::Role, 'id' => $m[1], 'subgroups' => false],
                (bool) preg_match('/^group(\+?):(\d+)$/', $key, $m) => ['type' => AudienceTargetType::Group, 'id' => $m[2], 'subgroups' => $m[1] === '+'],
                (bool) preg_match('/^user:(\d+)$/', $key, $m) => ['type' => AudienceTargetType::User, 'id' => $m[1], 'subgroups' => false],
                default => throw ValidationException::withMessages(['audience' => __('Nieprawidłowy odbiorca.')]),
            };
            $targets[$target['type']->value.':'.$target['id']] = $target;
        }

        return new self(array_values($targets));
    }

    public function isEmpty(): bool
    {
        return $this->targets === [];
    }

    /**
     * Throws if the author may not address any of the targets, or if a
     * group / person is outside the school.
     */
    public function authorize(User $author, School $school, AudienceGuard $guard): void
    {
        $denied = [];
        $groups = Group::query()->where('school_id', $school->id)->whereKey($this->ids(AudienceTargetType::Group))->get()->keyBy('id');

        foreach ($this->targets as $t) {
            $ok = match ($t['type']) {
                AudienceTargetType::Everyone, AudienceTargetType::Role => $guard->canTargetWholeSchool($author),
                AudienceTargetType::Group => ($g = $groups->get((int) $t['id'])) !== null
                    && $guard->canTargetGroup($author, $g)
                    && (! $t['subgroups'] || $guard->canIncludeSubgroups($author, $g)),
                AudienceTargetType::User => true,
            };
            if (! $ok) {
                $denied[] = $t['type'] === AudienceTargetType::Group ? ($groups->get((int) $t['id'])?->name ?? '#'.$t['id']) : $t['type']->value;
            }
        }

        $users = $this->ids(AudienceTargetType::User);
        if ($users && User::query()->inSchool($school)->whereKey($users)->count() !== count($users)) {
            $denied[] = __('osoby spoza szkoły');
        }

        if ($denied) {
            throw ValidationException::withMessages(['audience' => __('Nie możesz wysłać do: :list.', ['list' => implode(', ', $denied)])]);
        }
    }

    /**
     * Deduplicated union of active people (active account, active in the
     * school) reached by the targets — one SQL query.
     *
     * @return Collection<int, int> user ids
     */
    public function resolve(School $school): Collection
    {
        if ($this->isEmpty()) {
            return collect();
        }

        $everyone = collect($this->targets)->contains(fn ($t) => $t['type'] === AudienceTargetType::Everyone);
        $roles = $this->ids(AudienceTargetType::Role, false);
        $userIds = $this->ids(AudienceTargetType::User);
        $groupIds = $this->expandGroups($school);

        return User::query()->activeInSchool($school)->active()
            ->unless($everyone, fn (Builder $q) => $q->where(function (Builder $w) use ($roles, $userIds, $groupIds, $school) {
                $w->whereRaw('1 = 0')
                    ->when($userIds, fn ($q) => $q->orWhereIn('users.id', $userIds))
                    ->when($groupIds, fn ($q) => $q->orWhereIn('users.id', fn ($s) => $s->select('user_id')->from('group_user')->whereIn('group_id', $groupIds)))
                    ->when($roles, fn ($q) => $q->orWhereIn('users.id', fn ($s) => $s->select('model_has_roles.model_id')
                        ->from('model_has_roles')
                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                        ->where('model_has_roles.model_type', 'user')
                        ->where('model_has_roles.school_id', $school->id)
                        ->whereIn('roles.name', $roles)));
            }))
            ->pluck('users.id')
            ->unique()
            ->values();
    }

    /**
     * @return list<array{target_type: string, target_id: ?string, include_subgroups: bool}>
     */
    public function rows(): array
    {
        return array_map(fn ($t) => [
            'target_type' => $t['type']->value,
            'target_id' => $t['id'],
            'include_subgroups' => $t['subgroups'],
        ], $this->targets);
    }

    /**
     * @return list<int>|list<string>
     */
    private function ids(AudienceTargetType $type, bool $int = true): array
    {
        return array_values(array_map(fn ($t) => $int ? (int) $t['id'] : $t['id'],
            array_filter($this->targets, fn ($t) => $t['type'] === $type)));
    }

    /**
     * Group ids incl. descendants where subgroups were requested (scoped to
     * the school; loops are impossible thanks to the hierarchy validation,
     * but the visited set guards anyway).
     *
     * @return list<int>
     */
    private function expandGroups(School $school): array
    {
        $ids = [];
        $frontier = [];
        foreach ($this->targets as $t) {
            if ($t['type'] === AudienceTargetType::Group) {
                $ids[(int) $t['id']] = true;
                if ($t['subgroups']) {
                    $frontier[] = (int) $t['id'];
                }
            }
        }

        while ($frontier) {
            $children = Group::query()->where('school_id', $school->id)->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_filter($children, fn ($id) => ! isset($ids[$id])));
            foreach ($frontier as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }
}
