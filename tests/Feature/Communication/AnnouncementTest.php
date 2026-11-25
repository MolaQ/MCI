<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\Role;
use App\Livewire\Communication\Center;
use App\Livewire\Communication\Show;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\School;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function publish($author, array $data, array $keys): Announcement
{
    actAsSchool(test()->school, $author);

    return app(PublishAnnouncement::class)->handle($author, test()->school, $data + ['title' => 'Test', 'body' => '<p>Treść</p>'], $keys);
}

function mention(string $id, string $label = 'X'): string
{
    return '<span data-type="mention" data-id="'.$id.'" data-label="'.$label.'">@'.$label.'</span>';
}

it('delivers to the union of groups, roles and people without duplicates', function () {
    $author = userWithRole(Role::Coordinator);
    $group = Group::factory()->create();
    $teacherInGroup = userWithRole(Role::Teacher);
    $group->members()->attach($teacherInGroup->id, ['role' => 'member']);
    $otherTeacher = userWithRole(Role::Teacher);
    $staff = userWithRole(Role::Staff);
    $outsider = userWithRole(Role::Teacher, school: School::factory()->create());

    expect(fn () => publish($author, [], ['user:'.$outsider->id]))->toThrow(ValidationException::class);
    $a = publish($author, [], ['group:'.$group->id, 'role:teacher', 'user:'.$staff->id]);

    expect($a->recipients()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$teacherInGroup->id, $otherTeacher->id, $staff->id])->sort()->values()->all())
        ->and($a->recipients_count)->toBe(3);
});

it('lets a teacher address only their own groups', function () {
    $teacher = userWithRole(Role::Teacher);
    $mine = Group::factory()->create();
    $mine->members()->attach($teacher->id, ['role' => 'member']);
    $mine->members()->attach(userWithRole(Role::Teacher)->id, ['role' => 'member']);
    $foreign = Group::factory()->create();

    expect(publish($teacher, [], ['group:'.$mine->id])->recipients_count)->toBe(1);

    expect(fn () => publish($teacher, [], ['group:'.$foreign->id]))->toThrow(ValidationException::class)
        ->and(fn () => publish($teacher, [], ['everyone']))->toThrow(ValidationException::class)
        ->and(fn () => publish($teacher, [], ['group+:'.$mine->id]))->toThrow(ValidationException::class)
        ->and(fn () => publish($teacher, ['is_public' => true], ['group:'.$mine->id]))->toThrow(AuthorizationException::class)
        ->and(fn () => publish(member(), [], ['user:'.$teacher->id]))->toThrow(AuthorizationException::class);
});

it('sanitizes the body and rewrites forged or foreign mentions', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher, ['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $outsider = userWithRole(Role::Teacher, school: School::factory()->create());

    $a = publish($author, ['body' => '<p>Hej '.mention('user:'.$jan->id, 'Dyrektor').' i '.mention('user:'.$outsider->id, 'Obcy')
        .'<script>alert(1)</script><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)></p>'], ['everyone']);

    expect($a->body)->toContain('data-label="Jan Kowalski"')->toContain('@Jan Kowalski')
        ->not->toContain('Dyrektor')->not->toContain('user:'.$outsider->id)
        ->not->toContain('<script')->not->toContain('javascript:')->not->toContain('onerror')
        ->and($a->mentions()->count())->toBe(1)
        ->and(RichText::isBlank('<p> </p><p><br></p>'))->toBeTrue();
});

it('notifies a mentioned group only when the author may address it', function () {
    $teacher = userWithRole(Role::Teacher);
    $mine = Group::factory()->create();
    $mine->members()->attach($teacher->id, ['role' => 'member']);
    $foreign = Group::factory()->create();
    $foreignMember = userWithRole(Role::Teacher);
    $foreign->members()->attach($foreignMember->id, ['role' => 'member']);
    $mine->members()->attach(userWithRole(Role::Teacher)->id, ['role' => 'member']);

    $a = publish($teacher, ['body' => '<p>'.mention('group:'.$foreign->id).'</p>'], ['group:'.$mine->id]);

    expect($a->recipients()->whereKey($foreignMember->id)->exists())->toBeFalse()
        ->and($a->mentions()->where('mentionable_type', 'group')->count())->toBe(1);
});

it('rings the bell for mentions and important posts but not for routine ones', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    $ola = userWithRole(Role::Teacher);

    publish($author, [], ['role:teacher']);
    expect($jan->notifications()->count())->toBe(0);

    publish($author, ['body' => '<p>'.mention('user:'.$jan->id).'</p>'], ['role:teacher']);
    publish($author, ['priority' => 'urgent'], ['user:'.$ola->id]);

    expect($jan->notifications()->count())->toBe(1)
        ->and($jan->notifications()->first()->data['kind'])->toBe('mention')
        ->and($ola->notifications()->count())->toBe(1)
        ->and($jan->unreadAnnouncementsCount())->toBe(2);
});

it('shows a post to its recipients only, marks it read and records confirmation', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    $other = userWithRole(Role::Teacher);
    $a = publish($author, ['requires_confirmation' => true], ['user:'.$jan->id]);

    $this->actingAs($other)->get(route('communication.show', $a))->assertForbidden();

    $this->actingAs($jan);
    Livewire::test(Show::class, ['announcement' => $a])->call('confirm');
    $row = $a->recipients()->whereKey($jan->id)->first()->pivot;

    expect($row->read_at)->not->toBeNull()->and($row->confirmed_at)->not->toBeNull()
        ->and($jan->unreadNotifications()->count())->toBe(0);

    $this->actingAs($author)->get(route('communication.show', $a))->assertOk()->assertSee(__('Potwierdziło'));
});

it('keeps scheduled posts hidden until they go live, then the scheduler delivers them', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    $a = publish($author, ['priority' => 'important', 'publish_at' => now()->addHour()], ['user:'.$jan->id]);

    $this->actingAs($jan)->get(route('communication.show', $a))->assertForbidden();
    expect($jan->notifications()->count())->toBe(0);

    $this->travel(61)->minutes();
    $this->artisan('mci:deliver-announcements')->assertSuccessful();
    $this->artisan('mci:deliver-announcements')->assertSuccessful();

    expect($jan->notifications()->count())->toBe(1);
    $this->actingAs($jan)->get(route('communication.show', $a))->assertOk();
});

it('filters the inbox and marks everything read', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    publish($author, ['title' => 'Zwykły'], ['user:'.$jan->id]);
    publish($author, ['title' => 'Pilny', 'priority' => 'urgent'], ['user:'.$jan->id]);
    publish($author, ['title' => 'Nie dla Jana'], ['user:'.userWithRole(Role::Staff)->id]);

    $this->actingAs($jan);
    actAsSchool($this->school, $jan);
    Livewire::test(Center::class)
        ->assertSee('Zwykły')->assertSee('Pilny')->assertDontSee('Nie dla Jana')
        ->call('setFilter', 'important')->assertSee('Pilny')->assertDontSee('Zwykły')
        ->call('markAllRead');

    expect($jan->unreadAnnouncementsCount())->toBe(0);
});

it('requires at least one recipient', function () {
    publish(userWithRole(Role::Coordinator), [], []);
})->throws(ValidationException::class);

it('searches mentions only inside the active school', function () {
    $me = userWithRole(Role::Teacher);
    userWithRole(Role::Teacher, ['first_name' => 'Zenon', 'last_name' => 'Tutejszy']);
    userWithRole(Role::Teacher, ['first_name' => 'Zenon', 'last_name' => 'Obcy'], School::factory()->create());

    $this->actingAs($me)->getJson(route('mentions.search', ['q' => 'Zenon']))
        ->assertOk()->assertJsonFragment(['label' => 'Zenon Tutejszy'])->assertJsonMissing(['label' => 'Zenon Obcy']);
});
