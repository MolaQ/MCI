<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Group;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Suggestions for the "@" popup: people and groups of the active school.
 * Groups are limited to those the person may see (GroupPolicy::view), so
 * the list never reveals other teams' names.
 */
class MentionSearchController
{
    public function __invoke(Request $request, CurrentSchool $current): JsonResponse
    {
        $school = $current->get();
        if (! $school || app(Features::class)->disabled(Feature::Mentions, $school)) {
            return response()->json([]);
        }

        $term = mb_substr(trim((string) $request->query('q', '')), 0, 50);

        $users = User::query()->activeInSchool($school)->active()->search($term)
            ->whereKeyNot($request->user()->id)
            ->orderBy('last_name')->orderBy('first_name')->limit(6)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['id' => 'user:'.$u->id, 'label' => $u->name, 'kind' => 'user', 'hint' => '']);

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $groups = Group::query()->where('school_id', $school->id)
            ->when($term !== '', fn ($q) => $q->where('name', 'like', $like))
            ->orderBy('name')->limit(20)->get()
            ->filter(fn (Group $g) => Gate::allows('view', $g))
            ->take(6)
            ->map(fn (Group $g) => ['id' => 'group:'.$g->id, 'label' => $g->name, 'kind' => 'group', 'hint' => __('grupa')]);

        return response()->json($users->concat($groups)->values());
    }
}
