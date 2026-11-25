<?php

namespace Database\Seeders;

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local/demo data only: ~70 staff in the first school, plus a small
 * partner school that shares a few teachers (multi-school demo).
 * Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder is disabled in production.');

            return;
        }

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

        // A partner school sharing three teachers.
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

        $this->publicFront($school, $partner);
        $this->announcements($school, $coordinators->first(), $teacherUsers, $subjectGroups->first(), $tutors);

        $this->command?->info('Demo data: 70 staff in '.$school->short_name.', partner school '.$partner->short_name.' with 3 shared teachers.');
    }

    /**
     * Public profiles, offers and events for the public front (Step 3).
     */
    private function publicFront(School $school, School $partner): void
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
        $partner->update([
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
            $s->offers()->firstOrCreate(['name' => $name], ['kind' => $kind, 'description' => $description, 'position' => $i]);
        }

        $owner = $school->users()->first();
        foreach ([
            [$school, 'Dzień Otwarty ZSM', 'open_day', 10, '10:00', 'Budynek główny', 'public', 'Zwiedzanie pracowni, pokazy robotów i spotkanie z nauczycielami. Zapraszamy uczniów klas ósmych z rodzicami.'],
            [$school, 'Międzyszkolny konkurs CAD', 'competition', 17, '09:00', 'Pracownia CAD', 'public', 'Konkurs projektowania 3D dla uczniów szkół ponadpodstawowych.'],
            [$partner, 'Turniej piłki siatkowej szkół partnerskich', 'sport', 5, '12:00', 'Hala sportowa ZS2', 'public', 'Rozgrywki drużyn ZSM i ZS2. Kibice mile widziani!'],
            [$partner, 'Dzień Otwarty ZS2', 'open_day', 24, '11:00', 'ZS2, aula', 'public', 'Poznaj klasy językowe i kierunki ekonomiczne.'],
            [$school, 'Rada pedagogiczna', 'meeting', 3, '15:00', 'Sala 12', 'school_only', null],
            [$school, 'Szkolenie BHP dla nauczycieli ZSM i ZS2', 'meeting', 8, '14:00', 'Aula', 'inter_school', null],
            [$school, 'Zbiórka dla schroniska', 'charity', 0, '08:00', 'Hol', 'public', 'Zbieramy karmę i koce — cały dzień, hol główny.'],
        ] as [$s, $title, $category, $days, $time, $location, $visibility, $public]) {
            Event::query()->firstOrCreate(['school_id' => $s->id, 'title' => $title], [
                'owner_id' => $owner?->id, 'category' => $category,
                'starts_at' => now()->addDays($days)->setTimeFromTimeString($time),
                'ends_at' => now()->addDays($days)->setTimeFromTimeString($time)->addHours(3),
                'location' => $location, 'visibility' => $visibility, 'public_description' => $public,
            ]);
        }
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
            'is_public' => true,
        ], ['everyone']);

        $publish->handle($author, $school, [
            'title' => 'Materiały na zebranie zespołu',
            'body' => '<p>Proszę o przejrzenie wyników próbnej matury przed spotkaniem.</p>',
        ], ['group:'.$subject->id]);

        app(CurrentSchool::class)->set(null);
    }
}
