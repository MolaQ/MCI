<?php

use App\Domain\Users\Actions\ImportUsersFromCsv;
use App\Domain\Users\Actions\SendAccountSetupLink;
use App\Enums\GroupRole;
use App\Enums\Role;
use App\Livewire\Admin\Appearance;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Users\Edit;
use App\Livewire\Admin\Users\Index;
use App\Livewire\Groups\Edit as GroupEdit;
use App\Livewire\Groups\Show;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\SystemGroupsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function hardeningCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'mci').'.csv';
    file_put_contents($path, $content);

    return $path;
}

function usersManager(): User
{
    $manager = userWithRole(Role::Teacher);
    $manager->givePermissionTo('users.manage');

    return $manager;
}

it('does not demote a co-leader when they are "added" again', function () {
    $group = Group::factory()->create();
    $leader = userWithRole(Role::Teacher);
    $coLeader = userWithRole(Role::Teacher);
    $group->members()->attach($leader->id, ['role' => GroupRole::Leader->value]);
    $group->members()->attach($coLeader->id, ['role' => GroupRole::Leader->value]);

    $this->actingAs($leader);
    Livewire::test(Show::class, ['group' => $group])->call('addMember', $coLeader->id);

    expect($coLeader->fresh()->leadsGroup($group))->toBeTrue();
});

it('locks the stored logo path against client-side tampering', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)->set('logo_path', 'other/file.png');
})->throws(CannotUpdateLockedPropertyException::class);

it('stops a users.manage-only manager from making anyone a group leader via the user form', function () {
    $manager = usersManager();
    $target = userWithRole(Role::Teacher);
    $group = Group::factory()->create();
    $this->actingAs($manager);

    Livewire::test(Edit::class, ['user' => $target])
        ->set("memberships.{$group->id}", 'leader')
        ->call('save')
        ->assertHasErrors('memberships');

    expect($target->fresh()->leadsGroup($group))->toBeFalse();
});

it('saves accounts without any role', function () {
    $this->actingAs(admin());
    $target = member();

    Livewire::test(Edit::class, ['user' => $target])
        ->set('first_name', 'Nowe')
        ->set('roles', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($target->fresh()->first_name)->toBe('Nowe');
});

it('applies the user form rules to every CSV row', function () {
    $manager = usersManager();
    $this->actingAs($manager);
    Group::factory()->create(['name' => 'Matematyka']);
    $existingAdmin = admin();

    $path = hardeningCsv("imie;nazwisko;email;rola;grupy\n"
        ."Ja;Sam;{$manager->email};admin;\n"
        ."Inny;Admin;{$existingAdmin->email};teacher;\n"
        ."Nowa;Liderka;nowa@szkola.edu.pl;;Matematyka:lider\n"
        ."Nowy;Nauczyciel;nowy@szkola.edu.pl;coordinator;\n");

    $result = app(ImportUsersFromCsv::class)->handle($path, dryRun: false, updateExisting: true);

    expect($result['errors'])->toHaveKeys([2, 3, 4, 5])
        ->and($manager->fresh()->hasRole('admin'))->toBeFalse()
        ->and(User::query()->where('email', 'nowa@szkola.edu.pl')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'nowy@szkola.edu.pl')->exists())->toBeFalse();
});

it('never restores a deleted account from a CSV import', function () {
    $this->actingAs(admin());
    $deleted = userWithRole(Role::Teacher, ['email' => 'usuniety@szkola.edu.pl']);
    $deleted->delete();

    $result = app(ImportUsersFromCsv::class)->handle(
        hardeningCsv("imie;nazwisko;email;rola\nUsu;Niety;usuniety@szkola.edu.pl;teacher\n"),
        dryRun: false, updateExisting: true);

    expect($result['errors'])->toHaveKey(2)
        ->and(User::query()->find($deleted->id))->toBeNull();
});

it('reports setup-link failures without losing the rest of the import', function () {
    $this->actingAs(admin());
    $this->mock(SendAccountSetupLink::class)
        ->shouldReceive('handle')->andThrow(new RuntimeException('smtp down'));

    $result = app(ImportUsersFromCsv::class)->handle(
        hardeningCsv("imie;nazwisko;email;rola\nA;B;ab@szkola.edu.pl;teacher\n"),
        dryRun: false, sendSetupLinks: true);

    expect($result['failed_links'])->toBe(['ab@szkola.edu.pl'])
        ->and(User::query()->where('email', 'ab@szkola.edu.pl')->exists())->toBeTrue();
});

it('re-checks access on every Livewire request of the user list and group page', function () {
    $manager = usersManager();
    $this->actingAs($manager);
    $list = Livewire::test(Index::class);

    $manager->revokePermissionTo('users.manage');
    $list->set('search', 'a')->assertForbidden();

    $group = Group::factory()->create();
    $member = userWithRole(Role::Teacher);
    $group->members()->attach($member->id, ['role' => 'member']);
    $this->actingAs($member);
    $page = Livewire::test(Show::class, ['group' => $group]);

    $group->members()->detach($member->id);
    $page->set('search', 'a')->assertForbidden();
});

it('rejects a group hierarchy cycle and hides other groups from leaders', function () {
    $this->actingAs(admin());
    $a = Group::factory()->create(['name' => 'A']);
    $b = Group::factory()->create(['name' => 'B', 'parent_id' => $a->id]);

    Livewire::test(GroupEdit::class, ['group' => $a])
        ->set('parent_id', $b->id)
        ->call('save')
        ->assertHasErrors('parent_id');

    $leader = userWithRole(Role::Teacher);
    $b->members()->attach($leader->id, ['role' => 'leader']);
    Group::factory()->create(['name' => 'Tajna grupa']);
    $this->actingAs($leader);

    Livewire::test(GroupEdit::class, ['group' => $b])->assertDontSee('Tajna grupa');
});

it('does not undo admin decisions when seeders run again on deployment', function () {
    config(['mci.admin.email' => 'admin@szkola.edu.pl', 'mci.admin.password' => 'TajneHaslo123']);
    $this->seed([SystemGroupsSeeder::class, AdminUserSeeder::class]);

    Group::query()->where('slug', 'nauczyciele')->first()->update(['name' => 'Kadra']);
    Group::query()->where('slug', 'sport')->first()->delete();
    $admin = User::query()->where('email', 'admin@szkola.edu.pl')->sole();
    $admin->removeRole('admin');
    $admin->delete();

    $this->seed([SystemGroupsSeeder::class, AdminUserSeeder::class]);

    expect(Group::query()->where('slug', 'nauczyciele')->value('name'))->toBe('Kadra')
        ->and(Group::query()->where('slug', 'sport')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'admin@szkola.edu.pl')->exists())->toBeFalse()
        ->and(User::withTrashed()->where('email', 'admin@szkola.edu.pl')->first()->hasRole('admin'))->toBeFalse();
});

it('does not let a school admin switch the school log to the site journal', function () {
    $this->actingAs(admin());

    Livewire::test(AuditLog::class)->set('scope', 'site');
})->throws(CannotUpdateLockedPropertyException::class);
