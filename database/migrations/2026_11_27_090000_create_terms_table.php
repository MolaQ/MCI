<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editable event categories and group types (docs/ARCHITECTURE.md §3.8).
 * Replaces the hard-coded EventCategory / GroupType enums: the old values
 * become portal-wide terms and existing rows are pointed at them.
 */
return new class extends Migration
{
    /** @var array<string, list<array{0: string, 1: string, 2: string, 3: ?string, 4: bool}>> key, name, icon, colour, featured */
    private const DEFAULTS = [
        'event_category' => [
            ['open_day', 'Dzień otwarty', 'building-library', null, true],
            ['competition', 'Konkurs / zawody', 'trophy', null, true],
            ['recruitment', 'Rekrutacja', 'academic-cap', null, true],
            ['ceremony', 'Uroczystość', 'sparkles', null, false],
            ['sport', 'Sport', 'flag', null, false],
            ['charity', 'Akcja charytatywna', 'heart', null, false],
            ['meeting', 'Spotkanie', 'users', null, false],
            ['other', 'Inne', 'calendar', null, false],
        ],
        'group_type' => [
            ['staff', 'Kadra', 'briefcase', '#71717a', false],
            ['subject', 'Przedmiotowa', 'book-open', '#2563eb', false],
            ['vocational', 'Zawodowa', 'wrench-screwdriver', '#ea580c', false],
            ['class_tutors', 'Wychowawcy', 'academic-cap', '#7c3aed', false],
            ['class', 'Klasa', 'user-group', '#0891b2', false],
            ['volunteering', 'Wolontariat', 'heart', '#db2777', false],
            ['sport', 'Sport', 'flag', '#16a34a', false],
            ['club', 'Koło / klub', 'puzzle-piece', '#ca8a04', false],
            ['association', 'Stowarzyszenie', 'hand-raised', '#0d9488', false],
            ['custom', 'Inna', 'tag', '#475569', false],
        ],
    ];

    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            // null = the whole portal (site admins); set = one school's own entry.
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 60);
            $table->string('icon', 40);
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'school_id', 'is_active', 'position']);
            $table->index(['kind', 'key']);
        });

        $now = now();
        $ids = [];
        foreach (self::DEFAULTS as $kind => $rows) {
            foreach ($rows as $position => [$key, $name, $icon, $color, $featured]) {
                $ids[$kind][$key] = DB::table('terms')->insertGetId([
                    'kind' => $kind, 'school_id' => null, 'key' => $key, 'name' => $name, 'icon' => $icon,
                    'color' => $color, 'position' => $position, 'is_active' => true, 'is_featured' => $featured,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('public_description')->constrained('terms')->nullOnDelete();
        });
        foreach ($ids['event_category'] as $key => $id) {
            DB::table('events')->where('category', $key)->update(['category_id' => $id]);
        }
        DB::table('events')->whereNull('category_id')->update(['category_id' => $ids['event_category']['other']]);
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('category'));

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('slug')->constrained('terms')->nullOnDelete();
        });
        foreach ($ids['group_type'] as $key => $id) {
            DB::table('groups')->where('type', $key)->update(['type_id' => $id]);
        }
        DB::table('groups')->whereNull('type_id')->update(['type_id' => $ids['group_type']['custom']]);
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->string('category', 30)->default('other'));
        Schema::table('groups', function (Blueprint $table) {
            $table->string('type', 30)->default('custom')->index();
        });

        // Portal-wide terms keep their key; a school's own entries fall back to the default.
        foreach (DB::table('terms')->whereNull('school_id')->get(['id', 'kind', 'key']) as $term) {
            $term->kind === 'event_category'
                ? DB::table('events')->where('category_id', $term->id)->update(['category' => $term->key])
                : DB::table('groups')->where('type_id', $term->id)->update(['type' => $term->key]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
        Schema::dropIfExists('terms');
    }
};
