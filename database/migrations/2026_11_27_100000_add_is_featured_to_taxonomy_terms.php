<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event categories marked "wyróżniaj" put their next event in the large tile
 * of the public front. Open days, competitions and recruitment start featured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
        });

        DB::table('taxonomy_terms')->where('kind', 'event_category')->whereNull('school_id')
            ->whereIn('key', ['open_day', 'competition', 'recruitment'])->update(['is_featured' => true]);
    }

    public function down(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
