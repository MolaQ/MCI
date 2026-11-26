<?php

namespace Database\Seeders;

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Domain\Invites\Actions\CreateInvite;
use App\Domain\Projects\Actions\ManageMilestones;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Tasks\Actions\SaveTask;
use App\Domain\Workflows\Actions\DecideRequest;
use App\Domain\Workflows\Actions\ManageTemplates;
use App\Domain\Workflows\Actions\SubmitRequest;
use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Group;
use App\Models\Invite;
use App\Models\Project;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\CurrentSchool;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Local/demo data only: ~70 staff in the first school with every module
 * filled in (announcements, today's agenda and the month's events, personal
 * and team tasks, workflow templates and requests, projects with milestones
 * over time, invites, offers). Locally a small partner school shares a few
 * teachers (multi-school demo); the separate demo database
 * (mci.demo.single_school) keeps to one school.
 * Locally: php artisan db:seed --class=DemoSeeder
 * On a live server: php artisan mci:demo reset (separate demo database).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Production data never gets demo records: on a live server demo data
        // goes only into the separate demo database (php artisan mci:demo reset).
        if (app()->isProduction() && DB::getDefaultConnection() !== DemoMode::CONNECTION) {
            $this->command?->error('DemoSeeder is disabled in production. Use: php artisan mci:demo reset');

            return;
        }

        $single = DB::getDefaultConnection() === DemoMode::CONNECTION && (bool) config('mci.demo.single_school', true);
        $current = app(CurrentSchool::class);
        $school = School::query()->orderBy('id')->firstOrFail();
        $current->set($school);

        $subjects = ['Matematyka', 'Język polski', 'Języki obce', 'Mechatronika', 'Mechanika i budowa maszyn', 'Informatyka', 'Wychowanie fizyczne'];
        $subjectGroups = collect($subjects)->map(fn ($name) => Group::query()->firstOrCreate(
            ['school_id' => $school->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'type' => in_array($name, ['Mechatronika', 'Mechanika i budowa maszyn'], true) ? GroupType::Vocational : GroupType::Subject],
        ));

        $group = fn (string $slug) => Group::query()->where('school_id', $school->id)->where('slug', $slug)->first();
        $all = $group('wszyscy-pracownicy');
        $teachers = $group('nauczyciele');
        $tutors = $group('wychowawcy');
        $office = $group('administracja-i-obsluga');
        $volunteering = $group('wolontariat');

        $make = function (int $count, Role $role, bool $invited = false) use ($school) {
            $factory = User::factory($count);
            $factory = $invited ? $factory->invited() : $factory;

            return $factory->create()->each(function (User $u) use ($school, $role) {
                $u->schools()->attach($school->id, ['status' => 'active', 'joined_via' => 'import']);
                $u->assignRole($role->value);
            });
        };

        $coordinators = $make(3, Role::Coordinator);
        $teacherUsers = $make(55, Role::Teacher);
        $staffUsers = $make(10, Role::Staff);
        $make(2, Role::Teacher, invited: true);

        $attach = fn (?Group $g, User $u, GroupRole $r = GroupRole::Member) => $g?->members()->syncWithoutDetaching([$u->id => ['role' => $r->value]]);

        foreach ($coordinators->concat($teacherUsers)->concat($staffUsers) as $user) {
            $attach($all, $user);
        }

        foreach ($coordinators->concat($teacherUsers)->values() as $i => $user) {
            $attach($teachers, $user);
            $attach($subjectGroups[$i % $subjectGroups->count()], $user, $i < $subjectGroups->count() ? GroupRole::Leader : GroupRole::Member);
            if ($i % 2 === 0) {
                $attach($tutors, $user);
            }
            if ($i % 7 === 0) {
                $attach($volunteering, $user, $i === 0 ? GroupRole::Leader : GroupRole::Member);
            }
        }

        foreach ($staffUsers as $user) {
            $attach($office, $user);
        }

        // The school safety officer: an ordinary teacher with an individual alerts.send grant.
        $teacherUsers->first()->givePermissionTo('alerts.send');

        // Locally: a partner school sharing three teachers (not in the single-school demo database).
        $partner = null;
        if (! $single) {
            $partner = School::query()->firstOrCreate(['slug' => 'zs2'], [
                'name' => 'Zespół Szkół nr 2', 'short_name' => 'ZS2',
                'settings' => ['allow_self_registration' => true, 'max_profile_visibility' => 'school'],
            ]);
            SystemGroupsSeeder::seedFor($partner);
            $current->set($partner);
            foreach ($teacherUsers->take(3) as $user) {
                $user->schools()->syncWithoutDetaching([$partner->id => ['status' => 'active', 'joined_via' => 'admin']]);
                $user->unsetRelation('roles')->assignRole(Role::Teacher->value);
            }
            $current->set(null);
        }

        $this->publicFront($school, $partner);
        $this->announcements($school, $coordinators->first(), $teacherUsers, $subjectGroups->first(), $tutors);
        $this->tasks($school, $coordinators->first(), $teacherUsers, $tutors);
        $this->workflows($school, $coordinators->first(), $teacherUsers);
        $this->projects($school, $partner, $coordinators->first(), $teacherUsers, $teachers);
        $this->invites($school, $coordinators->first(), $teachers, $tutors);

        $this->command?->info('Demo data: 70 staff in '.$school->short_name.($partner ? ', partner school '.$partner->short_name.' with 3 shared teachers.' : ' (single school, every module).'));
    }

    /**
     * Public profiles, offers and events for the public front (Step 3).
     */
    private function publicFront(School $school, ?School $partner): void
    {
        $school->update([
            'city' => $school->city ?: 'Kraków',
            'tagline' => 'Technikum i szkoła branżowa z tradycją — mechanika, mechatronika, informatyka.',
            'description' => "Kształcimy techników i fachowców poszukiwanych na rynku pracy.\nNowoczesne pracownie CNC, robotyki i programowania, praktyki u partnerów przemysłowych.",
            'address' => 'ul. Przykładowa 10, 30-001 Kraków',
            'phone' => '12 345 67 89',
            'public_email' => 'sekretariat@example.edu.pl',
            'website' => 'https://example.edu.pl',
            'color' => $school->color ?: '#1d4ed8',
        ]);
        $partner?->update([
            'city' => 'Kraków', 'color' => '#047857',
            'tagline' => 'Liceum i technikum ekonomiczne — języki, logistyka, rachunkowość.',
            'description' => 'Szkoła partnerska ZSM. Wspólne konkursy, zawody sportowe i dni otwarte.',
            'public_email' => 'zs2@example.edu.pl',
        ]);

        foreach ([
            [$school, 'technikum', 'Technik mechatronik', 'Automatyka, robotyka i programowanie sterowników PLC.'],
            [$school, 'technikum', 'Technik mechanik', 'Projektowanie CAD/CAM, obrabiarki CNC.'],
            [$school, 'technikum', 'Technik informatyk', 'Sieci, administracja systemami i programowanie.'],
            [$school, 'branzowa', 'Operator obrabiarek skrawających', 'Praktyczna nauka zawodu u pracodawców.'],
            [$school, 'kurs', 'KKZ: Programowanie obrabiarek CNC', 'Kurs dla dorosłych, zajęcia popołudniowe.'],
            [$partner, 'liceum', 'Klasa językowa', 'Rozszerzony angielski i niemiecki.'],
            [$partner, 'technikum', 'Technik logistyk', 'Magazyn, spedycja, transport.'],
            [$partner, 'technikum', 'Technik ekonomista', 'Rachunkowość i finanse firm.'],
        ] as $i => [$s, $kind, $name, $description]) {
            if (! $s) {
                continue;
            }
            $s->offers()->firstOrCreate(['name' => $name], ['kind' => $kind, 'description' => $description, 'position' => $i]);
        }

        $owner = $school->users()->first();
        // [school, title, category, days from today, time (null = all day), hours, location, visibility, public text]
        foreach ([
            [$school, 'Apel poranny', 'meeting', 0, '08:00', 0.5, 'Aula', 'school_only', null],
            [$school, 'Zbiórka dla schroniska', 'charity', 0, null, 0, 'Hol', 'public', 'Zbieramy karmę i koce — cały dzień, hol główny.'],
            [$school, 'Konsultacje dla rodziców', 'meeting', 0, '16:00', 2, 'Sale lekcyjne', 'public', 'Spotkania z wychowawcami i nauczycielami przedmiotów.'],
            [$school, 'Spotkanie z absolwentami', 'meeting', -4, '12:00', 2, 'Aula', 'public', 'Absolwenci opowiadają o studiach i pracy w przemyśle.'],
            [$school, 'Rada pedagogiczna', 'meeting', 3, '15:00', 2, 'Sala 12', 'school_only', null],
            [$partner ?? $school, 'Turniej piłki siatkowej', 'sport', 5, '12:00', 3, $partner ? 'Hala sportowa ZS2' : 'Hala sportowa', 'public', 'Rozgrywki drużyn klasowych. Kibice mile widziani!'],
            [$school, 'Matura próbna z matematyki', 'meeting', 6, '09:00', 3, 'Sala gimnastyczna', 'school_only', null],
            [$school, 'Szkolenie BHP dla nauczycieli', 'meeting', 8, '14:00', 2, 'Aula', $partner ? 'inter_school' : 'school_only', null],
            [$school, 'Dzień Otwarty ZSM', 'open_day', 10, '10:00', 4, 'Budynek główny', 'public', 'Zwiedzanie pracowni, pokazy robotów i spotkanie z nauczycielami. Zapraszamy uczniów klas ósmych z rodzicami.'],
            [$school, 'Tydzień zawodowca', 'open_day', 12, null, 0, 'Pracownie zawodowe', 'public', 'Pięć dni warsztatów z pracodawcami: CNC, robotyka, programowanie.', 4],
            [$school, 'Międzyszkolny konkurs CAD', 'competition', 17, '09:00', 5, 'Pracownia CAD', 'public', 'Konkurs projektowania 3D dla uczniów szkół ponadpodstawowych.'],
            [$school, 'Wigilia szkolna', 'meeting', 24, '11:00', 2, 'Aula', 'school_only', null],
            [$partner, 'Dzień Otwarty ZS2', 'open_day', 24, '11:00', 3, 'ZS2, aula', 'public', 'Poznaj klasy językowe i kierunki ekonomiczne.'],
        ] as $row) {
            [$s, $title, $category, $days, $time, $hours, $location, $visibility, $public] = $row;
            if (! $s) {
                continue;
            }
            $span = $row[9] ?? 0;
            $start = $time ? now()->addDays($days)->setTimeFromTimeString($time) : now()->addDays($days)->startOfDay();
            Event::query()->firstOrCreate(['school_id' => $s->id, 'title' => $title], [
                'owner_id' => $owner?->id, 'category' => $category,
                'starts_at' => $start,
                'ends_at' => $time ? $start->copy()->addMinutes((int) ($hours * 60)) : $start->copy()->addDays($span)->endOfDay(),
                'all_day' => $time === null,
                'location' => $location, 'visibility' => $visibility, 'public_description' => $public,
            ]);
        }
    }

    private function workflows(School $school, User $coordinator, $teachers): void
    {
        if (WorkflowTemplate::query()->exists()) {
            return;
        }
        $admin = User::query()->where('email', config('mci.admin.email'))->first();
        $manage = app(ManageTemplates::class);
        app(CurrentSchool::class)->set($school, $coordinator);

        $trip = $manage->save($coordinator, $school, [
            'name' => 'Wycieczka szkolna', 'icon' => 'map',
            'description' => 'Zgoda na wyjście lub wyjazd z uczniami. Dołącz program i listę uczestników.',
        ], [
            ['label' => 'Klasa', 'type' => 'text', 'required' => true],
            ['label' => 'Data wyjazdu', 'type' => 'date', 'required' => true],
            ['label' => 'Liczba uczniów', 'type' => 'number', 'required' => true],
            ['label' => 'Transport', 'type' => 'select', 'options' => "Autokar\nPociąg\nPieszo", 'required' => true],
            ['label' => 'Program', 'type' => 'textarea', 'required' => false],
        ], [
            ['name' => 'Wicedyrektor', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'sla_hours' => 48, 'task_on_reach' => true],
            ['name' => 'Dyrektor', 'approver_type' => 'role', 'approver_id' => 'admin', 'sla_hours' => 72, 'followup_task' => 'Zebrać zgody rodziców i oddać listę w sekretariacie'],
        ]);
        $manage->save($coordinator, $school, ['name' => 'Zakup sprzętu', 'icon' => 'shopping-cart', 'min_attachments' => 1,
            'description' => 'Zakupy powyżej 500 zł. Dołącz ofertę lub zrzut ekranu ze sklepu.'], [
                ['label' => 'Kwota brutto (zł)', 'type' => 'number', 'required' => true],
                ['label' => 'Uzasadnienie', 'type' => 'textarea', 'required' => true],
            ], [
                ['name' => 'Kierownik pracowni', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'sla_hours' => 72],
                ['name' => 'Księgowość i dyrektor', 'approver_type' => 'role', 'approver_id' => 'admin'],
            ]);

        if ($admin?->is_site_admin) {
            $manage->save($admin, null, ['name' => 'Wydarzenie międzyszkolne', 'icon' => 'globe-europe-africa',
                'description' => 'Wspólne wydarzenie kilku szkół: zawody, konkurs, dzień otwarty.'], [
                    ['label' => 'Szkoły partnerskie', 'type' => 'text', 'required' => true],
                    ['label' => 'Termin', 'type' => 'date', 'required' => true],
                ], [['name' => 'Koordynator szkoły', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'sla_hours' => 120]]);
        }

        // One request already past the first stage, waiting for the director.
        $teacher = $teachers[1];
        app(CurrentSchool::class)->set($school, $teacher);
        $request = app(SubmitRequest::class)->handle($teacher, $school, $trip, 'Wyjazd 3TM do Muzeum Techniki', [
            'klasa' => '3TM', 'data_wyjazdu' => now()->addWeeks(3)->toDateString(), 'liczba_uczniow' => '26',
            'transport' => 'Pociąg', 'program' => "8:00 zbiórka na dworcu\n10:30 Muzeum Techniki\n15:00 powrót",
        ]);
        app(CurrentSchool::class)->set($school, $coordinator);
        app(DecideRequest::class)->handle($coordinator, $request, 'approve', '<p>Zgoda, proszę o listę opiekunów.</p>');

        app(CurrentSchool::class)->set($school, $teachers[2]);
        app(SubmitRequest::class)->handle($teachers[2], $school, $trip, 'Wyjście 1A do kina', [
            'klasa' => '1A', 'data_wyjazdu' => now()->addDays(10)->toDateString(), 'liczba_uczniow' => '30', 'transport' => 'Pieszo',
        ]);

        app(CurrentSchool::class)->set(null);
    }

    private function tasks(School $school, User $coordinator, $teachers, ?Group $tutors): void
    {
        if (Task::query()->where('school_id', $school->id)->exists()) {
            return;
        }
        $save = app(SaveTask::class);
        $admin = User::query()->where('email', config('mci.admin.email'))->first();

        app(CurrentSchool::class)->set($school, $coordinator);
        $save->handle($coordinator, $school, [
            'title' => 'Przygotować pracownie na Dzień Otwarty', 'priority' => 3,
            'description' => '<p>Sprawdźcie stanowiska i ustawcie prezentacje. <span data-type="mention" data-id="user:'.$teachers[4]->id.'" data-label="x">@x</span> odpowiada za roboty.</p>',
            'due_date' => now()->addDays(3)->toDateString(), 'due_time' => '15:00',
        ], $tutors ? ['group:'.$tutors->id] : ['role:teacher'], [
            ['body' => 'Lista sal i opiekunów'], ['body' => 'Plakaty na korytarzu', 'is_done' => true], ['body' => 'Stanowiska z robotami'],
        ]);
        $save->handle($coordinator, $school, [
            'title' => 'Uzupełnić oceny śródroczne', 'priority' => 4, 'due_date' => now()->subDay()->toDateString(),
        ], ['role:teacher']);
        $save->handle($coordinator, $school, [
            'title' => 'Zebrać zgody na wycieczkę', 'due_date' => now()->toDateString(), 'due_time' => '12:00',
        ], ['user:'.$teachers[0]->id]);

        if ($admin && $admin->belongsToSchool($school)) {
            $save->handle($coordinator, $school, [
                'title' => 'Zatwierdzić plan dyżurów na Dzień Otwarty', 'priority' => 3,
                'due_date' => now()->toDateString(), 'due_time' => '14:00',
            ], ['user:'.$admin->id]);
            app(CurrentSchool::class)->set($school, $admin);
            $save->handle($admin, $school, ['title' => 'Zamówić tonery do drukarek', 'due_date' => now()->toDateString()]);
            $save->handle($admin, $school, ['title' => 'Sprawdzić kopię zapasową', 'priority' => 1]);
            $save->handle($admin, $school, ['title' => 'Plan dyżurów na grudzień', 'priority' => 3, 'due_date' => now()->addDays(2)->toDateString()], [], [
                ['body' => 'Parter', 'is_done' => true], ['body' => 'I piętro'], ['body' => 'Boisko'],
            ]);
        }

        app(CurrentSchool::class)->set(null);
    }

    private function announcements(School $school, User $author, $teachers, Group $subject, ?Group $tutors): void
    {
        app(CurrentSchool::class)->set($school, $author);
        $publish = app(PublishAnnouncement::class);

        if (Announcement::query()->where('school_id', $school->id)->exists()) {
            return;
        }

        $publish->handle($author, $school, [
            'title' => 'Zmiana terminu rady pedagogicznej',
            'body' => '<p>Rada pedagogiczna odbędzie się w <strong>czwartek o 15:00</strong> w sali 12. Prosimy o potwierdzenie.</p>',
            'priority' => 'important', 'requires_confirmation' => true,
        ], ['role:teacher', 'role:coordinator']);

        $publish->handle($author, $school, [
            'title' => 'Dyżury wychowawców podczas Dnia Otwartego',
            'body' => '<p>Grafik dyżurów jest w pokoju nauczycielskim. <span data-type="mention" data-id="user:'.$teachers[4]->id.'" data-label="x">@x</span> koordynuje pokazy robotów.</p>',
        ], $tutors ? ['group:'.$tutors->id] : ['everyone']);

        $publish->handle($author, $school, [
            'title' => 'Zapraszamy na Dzień Otwarty!',
            'body' => '<p>Zapraszamy kandydatów i rodziców do zwiedzania pracowni. Szczegóły na stronie szkoły.</p>',
            'is_public' => true, 'pinned' => true,
        ], ['everyone']);

        $publish->handle($author, $school, [
            'title' => 'Dziś skrócone lekcje — 30 minut',
            'body' => '<p>Z powodu konsultacji dla rodziców wszystkie lekcje trwają dziś 30 minut. Ostatnia lekcja kończy się o 13:35.</p>',
            'priority' => 'urgent', 'is_public' => true,
        ], ['everyone']);

        $publish->handle($author, $school, [
            'title' => 'Wyniki konkursu „Mistrz CNC”',
            'body' => '<p>Gratulujemy laureatom! Dyplomy wręczymy na apelu.</p>',
            'is_public' => true,
        ], ['everyone']);

        $publish->handle($author, $school, [
            'title' => 'Materiały na zebranie zespołu',
            'body' => '<p>Proszę o przejrzenie wyników próbnej matury przed spotkaniem.</p>',
        ], ['group:'.$subject->id]);

        app(CurrentSchool::class)->set(null);
    }

    private function projects(School $school, ?School $partner, User $coordinator, $teachers, ?Group $teachersGroup): void
    {
        if (Project::query()->exists()) {
            return;
        }
        $current = app(CurrentSchool::class);
        $current->set($school, $coordinator);
        $save = app(SaveProject::class);
        $milestones = app(ManageMilestones::class);
        $admin = User::query()->where('email', config('mci.admin.email'))->first();
        $team = array_values(array_filter([$teachersGroup ? 'group:'.$teachersGroup->id : null, $admin ? 'user:'.$admin->id : null]));

        // [name, kind, status, starts (days), ends (days), visibility, milestones [title, days, done], budget]
        foreach ([
            ['Dzień Otwarty 2027', 'open_day', 'in_progress', -10, 42, 'public', [['Plan pokazów w pracowniach', -3, true], ['Zamówienie ulotek i plakatów', 2, false], ['Próba generalna', 30, false], ['Dzień otwarty', 42, false]], "Rada rodziców: 2 000 zł\nSala gimnastyczna i pracownie CNC"],
            ['Konkurs robotyki „RoboZSM”', 'competition', 'in_progress', -21, 35, 'public', [['Regulamin i zgłoszenia', -14, true], ['Eliminacje szkolne', 9, false], ['Finał', 35, false]], 'Nagrody: sponsor (3 zestawy Arduino)'],
            ['Paczka świąteczna — wolontariat', 'event', 'in_progress', -7, 24, 'public', [['Zbiórka darów', 14, false], ['Pakowanie i dostawa', 23, false]], null],
            ['Grant „Laboratoria przyszłości”', 'grant', 'in_progress', -60, 75, 'school', [['Zamówienie drukarek 3D', -20, true], ['Szkolenie nauczycieli', 12, false], ['Rozliczenie grantu', 75, false]], "Kwota: 120 000 zł\nRozliczenie do końca lutego"],
            ['Remont auli', 'investment', 'on_hold', -30, 60, 'school', [['Wybór wykonawcy', -5, false]], 'Czekamy na decyzję organu prowadzącego'],
            ['Erasmus+ Praktyki w Hiszpanii', 'erasmus', 'planned', 45, 230, 'team', [['Rekrutacja uczniów', 50, false], ['Wyjazd', 120, false]], null],
            ['Modernizacja pracowni CNC', 'investment', 'completed', -150, -30, 'school', [], null],
        ] as $i => [$name, $kind, $status, $from, $to, $visibility, $steps, $budget]) {
            $lead = $i === 3 ? ($teachers->get(1) ?? $coordinator) : $coordinator;
            $project = $save->handle($coordinator, $school, [
                'name' => $name, 'kind' => $kind, 'status' => $status, 'visibility' => $visibility,
                'starts_on' => now()->addDays($from)->toDateString(), 'ends_on' => now()->addDays($to)->toDateString(),
                'coordinator_id' => $lead->id, 'budget_notes' => $budget,
                'description' => '<p>'.e($name).': przykładowy projekt demonstracyjny z kamieniami milowymi.</p>',
            ], $visibility === 'team' ? ['user:'.$coordinator->id] : $team, $partner && $i === 0 ? [$partner->id] : []);
            foreach ($steps as $n => [$title, $days, $done]) {
                $m = $milestones->save($coordinator, $project, ['title' => $title, 'due_date' => now()->addDays($days)->toDateString(), 'due_time' => $n === count($steps) - 1 ? '10:00' : null]);
                if ($done) {
                    $milestones->toggle($coordinator, $m);
                }
            }
            if ($i === 0) {
                $task = app(SaveTask::class)->handle($coordinator, $school, ['title' => 'Przygotować stoisko mechatroniki', 'due_date' => now()->addDays(10)->toDateString()], ['user:'.$teachers->first()->id]);
                $task->update(['project_id' => $project->id, 'project_milestone_id' => $project->milestones()->skip(2)->first()?->id]);
            }
        }
        $current->set(null);
    }

    private function invites(School $school, User $coordinator, ?Group $teachers, ?Group $tutors): void
    {
        if (Invite::query()->where('school_id', $school->id)->exists() || ! $teachers) {
            return;
        }
        app(CurrentSchool::class)->set($school, $coordinator);
        $create = app(CreateInvite::class);
        $create->handle($coordinator, $school, [
            'kind' => 'group_link', 'label' => 'Plakat w pokoju nauczycielskim',
            'groups' => array_filter([$teachers->id => 'member', $tutors?->id => $tutors ? 'member' : null]),
            'expires_at' => now()->addDays(30), 'allow_self_registration' => true,
        ]);
        $create->handle($coordinator, $school, [
            'kind' => 'personal', 'email' => 'nowy.nauczyciel@example.edu.pl', 'label' => 'Nowy nauczyciel informatyki',
            'groups' => [$teachers->id => 'member'], 'send_email' => false,
        ]);
        app(CurrentSchool::class)->set(null);
    }
}
