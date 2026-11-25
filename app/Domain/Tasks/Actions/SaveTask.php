<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Media\Actions\StoreImage;
use App\Domain\Tasks\TaskGuard;
use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Jobs\NotifyTask;
use App\Models\Group;
use App\Models\Media;
use App\Models\School;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a task: assignees, checklist, images and mentions in
 * one transaction, with an audit entry; newly assigned and newly mentioned
 * people are notified after commit (bell + Web Push).
 *
 * Every rule is enforced here, not in the form: TaskGuard for assignments,
 * features for teams/attachments/mentions, sanitized rich text.
 */
class SaveTask
{
    public function __construct(private TaskGuard $guard, private StoreImage $storeImage) {}

    /**
     * @param  array{title: string, description?: ?string, priority?: int|string, due_date?: ?string, due_time?: ?string,
     *               remind_before_minutes?: ?int}  $data
     * @param  list<string>  $assigneeKeys  user:1, group:2, role:teacher
     * @param  list<array{id?: ?int, body: string, is_done?: bool}>  $checklist
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     */
    public function handle(User $actor, School $school, array $data, array $assigneeKeys = [], array $checklist = [], array $newImages = [], array $removeImageIds = [], ?Task $task = null): Task
    {
        $features = app(Features::class);
        if ($features->disabled(Feature::Tasks, $school)) {
            throw new AuthorizationException(__('Zadania są wyłączone w tej szkole.'));
        }
        if ($task) {
            if ($task->school_id !== $school->id || Gate::forUser($actor)->denies('update', $task)) {
                throw new AuthorizationException(__('Nie możesz edytować tego zadania.'));
            }
        } elseif (! $actor->belongsToSchool($school)) {
            throw new AuthorizationException(__('Nie należysz do tej szkoły.'));
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['title' => __('Podaj tytuł (do 200 znaków).')]);
        }
        $priority = TaskPriority::tryFrom((int) ($data['priority'] ?? TaskPriority::Normal->value)) ?? TaskPriority::Normal;
        [$dueAt, $allDay] = $this->due($data['due_date'] ?? null, $data['due_time'] ?? null);
        $remind = isset($data['remind_before_minutes']) && $data['remind_before_minutes'] !== '' && $data['remind_before_minutes'] !== null
            ? max(0, min(10080, (int) $data['remind_before_minutes'])) : null;

        $assignees = $this->assignees($actor, $school, $assigneeKeys);
        $isPersonal = $assignees === [];

        $description = RichText::sanitize((string) ($data['description'] ?? ''));
        $description = RichText::isBlank($description) ? null : $description;
        $mentions = ['html' => $description, 'users' => [], 'groups' => []];
        if ($description !== null) {
            $mentions = $isPersonal || $features->disabled(Feature::Mentions, $school)
                ? ['html' => Mentions::strip($description), 'users' => [], 'groups' => []]
                : Mentions::normalize($description, $school);
        }

        $checklist = $this->checklist($checklist);

        if ($newImages && $features->disabled(Feature::TaskAttachments, $school)) {
            throw ValidationException::withMessages(['images' => __('Obrazy w zadaniach są wyłączone.')]);
        }
        $keptImages = $task ? $task->images()->whereNotIn('id', $removeImageIds)->count() : 0;
        if ($keptImages + count($newImages) > config('mci.media.max_post_images')) {
            throw ValidationException::withMessages(['images' => __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')])]);
        }

        $before = $task ? [
            'assignees' => $task->assignees()->get()->map->key()->all(),
            'mentions' => $task->mentions()->where('mentionable_type', 'user')->pluck('mentionable_id')->all(),
            'due_at' => $task->due_at?->toIso8601String(),
            'priority' => $task->priority->value,
            'title' => $task->title,
        ] : ['assignees' => [], 'mentions' => [], 'due_at' => null, 'priority' => null, 'title' => null];

        $stored = [];
        $removed = [];
        try {
            $task = DB::transaction(function () use ($actor, $school, $task, $title, $mentions, $priority, $dueAt, $allDay, $remind, $isPersonal, $assignees, $checklist, $newImages, $removeImageIds, $before, &$stored, &$removed) {
                $creating = $task === null;
                $task ??= new Task(['school_id' => $school->id, 'created_by' => $actor->id, 'status' => TaskStatus::Todo]);
                $dueChanged = $task->due_at?->toIso8601String() !== $dueAt?->toIso8601String();
                $task->fill([
                    'title' => $title,
                    'description' => $mentions['html'],
                    'priority' => $priority,
                    'due_at' => $dueAt,
                    'all_day' => $allDay,
                    'remind_before_minutes' => $remind,
                    'is_personal' => $isPersonal,
                ])->save();

                if ($dueChanged && ! $creating) {
                    // A new deadline earns new reminders.
                    DB::table('task_reminders')->where('task_id', $task->id)->delete();
                }

                $this->syncAssignees($task, $assignees, $actor);
                $this->syncChecklist($task, $checklist, $actor);

                foreach ($task->images()->whereIn('id', $removeImageIds)->get() as $media) {
                    /** @var Media $media */
                    $removed[] = $media->path;
                    $media->delete();
                }
                $position = (int) $task->images()->max('position');
                foreach (array_values($newImages) as $i => $file) {
                    $media = $this->storeImage->handle($file, 'image', $school->id, $actor, $task, false, 'images.'.$i);
                    $media->update(['position' => $position + $i + 1]);
                    $stored[] = $media->path;
                }

                Mentions::sync($task, $mentions['users'], $mentions['groups'], $school, $actor);

                $after = array_column($assignees, 'key');
                Audit::log($creating ? 'task.created' : 'task.updated', $task, array_filter([
                    'title' => $task->title,
                    'personal' => $isPersonal ?: null,
                    'assignees_added' => array_values(array_diff($after, $before['assignees'])) ?: null,
                    'assignees_removed' => array_values(array_diff($before['assignees'], $after)) ?: null,
                    'due_at' => $dueChanged ? $task->due_at?->toIso8601String() : null,
                    'priority' => $before['priority'] !== $priority->value ? $priority->value : null,
                    'images' => $stored ? count($stored) : null,
                ], fn ($v) => $v !== null), $actor, $school->id);

                return $task;
            });
        } catch (\Throwable $e) {
            Storage::disk('media')->delete($stored);

            throw $e;
        }
        Storage::disk('media')->delete($removed);

        // Notify only who is new: newly assigned people, then newly mentioned ones.
        $added = array_values(array_diff(array_column($assignees, 'key'), $before['assignees']));
        $assignedIds = $added ? $task->recipientIds($added) : collect();
        $assignedIds = $assignedIds->reject(fn ($id) => $id === $actor->id)->values();
        if ($assignedIds->isNotEmpty()) {
            NotifyTask::dispatch($task->id, $assignedIds->all(), 'assigned', $actor->id)->afterCommit();
        }
        $mentioned = collect($mentions['users'])->diff($before['mentions'])->diff($assignedIds)->reject(fn ($id) => $id === $actor->id)->values();
        if ($mentioned->isNotEmpty()) {
            NotifyTask::dispatch($task->id, $mentioned->all(), 'mention', $actor->id)->afterCommit();
        }

        return $task->refresh();
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: bool}
     */
    private function due(?string $date, ?string $time): array
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '') {
            if ($time !== '') {
                throw ValidationException::withMessages(['due_date' => __('Podaj dzień terminu.')]);
            }

            return [null, false];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ($time !== '' && ! preg_match('/^\d{2}:\d{2}$/', $time))) {
            throw ValidationException::withMessages(['due_date' => __('Nieprawidłowy termin.')]);
        }

        try {
            // Times are entered and stored in the application time zone (Europe/Warsaw).
            $at = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.($time !== '' ? $time.':00' : '23:59:00'), config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_date' => __('Nieprawidłowy termin.')]);
        }
        if (! $at || $at->year < 2000 || $at->year > 2100) {
            throw ValidationException::withMessages(['due_date' => __('Nieprawidłowy termin.')]);
        }

        return [$at, $time === ''];
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, user_id: ?int, group_id: ?int, role: ?string}>
     */
    private function assignees(User $actor, School $school, array $keys): array
    {
        $out = [];
        $denied = [];
        foreach (array_unique(array_map('strval', $keys)) as $key) {
            if ($key === 'user:'.$actor->id) {
                continue; // "for me" is the personal list
            }
            if (preg_match('/^user:(\d+)$/', $key, $m)) {
                $user = User::query()->activeInSchool($school)->active()->find((int) $m[1]);
                $user && $this->guard->canAssignUser($actor, $user, $school)
                    ? $out[] = ['key' => $key, 'user_id' => $user->id, 'group_id' => null, 'role' => null]
                    : $denied[] = $user?->name ?? __('osoba spoza szkoły');
            } elseif (preg_match('/^group:(\d+)$/', $key, $m)) {
                $group = Group::query()->where('school_id', $school->id)->find((int) $m[1]);
                $group && $this->guard->canAssignGroup($actor, $group, $school)
                    ? $out[] = ['key' => $key, 'user_id' => null, 'group_id' => $group->id, 'role' => null]
                    : $denied[] = $group?->name ?? __('grupa spoza szkoły');
            } elseif (preg_match('/^role:([a-z_]+)$/', $key, $m) && ($role = Role::tryFrom($m[1])) && $role !== Role::Admin) {
                $this->guard->canAssignRole($actor, $school)
                    ? $out[] = ['key' => $key, 'user_id' => null, 'group_id' => null, 'role' => $role->value]
                    : $denied[] = $role->label();
            } else {
                $denied[] = $key;
            }
        }

        if ($denied) {
            throw ValidationException::withMessages(['assignees' => __('Nie możesz przydzielić zadania: :list.', ['list' => implode(', ', $denied)])]);
        }
        if (count($out) > 50) {
            throw ValidationException::withMessages(['assignees' => __('Wybierz najwyżej 50 osób, grup lub ról.')]);
        }

        return $out;
    }

    /**
     * @param  list<array{id?: ?int, body: string, is_done?: bool}>  $items
     * @return list<array{id: ?int, body: string, is_done: bool}>
     */
    private function checklist(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            if (mb_strlen($body) > 200) {
                throw ValidationException::withMessages(['checklist' => __('Punkt listy może mieć najwyżej 200 znaków.')]);
            }
            $clean[] = ['id' => isset($item['id']) ? (int) $item['id'] : null, 'body' => $body, 'is_done' => (bool) ($item['is_done'] ?? false)];
        }
        if (count($clean) > config('mci.tasks.max_checklist_items')) {
            throw ValidationException::withMessages(['checklist' => __('Lista może mieć najwyżej :count punktów.', ['count' => config('mci.tasks.max_checklist_items')])]);
        }

        return $clean;
    }

    /**
     * @param  list<array{key: string, user_id: ?int, group_id: ?int, role: ?string}>  $assignees
     */
    private function syncAssignees(Task $task, array $assignees, User $actor): void
    {
        $existing = $task->assignees()->get()->keyBy(fn (TaskAssignee $a) => $a->key());
        $keys = array_column($assignees, 'key');

        $existing->except($keys)->each->delete();
        foreach ($assignees as $a) {
            if (! $existing->has($a['key'])) {
                $task->assignees()->create([
                    'user_id' => $a['user_id'], 'group_id' => $a['group_id'], 'role' => $a['role'],
                    'assigned_by' => $actor->id, 'assigned_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  list<array{id: ?int, body: string, is_done: bool}>  $items
     */
    private function syncChecklist(Task $task, array $items, User $actor): void
    {
        $existing = $task->checklist()->get()->keyBy('id');
        $keep = [];
        foreach ($items as $position => $item) {
            $row = $item['id'] ? $existing->get($item['id']) : null;
            $done = ['is_done' => $item['is_done']];
            if ($row && $row->is_done !== $item['is_done']) {
                $done += ['done_by' => $item['is_done'] ? $actor->id : null, 'done_at' => $item['is_done'] ? now() : null];
            } elseif (! $row && $item['is_done']) {
                $done += ['done_by' => $actor->id, 'done_at' => now()];
            }
            $row ??= $task->checklist()->make();
            $row->fill(['body' => $item['body'], 'position' => $position] + $done)->save();
            $keep[] = $row->id;
        }
        $task->checklist()->whereNotIn('id', $keep)->delete();
    }
}
