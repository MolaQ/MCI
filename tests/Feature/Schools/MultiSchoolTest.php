<?php

use App\Enums\ProfileVisibility;
use App\Enums\Role;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Groups\Index as GroupsIndex;
use App\Livewire\Site\SchoolEdit;
use App\Models\AuditLog as AuditLogModel;
use App\Models\Group;
use App\Models\School;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Livewire\Livewire;

beforeEach(function () {
    $this->other = School::factory()->create(['short_name' => 'ZS2', 'slug' => 'zs2', 'name' => 'Zespół Szkół nr 2']);
});

it('gives one person different roles in different schools', function () {
    $user = userWithRole(Role::Coordinator);
    $user->schools()->attach($this->other->id, ['status' => 'active']);
    actAsSchool($this->other, $user);
    $user->assignRole(Role::Teacher->value);

    actAsSchool($this->school, $user);
    expect($user->hasRole('coordinator'))->toBeTrue()->and($user->can('groups.manage'))->toBeTrue();

    actAsSchool($this->other, $user);
    expect($user->hasRole('coordinator'))->toBeFalse()->and($user->can('groups.manage'))->toBeFalse()
        ->and($user->hasRole('teacher'))->toBeTrue();
});

it('scopes individual permissions such as alerts.send to one school', function () {
    $user = userWithRole(Role::Teacher);
    $user->givePermissionTo('alerts.send');
    $user->schools()->attach($this->other->id, ['status' => 'active']);

    actAsSchool($this->other, $user);
    expect($user->can('alerts.send'))->toBeFalse();

    actAsSchool($this->school, $user);
    expect($user->can('alerts.send'))->toBeTrue();
});

it('switches the active school only to schools the user belongs to', function () {
    $user = userWithRole(Role::Teacher);
    $user->schools()->attach($this->other->id, ['status' => 'active']);
    $foreign = School::factory()->create();

    $this->actingAs($user)->post(route('school.switch'), ['school_id' => $this->other->id])->assertRedirect(route('dashboard'));
    expect(session(CurrentSchool::SESSION_KEY))->toBe($this->other->id)
        ->and($user->fresh()->last_school_id)->toBe($this->other->id);

    $this->actingAs($user)->post(route('school.switch'), ['school_id' => $foreign->id])->assertForbidden();
});

it('never lets a suspended membership become the active school', function () {
    $user = userWithRole(Role::Teacher);
    $user->schools()->attach($this->other->id, ['status' => 'suspended']);

    $this->actingAs($user)->post(route('school.switch'), ['school_id' => $this->other->id])->assertForbidden();
});

it('hides groups of other schools, even by direct URL', function () {
    $user = userWithRole(Role::Coordinator);
    Group::factory()->create(['name' => 'Nasza grupa']);
    $foreign = Group::factory()->create(['school_id' => $this->other->id, 'name' => 'Cudza szkoła']);

    $this->actingAs($user);
    Livewire::test(GroupsIndex::class)->assertSee('Nasza grupa')->assertDontSee('Cudza szkoła');

    $this->get(route('groups.show', $foreign))->assertForbidden();
});

it('keeps the school log and the site journal apart', function () {
    Audit::log('group.created');
    Audit::site('school.created');
    actAsSchool($this->other);
    Audit::log('group.updated');
    actAsSchool($this->school);

    $this->actingAs(admin());
    Livewire::test(AuditLog::class)
        ->assertSee(__('audit.group.created'))
        ->assertDontSee(__('audit.school.created'))
        ->assertDontSee(__('audit.group.updated'));

    $this->get(route('site.journal'))->assertForbidden();

    $this->actingAs(siteAdmin());
    Livewire::test(AuditLog::class, ['scope' => 'site'])
        ->assertSee(__('audit.school.created'))
        ->assertDontSee(__('audit.group.created'));
});

it('filters logs with pills and exports CSV', function () {
    Audit::log('group.created');
    Audit::log('user.created');
    $this->actingAs(admin());

    Livewire::test(AuditLog::class)
        ->call('setCategory', 'group')
        ->assertSee(__('audit.group.created'))
        ->assertDontSee(__('audit.user.created'))
        ->call('export')
        ->assertFileDownloaded('logi-szkoly-'.now()->format('Y-m-d').'.csv');
});

it('lets site admins create a school with starter groups', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(SchoolEdit::class)
        ->set('name', 'Technikum Nowe')
        ->set('short_name', 'TN')
        ->set('slug', 'tn')
        ->set('email_domains', 'tn.edu.pl, Uczen.TN.edu.pl')
        ->call('save')
        ->assertHasNoErrors();

    $school = School::query()->where('slug', 'tn')->sole();
    expect($school->email_domains)->toBe(['tn.edu.pl', 'uczen.tn.edu.pl'])
        ->and($school->groups()->where('slug', 'wszyscy-pracownicy')->exists())->toBeTrue()
        ->and(AuditLogModel::query()->whereNull('school_id')->where('action', 'school.created')->exists())->toBeTrue();
});

it('lets a school admin change only the policy of their own school', function () {
    $this->actingAs(admin());

    Livewire::test(SchoolEdit::class)
        ->set('name', 'Przejęta nazwa')
        ->set('max_profile_visibility', 'school')
        ->call('save');

    $this->school->refresh();
    expect($this->school->name)->toBe('Zespół Szkół Mechanicznych')
        ->and($this->school->maxProfileVisibility())->toBe(ProfileVisibility::School);

    $this->get(route('site.schools.edit', $this->other))->assertForbidden();
});

it('caps profile visibility by the strictest school policy', function () {
    $user = member(['profile_visibility' => 'public']);
    expect($user->effectiveProfileVisibility())->toBe(ProfileVisibility::Network); // factory policy cap

    $user->schools()->attach($this->other->id, ['status' => 'active']);
    $this->other->update(['settings' => ['max_profile_visibility' => 'school']]);

    expect($user->fresh()->effectiveProfileVisibility())->toBe(ProfileVisibility::School);
});

it('shows profiles across schools only when the person and the policy allow it', function () {
    $person = member(['profile_visibility' => 'network']);
    $colleague = member();
    $stranger = member([], $this->other);

    expect($person->profileVisibleTo($colleague))->toBeTrue()
        ->and($person->profileVisibleTo($stranger))->toBeTrue()
        ->and($person->profileVisibleTo(null))->toBeFalse();

    $person->update(['profile_visibility' => 'school']);
    expect($person->fresh()->profileVisibleTo($stranger))->toBeFalse()
        ->and($person->fresh()->profileVisibleTo($colleague))->toBeTrue();
});
