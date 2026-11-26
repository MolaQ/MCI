<?php

namespace App\Livewire\Site;

use App\Domain\Calendar\Agenda;
use App\Domain\Media\Actions\ReplaceImage;
use App\Enums\Permission;
use App\Enums\ProfileVisibility;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\School;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Database\Seeders\SystemGroupsSeeder;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Site admins create / edit any school. School users with settings.manage
 * edit the *policy* of their active school (domains, self-registration,
 * profile-visibility cap) via the same screen.
 */
class SchoolEdit extends Component
{
    use WithImageUploads;

    /** Site admins only: logo of the school being edited. */
    public $logoUpload = null;

    public ?School $school = null;

    public string $name = '';

    public string $short_name = '';

    public string $slug = '';

    public string $city = '';

    public string $email_domains = '';

    public bool $is_active = true;

    public bool $allow_self_registration = true;

    public string $max_profile_visibility = 'network';

    public string $calendar_default = 'personal';

    public function mount(?School $school = null): void
    {
        // Only site admins create schools; everyone else edits the active one.
        $this->school = $school?->exists ? $school
            : ($this->isSiteAdmin() && ! request()->routeIs('admin.school') ? null : app(CurrentSchool::class)->required());
        $this->authorizeAccess();

        if ($this->school) {
            $this->fill($this->school->only(['name', 'short_name', 'slug', 'is_active']));
            $this->city = (string) $this->school->city;
            $this->email_domains = implode(', ', $this->school->email_domains ?? []);
            $this->allow_self_registration = $this->school->allowsSelfRegistration();
            $this->max_profile_visibility = $this->school->maxProfileVisibility()->value;
            $this->calendar_default = in_array($this->school->setting('calendar_default'), Agenda::MODES, true) ? $this->school->setting('calendar_default') : (string) config('mci.calendar.default', 'personal');
        }
    }

    private function isSiteAdmin(): bool
    {
        return (bool) Auth::user()->is_site_admin;
    }

    private function authorizeAccess(): void
    {
        if ($this->isSiteAdmin()) {
            return;
        }

        abort_unless(
            $this->school
            && $this->school->id === app(CurrentSchool::class)->id()
            && Auth::user()->can(Permission::SettingsManage->value),
            403,
        );
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['required', 'string', 'max:20'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('schools', 'slug')->ignore($this->school?->id)],
            'city' => ['nullable', 'string', 'max:100'],
            'email_domains' => ['nullable', 'string', 'max:500', 'regex:/^\s*([a-z0-9.-]+\.[a-z]{2,})(\s*,\s*[a-z0-9.-]+\.[a-z]{2,})*\s*$/i'],
            'max_profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'calendar_default' => ['required', Rule::in(Agenda::MODES)],
            'logoUpload' => $this->imageUploadRule(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['email_domains' => __('domeny e-mail'), 'short_name' => __('skrót'), 'slug' => __('adres')];
    }

    public function updatedLogoUpload(): void
    {
        $this->validateOnly('logoUpload');
    }

    public function removeLogo(ReplaceImage $replace): void
    {
        abort_unless($this->isSiteAdmin() && $this->school, 403);
        $replace->handle($this->school, 'logo_media_id', null, 'logo', true, Auth::user(), 'logoUpload', remove: true);
        Flux::toast(text: __('Usunięto logo szkoły.'));
    }

    public function save()
    {
        $this->authorizeAccess();
        $this->slug = $this->slug ?: Str::slug($this->short_name);
        $data = $this->validate();

        $domains = array_values(array_filter(array_map(fn ($d) => mb_strtolower(trim($d)), explode(',', $this->email_domains))));
        $settings = array_merge($this->school?->settings ?? [], [
            'allow_self_registration' => $this->allow_self_registration,
            'max_profile_visibility' => $this->max_profile_visibility,
            'calendar_default' => $this->calendar_default,
        ]);

        if ($this->school && ! $this->isSiteAdmin()) {
            // School-level policy only.
            $this->school->update(['email_domains' => $domains ?: null, 'settings' => $settings]);
            Audit::log('school.policy_updated', $this->school, ['changed' => array_keys($this->school->getChanges())], schoolId: $this->school->id);
            Flux::toast(variant: 'success', text: __('Zapisano ustawienia szkoły.'));

            return null;
        }

        $attributes = [
            'name' => $data['name'], 'short_name' => $data['short_name'], 'slug' => $data['slug'],
            'city' => $data['city'] ?: null, 'email_domains' => $domains ?: null,
            'settings' => $settings, 'is_active' => $this->is_active,
        ];

        if ($this->school) {
            $this->school->update($attributes);
            Audit::site('school.updated', $this->school, ['changed' => array_keys($this->school->getChanges())]);
        } else {
            $this->school = School::query()->create($attributes);
            SystemGroupsSeeder::seedFor($this->school);
            Audit::site('school.created', $this->school, ['name' => $this->school->name]);
        }

        if ($this->logoUpload) {
            app(ReplaceImage::class)->handle($this->school, 'logo_media_id', $this->logoUpload, 'logo', true, Auth::user(), 'logoUpload');
            $this->logoUpload = null;
        }

        Flux::toast(variant: 'success', text: __('Zapisano szkołę.'));

        return Auth::user()->is_site_admin && ! request()->routeIs('admin.school')
            ? $this->redirectRoute('site.schools', navigate: true)
            : null;
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.site.school-edit', [
            'visibilities' => ProfileVisibility::cases(),
            'siteAdmin' => $this->isSiteAdmin(),
        ])->title($this->school ? $this->school->name : __('Nowa szkoła'));
    }
}
