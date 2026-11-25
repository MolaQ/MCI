<?php

use App\Enums\TaxonomyKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editable event categories and group types. Rows with school_id = null are
 * the site-wide list; rows with a school_id belong to that school only.
 * events.category and groups.type keep storing the term key, so existing
 * data stays valid: the built-in keys are inserted here as site-wide terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 60);
            $table->string('icon', 40);
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'school_id', 'key']);
            $table->index(['kind', 'key']);
        });

        $now = now();
        $names = ['event_category' => 'enums.event_category', 'group_type' => 'enums.group_type'];
        foreach (TaxonomyKind::cases() as $kind) {
            $position = 0;
            foreach ($kind->builtIn() as $key => [$icon, $color]) {
                DB::table('taxonomy_terms')->insert([
                    'kind' => $kind->value, 'school_id' => null, 'key' => $key,
                    'name' => trans($names[$kind->value].'.'.$key, [], 'pl'),
                    'icon' => $icon, 'color' => $color, 'position' => $position++, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
