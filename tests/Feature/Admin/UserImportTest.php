<?php

use App\Domain\Users\Actions\ImportUsersFromCsv;
use App\Enums\GroupRole;
use App\Enums\UserStatus;
use App\Livewire\Admin\Users\Import;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function csvFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'mci').'.csv';
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    $this->actingAs(admin());
    Group::factory()->create(['name' => 'Matematyka', 'slug' => 'matematyka']);
    Group::factory()->create(['name' => 'Wychowawcy', 'slug' => 'wychowawcy']);
});

it('previews without writing anything (dry run)', function () {
    $path = csvFile("imie;nazwisko;email;rola;grupy\nAnna;Kowalska;anna@szkola.edu.pl;teacher;Matematyka\n");

    $result = app(ImportUsersFromCsv::class)->handle($path, dryRun: true);

    expect($result['created'])->toBe(1)
        ->and(User::query()->where('email', 'anna@szkola.edu.pl')->exists())->toBeFalse();
});

it('imports users with multiple groups and leader flags', function () {
    $path = csvFile("\xEF\xBB\xBFimię;nazwisko;email;rola;grupy;telefon\n"
        ."Anna;Kowalska;Anna@Szkola.edu.pl;teacher;Matematyka:lider|wychowawcy;600100200\n"
        ."Jan;Nowak;jan@szkola.edu.pl;;Wychowawcy;\n");

    $result = app(ImportUsersFromCsv::class)->handle($path, dryRun: false);

    expect($result)->toMatchArray(['created' => 2, 'updated' => 0, 'errors' => []]);

    $anna = User::query()->where('email', 'anna@szkola.edu.pl')->firstOrFail();
    expect($anna->status)->toBe(UserStatus::Invited)
        ->and($anna->phone)->toBe('600100200')
        ->and($anna->hasRole('teacher'))->toBeTrue()
        ->and($anna->groups)->toHaveCount(2)
        ->and($anna->groups->firstWhere('slug', 'matematyka')->pivot->role)->toBe(GroupRole::Leader);

    expect(User::query()->where('email', 'jan@szkola.edu.pl')->first()->hasRole('teacher'))->toBeTrue();
});

it('accepts comma-separated files', function () {
    $path = csvFile("first_name,last_name,email,role,groups\nAnna,Kowalska,anna@szkola.edu.pl,staff,\n");

    $result = app(ImportUsersFromCsv::class)->handle($path, dryRun: false);

    expect($result['created'])->toBe(1)
        ->and(User::query()->firstWhere('email', 'anna@szkola.edu.pl')->hasRole('staff'))->toBeTrue();
});

it('reports invalid rows, unknown groups and duplicates by line number', function () {
    $path = csvFile("imie;nazwisko;email;rola;grupy\n"
        ."Anna;Kowalska;nie-email;teacher;\n"
        ."Jan;Nowak;jan@szkola.edu.pl;teacher;Fizyka\n"
        ."Ewa;Lis;ewa@szkola.edu.pl;dyrektor;\n"
        ."Ola;Mak;ola@szkola.edu.pl;teacher;\n"
        ."Ola;Mak;ola@szkola.edu.pl;teacher;\n");

    $result = app(ImportUsersFromCsv::class)->handle($path, dryRun: true);

    expect(array_keys($result['errors']))->toBe([2, 3, 4, 6])
        ->and($result['errors'][3])->toContain('Fizyka')
        ->and($result['created'])->toBe(1);
});

it('skips existing accounts unless updating is requested, and never removes memberships', function () {
    $existing = member(['email' => 'anna@szkola.edu.pl', 'first_name' => 'Stara']);
    $other = Group::factory()->create(['name' => 'Wolontariat']);
    $existing->groups()->attach($other->id, ['role' => 'member']);

    $path = csvFile("imie;nazwisko;email;rola;grupy\nAnna;Kowalska;anna@szkola.edu.pl;teacher;Matematyka\n");

    expect(app(ImportUsersFromCsv::class)->handle($path, dryRun: false)['skipped'])->toBe(1);
    expect($existing->fresh()->first_name)->toBe('Stara');

    app(ImportUsersFromCsv::class)->handle($path, dryRun: false, updateExisting: true);

    $existing->refresh();
    expect($existing->first_name)->toBe('Anna')
        ->and($existing->groups()->pluck('name')->sort()->values()->all())->toBe(['Matematyka', 'Wolontariat']);
});

it('joins an account from another school without overwriting personal data', function () {
    $other = School::factory()->create();
    $existing = member(['email' => 'anna@szkola.edu.pl', 'first_name' => 'Anna', 'last_name' => 'Prawdziwa'], $other);

    $path = csvFile("imie;nazwisko;email;rola;grupy\nFałszywa;Zmiana;anna@szkola.edu.pl;teacher;Matematyka\n");
    app(ImportUsersFromCsv::class)->handle($path, dryRun: false);

    $existing->refresh();
    expect($existing->last_name)->toBe('Prawdziwa')
        ->and($existing->belongsToSchool($this->school))->toBeTrue()
        ->and($existing->belongsToSchool($other))->toBeTrue()
        ->and($existing->schoolGroups()->pluck('name')->all())->toBe(['Matematyka']);
});

it('runs the import from the Livewire screen and e-mails new people', function () {
    Notification::fake();
    $file = UploadedFile::fake()->createWithContent('kadra.csv', "imie;nazwisko;email;rola;grupy\nAnna;Kowalska;anna@szkola.edu.pl;teacher;Matematyka\n");

    Livewire::test(Import::class)
        ->set('file', $file)
        ->call('check')
        ->assertSet('preview.created', 1)
        ->call('import')
        ->assertRedirect(route('admin.users.index'));

    Notification::assertSentTo(User::query()->firstWhere('email', 'anna@szkola.edu.pl'), AccountSetupNotification::class);
});
