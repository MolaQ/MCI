<?php

namespace App\Domain\Workflows\Actions;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates, edits, orders, disables and deletes workflow templates.
 *  - $school = null: system-wide template (site admin), logged in Dziennik witryny;
 *  - a school: that school's own template (workflows.manage), logged in Logi szkoły.
 * Requests already submitted keep their own copy of the stages.
 */
class ManageTemplates
{
    public const ICONS = [
        'clipboard-document-check', 'document-check', 'document-text', 'pencil-square', 'banknotes', 'shopping-cart',
        'truck', 'map', 'map-pin', 'globe-europe-africa', 'calendar-days', 'academic-cap', 'building-library',
        'wrench-screwdriver', 'computer-desktop', 'users', 'user-group', 'trophy', 'heart', 'shield-check',
        'key', 'inbox-arrow-down', 'paper-airplane', 'scale',
    ];

    public const MAX_STAGES = 10;

    public const MAX_FIELDS = 20;

    public function authorize(User $actor, ?School $school): void
    {
        $features = app(Features::class);
        $ok = $school === null
            ? $actor->is_site_admin
            : $actor->can('workflows.manage') && $features->enabled(Feature::Workflows, $school);
        if (! $ok) {
            throw new AuthorizationException(__('Nie możesz zarządzać szablonami obiegów.'));
        }
    }

    /**
     * @param  array{name: string, description?: ?string, icon?: string, min_attachments?: int, is_active?: bool}  $data
     * @param  list<array{key?: ?string, label: string, type: string, required?: bool, options?: string|list<string>}>  $fields
     * @param  list<array{name: string, approver_type: string, approver_id: string|int|null, school_id?: int|string|null, sla_hours?: int|string|null, task_on_reach?: bool, followup_task?: ?string}>  $stages
     */
    public function save(User $actor, ?School $school, array $data, array $fields, array $stages, ?WorkflowTemplate $template = null): WorkflowTemplate
    {
        $this->authorize($actor, $school);
        if ($template && ($template->school_id !== $school?->id || Gate::forUser($actor)->denies('manage', $template))) {
            abort(404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = __('Podaj nazwę (do 120 znaków).');
        } elseif (WorkflowTemplate::query()->where('school_id', $school?->id)->where('name', $name)->when($template, fn ($q) => $q->whereKeyNot($template->id))->exists()) {
            $errors['name'] = __('Szablon o tej nazwie już istnieje.');
        }
        $icon = in_array($data['icon'] ?? '', self::ICONS, true) ? $data['icon'] : 'clipboard-document-check';
        $minAttachments = max(0, min((int) ($data['min_attachments'] ?? 0), (int) config('mci.media.max_post_images')));
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            $errors['description'] = __('Opis może mieć najwyżej 1000 znaków.');
        }

        $cleanFields = $this->fields($fields, $errors);
        $cleanStages = $this->stages($school, $stages, $errors);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($actor, $school, $template, $name, $description, $icon, $minAttachments, $cleanFields, $cleanStages, $data) {
            $creating = $template === null;
            $template ??= new WorkflowTemplate([
                'school_id' => $school?->id,
                'created_by' => $actor->id,
                'position' => (int) WorkflowTemplate::query()->where('school_id', $school?->id)->max('position') + 1,
            ]);
            $template->fill([
                'name' => $name,
                'description' => $description ?: null,
                'icon' => $icon,
                'fields' => $cleanFields,
                'min_attachments' => $minAttachments,
                'is_active' => (bool) ($data['is_active'] ?? $template->is_active ?? true),
            ])->save();

            $template->stages()->delete();
            foreach ($cleanStages as $i => $stage) {
                $template->stages()->create($stage + ['position' => $i]);
            }

            $this->audit($school, $creating ? 'workflow.template_created' : 'workflow.template_updated', $template, [
                'name' => $name, 'stages' => count($cleanStages), 'fields' => count($cleanFields),
            ], $actor);

            return $template;
        });
    }

    public function move(User $actor, WorkflowTemplate $template, int $direction): void
    {
        $this->guard($actor, $template);
        $siblings = WorkflowTemplate::query()->where('school_id', $template->school_id)->orderBy('position')->orderBy('id')->get()->values();
        $index = $siblings->search(fn ($t) => $t->is($template));
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! $siblings->has($target)) {
            return;
        }
        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
        foreach ($ordered as $i => $t) {
            $t->forceFill(['position' => $i])->save();
        }
        $this->audit($template->school, 'workflow.template_moved', $template, ['name' => $template->name], $actor);
    }

    public function setActive(User $actor, WorkflowTemplate $template, bool $active): void
    {
        $this->guard($actor, $template);
        $template->forceFill(['is_active' => $active])->save();
        $this->audit($template->school, $active ? 'workflow.template_enabled' : 'workflow.template_disabled', $template, ['name' => $template->name], $actor);
    }

    /** Requests keep their copy; the template row is soft-deleted so history still names it. */
    public function delete(User $actor, WorkflowTemplate $template): void
    {
        $this->guard($actor, $template);
        $template->delete();
        $this->audit($template->school, 'workflow.template_deleted', $template, ['name' => $template->name], $actor);
    }

    private function guard(User $actor, WorkflowTemplate $template): void
    {
        $this->authorize($actor, $template->school);
        if (Gate::forUser($actor)->denies('manage', $template)) {
            abort(404);
        }
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<string>}>
     */
    private function fields(array $fields, array &$errors): array
    {
        $out = [];
        $keys = [];
        $fields = array_values(array_filter($fields, fn ($f) => trim((string) ($f['label'] ?? '')) !== ''));
        if (count($fields) > self::MAX_FIELDS) {
            $errors['fields'] = __('Szablon może mieć najwyżej :count pól.', ['count' => self::MAX_FIELDS]);

            return [];
        }
        foreach ($fields as $i => $f) {
            $label = trim((string) $f['label']);
            $type = (string) ($f['type'] ?? 'text');
            if (mb_strlen($label) > 80 || ! in_array($type, WorkflowTemplate::FIELD_TYPES, true)) {
                $errors["fields.{$i}"] = __('Pole :n: nazwa do 80 znaków i typ z listy.', ['n' => $i + 1]);

                continue;
            }
            $key = preg_match('/^[a-z0-9_]{1,40}$/', (string) ($f['key'] ?? '')) ? $f['key'] : (Str::slug($label, '_') ?: 'pole');
            $key = substr($key, 0, 36);
            $base = $key;
            for ($n = 2; in_array($key, $keys, true); $n++) {
                $key = $base.'_'.$n;
            }
            $keys[] = $key;
            $field = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => (bool) ($f['required'] ?? false)];
            if ($type === 'select') {
                $options = is_array($f['options'] ?? null) ? $f['options'] : preg_split('/[\r\n,;]+/', (string) ($f['options'] ?? ''));
                $options = array_values(array_unique(array_filter(array_map(fn ($o) => mb_substr(trim((string) $o), 0, 80), $options), fn ($o) => $o !== '')));
                if (count($options) < 1 || count($options) > 20) {
                    $errors["fields.{$i}"] = __('Pole „:field”: podaj od 1 do 20 opcji, każdą w osobnej linii.', ['field' => $label]);
                }
                $field['options'] = $options;
            }
            $out[] = $field;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(?School $school, array $stages, array &$errors): array
    {
        $features = app(Features::class);
        $stages = array_values($stages);
        if ($stages === []) {
            $errors['stages'] = __('Dodaj co najmniej jeden etap.');

            return [];
        }
        if (count($stages) > self::MAX_STAGES) {
            $errors['stages'] = __('Szablon może mieć najwyżej :count etapów.', ['count' => self::MAX_STAGES]);

            return [];
        }

        $out = [];
        foreach ($stages as $i => $s) {
            $n = $i + 1;
            $name = trim((string) ($s['name'] ?? ''));
            $type = (string) ($s['approver_type'] ?? '');
            $id = trim((string) ($s['approver_id'] ?? ''));
            $schoolId = filled($s['school_id'] ?? null) ? (int) $s['school_id'] : null;
            if ($schoolId === $school?->id) {
                $schoolId = null;
            }
            $sla = filled($s['sla_hours'] ?? null) ? (int) $s['sla_hours'] : null;
            $followup = trim((string) ($s['followup_task'] ?? ''));

            $error = match (true) {
                $name === '' || mb_strlen($name) > 120 => __('Etap :n: podaj nazwę (do 120 znaków).', ['n' => $n]),
                ! in_array($type, ['role', 'group', 'user'], true) || $id === '' => __('Etap :n: wybierz, kto decyduje.', ['n' => $n]),
                $sla !== null && ($sla < 1 || $sla > 2160) => __('Etap :n: termin decyzji od 1 do 2160 godzin.', ['n' => $n]),
                mb_strlen($followup) > 200 => __('Etap :n: zadanie po zatwierdzeniu do 200 znaków.', ['n' => $n]),
                default => null,
            };

            $target = $schoolId ? School::query()->where('is_active', true)->find($schoolId) : $school;
            if (! $error && $schoolId !== null) {
                if (! $target) {
                    $error = __('Etap :n: wybrana szkoła nie istnieje.', ['n' => $n]);
                } elseif ($features->disabled(Feature::WorkflowCrossSchool, $school)) {
                    $error = __('Etap :n: obiegi międzyszkolne są wyłączone.', ['n' => $n]);
                }
            }
            if (! $error) {
                $error = match ($type) {
                    'role' => Role::tryFrom($id) ? null : __('Etap :n: nieznana rola.', ['n' => $n]),
                    'group' => ! $target ? __('Etap :n: wybierz szkołę, z której jest grupa.', ['n' => $n])
                        : (Group::query()->where('school_id', $target->id)->whereKey((int) $id)->exists() ? null : __('Etap :n: grupa spoza wybranej szkoły.', ['n' => $n])),
                    'user' => ! $target ? __('Etap :n: wybierz szkołę, z której jest osoba.', ['n' => $n])
                        : (User::query()->activeInSchool($target)->whereKey((int) $id)->exists() ? null : __('Etap :n: osoba spoza wybranej szkoły.', ['n' => $n])),
                };
            }
            if ($error) {
                $errors["stages.{$i}"] = $error;

                continue;
            }

            $out[] = [
                'name' => $name, 'approver_type' => $type, 'approver_id' => $type === 'role' ? $id : (string) (int) $id,
                'school_id' => $schoolId, 'sla_hours' => $sla, 'task_on_reach' => (bool) ($s['task_on_reach'] ?? false),
                'followup_task' => $followup ?: null,
            ];
        }

        return $out;
    }

    private function audit(?School $school, string $action, WorkflowTemplate $template, array $properties, User $actor): void
    {
        $school
            ? Audit::log($action, $template, $properties, $actor, $school->id)
            : Audit::site($action, $template, $properties, $actor);
    }
}
